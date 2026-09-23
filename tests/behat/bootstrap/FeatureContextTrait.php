<?php

/**
 * @file
 * Feature context trait for testing the web half of Behat-steps.
 *
 * This is a test for the test framework itself. Consumer project should not
 * use any steps or functions from this file.
 */

declare(strict_types=1);

use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Hook\BeforeScenario;
use Behat\Mink\Driver\Selenium2Driver;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use DrevOps\BehatSteps\Behat\Tag;
use Symfony\Component\BrowserKit\Cookie;

/**
 * Defines application features from the specific context.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\RawContext
 */
trait FeatureContextTrait {

  /**
   * Whether to use center scroll alignment for testing.
   */
  protected bool $testElementScrollCenter = TRUE;

  /**
   * Stop Mink sessions before scenarios that will spawn sub-processes.
   *
   * When a @javascript scenario runs in the parent process, Mink keeps the
   * Selenium2/Chrome connection open (via resetSessions()). This causes
   * child processes to hang when they try to establish their own connection.
   *
   * @see \Behat\MinkExtension\Listener\SessionsListener::prepareDefaultMinkSession()
   */
  #[BeforeScenario]
  public function testStopSessionsBeforeSubProcess(BeforeScenarioScope $scope): void {
    $has_trait_tag = (bool) array_filter(Tag::on($scope->getScenario()), fn(string $tag): bool => str_starts_with($tag, 'trait:'));

    if ($has_trait_tag) {
      $this->getMink()->stopSessions();
    }
  }

  /**
   * Sleep for the given number of seconds.
   *
   * @code
   * When sleep for 5 seconds
   * @endcode
   */
  #[When('sleep for :seconds second(s)')]
  public function testSleepForSeconds(int|string $seconds): void {
    sleep((int) $seconds);
  }

  /**
   * Assert that a cookie exists.
   */
  #[Given('cookie :name exists')]
  public function testAssertCookieExists(string $name): void {
    $cookies = $this->testGetAllCookies();

    if (!isset($cookies[$name])) {
      throw new \Exception(sprintf('Cookie "%s" does not exist.', $name));
    }
  }

  /**
   * Assert that a cookie does not exist.
   */
  #[Given('cookie :name does not exist')]
  public function testAssertCookieNotExists(string $name): void {
    $cookies = $this->testGetAllCookies();

    if (isset($cookies[$name])) {
      throw new \Exception(sprintf('Cookie "%s" exists but should not.', $name));
    }
  }

  /**
   * Set a test cookie with the given name and value.
   */
  #[Given('I set a test cookie with name :name and value :value')]
  public function testSetCookie(string $name, string $value): void {
    $session = $this->getSession();

    $driver = $session->getDriver();

    // WebDriver-based drivers like Selenium2Driver.
    if (method_exists($driver, 'getWebDriverSession')) {
      $driver->getWebDriverSession()->setCookie([
        'name' => $name,
        'value' => rawurlencode($value),
        'secure' => FALSE,
      ]);
    }

    // BrowserKit-based drivers like GoutteDriver.
    if (method_exists($driver, 'getClient')) {
      $cookie_jar = $driver->getClient()->getCookieJar();
      $cookie = new Cookie($name, rawurlencode($value));
      $cookie_jar->set($cookie);
    }

    // CDP-based drivers like the Chrome (chrome-mink) driver. Their own
    // setCookie() binds the cookie to the configured base URL, so a page served
    // from another origin never receives it. Writing through the document keeps
    // the cookie on the origin the scenario is on.
    if (method_exists($driver, 'getCookies')) {
      $driver->evaluateScript(sprintf('document.cookie = %s;', json_encode($name . '=' . rawurlencode($value) . '; path=/')));
    }
  }

  /**
   * Set scroll alignment to top.
   */
  #[Given('I set scroll to top alignment')]
  public function testSetScrollToTopAlignment(): void {
    $this->testElementScrollCenter = FALSE;
  }

  /**
   * Assert the viewport width is approximately the specified value.
   *
   * Allows a tolerance of 20px to account for scrollbar width differences
   * between resizeWindow() (outer size) and window.innerWidth (inner size).
   */
  #[Then('the viewport should have the width of :width')]
  public function testAssertViewportWidth(string $width): void {
    $current = $this->responsiveGetCurrentDimensions();
    $expected = (int) $width;
    $tolerance = 20;
    if (abs($current['width'] - $expected) > $tolerance) {
      throw new \RuntimeException(sprintf('Expected viewport width within %dpx of %d, but got %d.', $tolerance, $expected, $current['width']));
    }
  }

  /**
   * Assert the order the scenario resolves drivers in.
   */
  #[Then('the scenario driver order should be :order')]
  public function testAssertDriverOrder(string $order): void {
    $actual = implode(', ', array_keys($this->getDriverManager()->getScenarioDrivers()));

    if ($actual !== $order) {
      throw new \RuntimeException(sprintf('Expected the driver order "%s", but it was "%s".', $order, $actual));
    }
  }

  /**
   * Assert which driver class a capability resolves to.
   *
   * The capability is named by the part before 'CapabilityInterface', so
   * 'Cache' names 'CacheCapabilityInterface'.
   */
  #[Then('the :capability capability should resolve to the :expected driver')]
  public function testAssertCapabilityResolvesTo(string $capability, string $expected): void {
    $interface = sprintf('DrevOps\BehatSteps\Driver\Capability\%sCapabilityInterface', $capability);

    if (!interface_exists($interface)) {
      throw new \RuntimeException(sprintf('There is no "%s" capability interface.', $capability));
    }

    $actual = $this->driverFor($interface)::class;

    if ($actual !== $expected) {
      throw new \RuntimeException(sprintf('Expected the "%s" capability to resolve to "%s", but it resolved to "%s".', $capability, $expected, $actual));
    }
  }

  /**
   * Get a list of cookies.
   *
   * @return array<string, string>
   *   List of cookies.
   */
  protected function testGetAllCookies(): array {
    $cookie_list = [];

    $driver = $this->getSession()->getDriver();
    if ($driver instanceof Selenium2Driver) {
      $cookies = $driver->getWebDriverSession()->getAllCookies();
      foreach ($cookies as $cookie) {
        $cookie_list[$cookie['name']] = $cookie['value'];
      }
    }

    // CDP-based drivers like the Chrome (chrome-mink) driver.
    elseif (method_exists($driver, 'getCookies')) {
      foreach ($driver->getCookies() as $cookie) {
        $cookie_list[$cookie['name']] = rawurldecode((string) $cookie['value']);
      }
    }
    else {
      /** @var \Behat\Mink\Driver\BrowserKitDriver $driver */
      // @phpstan-ignore-next-line
      $cookie_list = $driver->getClient()->getCookieJar()->allValues($driver->getCurrentUrl());
    }

    return $cookie_list;
  }

}
