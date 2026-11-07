<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Extension\Browser\Adapter;

use Behat\Mink\Driver\DriverInterface;
use Behat\Mink\Driver\Selenium2Driver;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use DrevOps\BehatSteps\Extension\Browser\BrowserAdapterBase;
use DrevOps\BehatSteps\Extension\Browser\Capability\ClockCapabilityInterface;
use DrevOps\BehatSteps\Extension\Browser\Capability\CookieCapabilityInterface;
use DrevOps\BehatSteps\Extension\Browser\Capability\JavascriptCapabilityInterface;
use DrevOps\BehatSteps\Extension\Browser\Capability\KeyboardCapabilityInterface;
use WebDriver\Exception\UnknownCommand;
use WebDriver\Session;

/**
 * Capabilities of the Selenium2 browser driver.
 *
 * A WebDriver session cannot add request headers, so this adapter declares no
 * request-header capability.
 *
 * The clock capability sends CDP commands through the vendor endpoint of
 * chromedriver, so it works only when the session drives a Chromium browser.
 */
class Selenium2Adapter extends BrowserAdapterBase implements ClockCapabilityInterface, CookieCapabilityInterface, JavascriptCapabilityInterface, KeyboardCapabilityInterface {

  use DevToolsClockTrait;

  /**
   * Browser driver methods this adapter reaches through reflection.
   *
   * The browser driver exposes no public equivalent.
   */
  public const array SYN_METHODS = ['withSyn', 'executeJsOnXpath'];

  /**
   * WebDriver session method this adapter reaches through reflection.
   *
   * The session sends only the commands it names, and exposes its generic
   * request method as protected.
   */
  public const string SESSION_REQUEST_METHOD = 'curl';

  /**
   * Chromedriver endpoint that runs a CDP command, relative to the session.
   */
  public const string DEVTOOLS_ENDPOINT = '/goog/cdp/execute';

  /**
   * {@inheritdoc}
   */
  public static function supports(DriverInterface $driver): bool {
    return $driver instanceof Selenium2Driver;
  }

  /**
   * {@inheritdoc}
   */
  public function cookieGetAll(): array {
    /** @var \Behat\Mink\Driver\Selenium2Driver $driver */
    $driver = $this->driver;
    $cookies = [];

    foreach ($driver->getWebDriverSession()->getAllCookies() as $cookie) {
      $cookies[] = [
        'name' => (string) $cookie['name'],
        'value' => (string) $cookie['value'],
      ];
    }

    return $cookies;
  }

  /**
   * {@inheritdoc}
   *
   * Selenium dispatches synthetic events through the bundled Syn library,
   * which the browser driver loads through methods it does not expose
   * publicly.
   */
  public function keyboardTriggerKey(string $xpath, string $key): void {
    $reflection = new \ReflectionClass($this->driver);
    [$with_syn, $execute_js_on_xpath] = self::SYN_METHODS;

    $driver_with_syn = $reflection->getMethod($with_syn)->invoke($this->driver);

    // The key is embedded in the script as an encoded literal, so Syn
    // receives a quote, a backslash or a control character unchanged.
    $reflection->getMethod($execute_js_on_xpath)->invokeArgs($driver_with_syn, [
      $xpath,
      sprintf('syn.key({{ELEMENT}}, %s);', json_encode($key)),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function devToolsConnection(): Session {
    /** @var \Behat\Mink\Driver\Selenium2Driver $driver */
    $driver = $this->driver;

    return $driver->getWebDriverSession();
  }

  /**
   * {@inheritdoc}
   */
  protected function devToolsSend(string $method, array $params): array {
    $session = $this->devToolsConnection();
    $payload = ['cmd' => $method, 'params' => $params];

    try {
      $response = (new \ReflectionMethod($session, self::SESSION_REQUEST_METHOD))->invoke($session, 'POST', self::DEVTOOLS_ENDPOINT, $payload);
    }
    catch (UnknownCommand $exception) {
      throw new UnsupportedDriverActionException(sprintf('The browser behind %%s does not accept the Chrome DevTools Protocol command "%s".', $method), $this->driver, $exception);
    }

    $value = is_array($response) ? ($response['value'] ?? NULL) : NULL;

    return is_array($value) ? $value : [];
  }

}
