<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Mink\Exception\ExpectationException;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use Behat\Step\Then;

/**
 * Verify and inspect browser cookies.
 *
 * - Assert cookie existence and values with exact or partial matching.
 * - Support both WebDriver and BrowserKit drivers for test compatibility.
 */
trait CookieTrait {

  /**
   * Assert that a cookie exists.
   *
   * @code
   * Then a cookie with the name "session_id" should exist
   * @endcode
   */
  #[Then('a cookie with the name :name should exist')]
  public function cookieAssertWithNameExists(string $name): void {
    $this->cookieExists($name);
  }

  /**
   * Assert that a cookie exists with a specific value.
   *
   * @code
   * Then a cookie with the name "language" and the value "en" should exist
   * @endcode
   */
  #[Then('a cookie with the name :name and the value :value should exist')]
  public function cookieAssertWithNameValueExists(string $name, string $value): void {
    $this->cookieExists($name, $value);
  }

  /**
   * Assert that a cookie exists with a value containing a partial value.
   *
   * @code
   * Then a cookie with the name "preferences" and a value containing "darkmode" should exist
   * @endcode
   */
  #[Then('a cookie with the name :name and a value containing :partial_value should exist')]
  public function cookieAssertWithNamePartialValueExists(string $name, string $partial_value): void {
    $this->cookieExists($name, $partial_value, FALSE, TRUE);
  }

  /**
   * Assert that a cookie with a partial name exists.
   *
   * @code
   * Then a cookie with a name containing "session" should exist
   * @endcode
   */
  #[Then('a cookie with a name containing :partial_name should exist')]
  public function cookieAssertWithPartialNameExists(string $partial_name): void {
    $this->cookieExists($partial_name, NULL, TRUE);
  }

  /**
   * Assert that a cookie with a partial name and value exists.
   *
   * @code
   * Then a cookie with a name containing "user" and the value "admin" should exist
   * @endcode
   */
  #[Then('a cookie with a name containing :partial_name and the value :value should exist')]
  public function cookieAssertWithPartialNameValueExists(string $partial_name, string $value): void {
    $this->cookieExists($partial_name, $value, TRUE);
  }

  /**
   * Assert that a cookie with a partial name and partial value exists.
   *
   * @code
   * Then a cookie with a name containing "user" and a value containing "admin" should exist
   * @endcode
   */
  #[Then('a cookie with a name containing :partial_name and a value containing :partial_value should exist')]
  public function cookieAssertWithPartialNamePartialValueExists(string $partial_name, string $partial_value): void {
    $this->cookieExists($partial_name, $partial_value, TRUE, TRUE);
  }

  /**
   * Assert that a cookie does not exist.
   *
   * @code
   * Then a cookie with the name "old_session" should not exist
   * @endcode
   */
  #[Then('a cookie with the name :name should not exist')]
  public function cookieAssertWithNameNotExists(string $name): void {
    $this->cookieNotExists($name);
  }

  /**
   * Assert that a cookie with a specific value does not exist.
   *
   * @code
   * Then a cookie with the name "language" and the value "fr" should not exist
   * @endcode
   */
  #[Then('a cookie with the name :name and the value :value should not exist')]
  public function cookieAssertWithNameValueNotExists(string $name, string $value): void {
    $this->cookieNotExists($name, $value);
  }

  /**
   * Assert that a cookie with a value containing a partial value does not exist.
   *
   * @code
   * Then a cookie with the name "preferences" and a value containing "lightmode" should not exist
   * @endcode
   */
  #[Then('a cookie with the name :name and a value containing :partial_value should not exist')]
  public function cookieAssertWithNamePartialValueNotExists(string $name, string $partial_value): void {
    $this->cookieNotExists($name, $partial_value, FALSE, TRUE);
  }

  /**
   * Assert that a cookie with a partial name does not exist.
   *
   * @code
   * Then a cookie with a name containing "old" should not exist
   * @endcode
   */
  #[Then('a cookie with a name containing :partial_name should not exist')]
  public function cookieAssertWithPartialNameNotExists(string $partial_name): void {
    $this->cookieNotExists($partial_name, NULL, TRUE);
  }

  /**
   * Assert that a cookie with a partial name and value does not exist.
   *
   * @code
   * Then a cookie with a name containing "user" and the value "guest" should not exist
   * @endcode
   */
  #[Then('a cookie with a name containing :partial_name and the value :value should not exist')]
  public function cookieAssertWithPartialNameValueNotExists(string $partial_name, string $value): void {
    $this->cookieNotExists($partial_name, $value, TRUE);
  }

  /**
   * Assert that a cookie with a partial name and partial value does not exist.
   *
   * @code
   * Then a cookie with a name containing "user" and a value containing "guest" should not exist
   * @endcode
   */
  #[Then('a cookie with a name containing :partial_name and a value containing :partial_value should not exist')]
  public function cookieAssertWithPartialNamePartialValueNotExists(string $partial_name, string $partial_value): void {
    $this->cookieNotExists($partial_name, $partial_value, TRUE, TRUE);
  }

  /**
   * Assert that a cookie exists.
   */
  protected function cookieExists(string $name, ?string $value = NULL, bool $is_partial_name = FALSE, bool $is_partial_value = FALSE): void {
    $cookie = $this->cookieGetByName($name, $is_partial_name);

    if ($cookie === NULL) {
      if ($is_partial_name) {
        throw new ExpectationException(sprintf('The cookie with name containing "%s" was not set.', $name), $this->getSession()->getDriver());
      }

      throw new ExpectationException(sprintf('The cookie with name "%s" was not set.', $name), $this->getSession()->getDriver());
    }

    if ($value !== NULL) {
      if ($is_partial_value) {
        if (!str_contains((string) $cookie['value'], $value)) {
          if ($is_partial_name) {
            throw new ExpectationException(sprintf('The cookie with name containing "%s" was set with value "%s", but it should contain "%s".', $name, $cookie['value'], $value), $this->getSession()->getDriver());
          }
          throw new ExpectationException(sprintf('The cookie with name "%s" was set with value "%s", but it should contain "%s".', $name, $cookie['value'], $value), $this->getSession()->getDriver());
        }
      }
      elseif ($cookie['value'] !== $value) {
        if ($is_partial_name) {
          throw new ExpectationException(sprintf('The cookie with name containing "%s" was set with value "%s", but it should be "%s".', $name, $cookie['value'], $value), $this->getSession()->getDriver());
        }
        throw new ExpectationException(sprintf('The cookie with name "%s" was set with value "%s", but it should be "%s".', $name, $cookie['value'], $value), $this->getSession()->getDriver());
      }
    }
  }

  /**
   * Assert that a cookie does not exist.
   */
  protected function cookieNotExists(string $name, ?string $value = NULL, bool $is_partial_name = FALSE, bool $is_partial_value = FALSE): void {
    $cookie = $this->cookieGetByName($name, $is_partial_name);

    if ($cookie === NULL) {
      return;
    }

    if ($value !== NULL) {
      if ($is_partial_value) {
        if (str_contains((string) $cookie['value'], $value)) {
          if ($is_partial_name) {
            throw new ExpectationException(sprintf('The cookie with name containing "%s" was set with value containing "%s", but it should not contain "%s".', $name, $cookie['value'], $value), $this->getSession()->getDriver());
          }
          throw new ExpectationException(sprintf('The cookie with name "%s" was set with value containing "%s", but it should not contain "%s".', $name, $cookie['value'], $value), $this->getSession()->getDriver());
        }
      }
      elseif ($cookie['value'] === $value) {
        if ($is_partial_name) {
          throw new ExpectationException(sprintf('The cookie with name containing "%s" was set with value "%s", but it should not be "%s".', $name, $cookie['value'], $value), $this->getSession()->getDriver());
        }
        throw new ExpectationException(sprintf('The cookie with name "%s" was set with value "%s", but it should not be "%s".', $name, $cookie['value'], $value), $this->getSession()->getDriver());
      }
    }
    else {
      if ($is_partial_name) {
        throw new ExpectationException(sprintf('The cookie with name containing "%s" was set but it should not be.', $name), $this->getSession()->getDriver());
      }
      throw new ExpectationException(sprintf('The cookie with name "%s" was set but it should not be.', $name), $this->getSession()->getDriver());
    }
  }

  /**
   * Get a cookie by exact or partial name.
   *
   * @param string $name
   *   The name of the cookie.
   * @param bool $is_partial
   *   Whether to search for a partial name.
   *
   * @return array<string, mixed>|null
   *   The cookie or NULL if not found.
   */
  protected function cookieGetByName(string $name, bool $is_partial = FALSE): ?array {
    $cookies = $this->cookieGetAll();

    foreach ($cookies as $cookie) {
      if ($is_partial) {
        if (str_contains((string) $cookie['name'], $name)) {
          return $cookie;
        }
      }
      elseif ($cookie['name'] === $name) {
        return $cookie;
      }
    }

    return NULL;
  }

  /**
   * Get all cookies.
   *
   * @return array<int, array<string, mixed>>
   *   An array of cookies.
   */
  protected function cookieGetAll(): array {
    $driver = $this->getSession()->getDriver();

    // WebDriver-based drivers like Selenium2Driver.
    if (method_exists($driver, 'getWebDriverSession')) {
      $cookies = $driver->getWebDriverSession()->getAllCookies();
      array_walk($cookies, function (array &$cookie): void {
        $cookie['value'] = rawurldecode((string) $cookie['value']);
      });
    }

    // CDP-based drivers like the Chrome (chrome-mink) driver.
    elseif (method_exists($driver, 'getCookies')) {
      $cookies = [];
      foreach ($driver->getCookies() as $cookie) {
        $cookies[] = [
          'name' => $cookie['name'],
          'value' => rawurldecode((string) $cookie['value']),
          'secure' => $cookie['secure'],
        ];
      }
    }

    // BrowserKit-based drivers like GoutteDriver.
    elseif (method_exists($driver, 'getClient')) {
      /** @var \Symfony\Component\BrowserKit\CookieJar $cookie_jar */
      $cookie_jar = $driver->getClient()->getCookieJar();

      // The allValues() list is filtered for the current URL but holds only
      // name/value pairs; the full cookie objects supply the remaining
      // properties.
      /** @var \Symfony\Component\BrowserKit\Cookie[] $cookie_objects */
      $cookie_objects = $cookie_jar->all();
      $cookie_names = array_keys($cookie_jar->allValues($driver->getCurrentUrl()));

      $cookies = [];
      foreach ($cookie_objects as $cookie_object) {
        if (!in_array($cookie_object->getName(), $cookie_names)) {
          // @codeCoverageIgnoreStart
          continue;
          // @codeCoverageIgnoreEnd
        }

        $cookies[] = [
          'name' => $cookie_object->getName(),
          'value' => $cookie_object->getValue(),
          'secure' => $cookie_object->isSecure(),
        ];
      }
    }
    else {
      // @codeCoverageIgnoreStart
      throw new UnsupportedDriverActionException('Cookie retrieval is not supported by %s', $driver);
      // @codeCoverageIgnoreEnd
    }

    return $cookies;
  }

}
