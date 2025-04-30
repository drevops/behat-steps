<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

/**
 * Trait CookieTrait.
 *
 * Cookie-related steps.
 *
 * @package DrevOps\BehatSteps
 */
trait CookieTrait {

  /**
   * Check if a cookie exists.
   *
   * @code
   * Then a cookie with the name "session_id" should exist
   * @endcode
   *
   * @Then a cookie with the name :name should exist
   */
  public function cookieWithNameShouldExist(string $name): void {
    static::cookieExists($name);
  }

  /**
   * Check if a cookie exists with a specific value.
   *
   * @code
   * Then a cookie with the name "language" and the value "en" should exist
   * @endcode
   *
   * @Then a cookie with the name :name and the value :value should exist
   */
  public function cookieWithNameValueShouldExist(string $name, string $value): void {
    static::cookieExists($name, $value);
  }

  /**
   * Check if a cookie exists with a value containing a partial value.
   *
   * @code
   * Then a cookie with the name "preferences" and a value containing "darkmode" should exist
   * @endcode
   *
   * @Then a cookie with the name :name and a value containing :partial_value should exist
   */
  public function cookieWithNamePartialValueShouldExist(string $name, string $partial_value): void {
    static::cookieExists($name, $partial_value, FALSE, TRUE);
  }

  /**
   * Check if a cookie with a partial name exists.
   *
   * @code
   * Then a cookie with a name containing "session" should exist
   * @endcode
   *
   * @Then a cookie with a name containing :partial_name should exist
   */
  public function cookieWithPartialNameShouldExist(string $partial_name): void {
    static::cookieExists($partial_name, NULL, TRUE);
  }

  /**
   * Check if a cookie with a partial name and value exists.
   *
   * @code
   * Then a cookie with a name containing "user" and the value "admin" should exist
   * @endcode
   *
   * @Then a cookie with a name containing :partial_name and the value :value should exist
   */
  public function cookieWithPartialNameValueShouldExist(string $partial_name, string $value): void {
    static::cookieExists($partial_name, $value, TRUE);
  }

  /**
   * Check if a cookie with a partial name and partial value exists.
   *
   * @code
   * Then a cookie with a name containing "user" and a value containing "admin" should exist
   * @endcode
   *
   * @Then a cookie with a name containing :partial_name and a value containing :partial_value should exist
   */
  public function cookieWithPartialNamePartialValueShouldExist(string $partial_name, string $partial_value): void {
    static::cookieExists($partial_name, $partial_value, TRUE, TRUE);
  }

  /**
   * Check if a cookie does not exist.
   *
   * @code
   * Then a cookie with name "old_session" should not exist
   * @endcode
   *
   * @Then a cookie with( the) name :name should not exist
   */
  public function cookieWithNameShouldNotExist(string $name): void {
    static::cookieNotExists($name);
  }

  /**
   * Check if a cookie with a specific value does not exist.
   *
   * @code
   * Then a cookie with the name "language" and the value "fr" should not exist
   * @endcode
   *
   * @Then a cookie with the name :name and the value :value should not exist
   */
  public function cookieWithNameValueShouldNotExist(string $name, string $value): void {
    static::cookieNotExists($name, $value);
  }

  /**
   * Check if a cookie with a value containing a partial value does not exist.
   *
   * @code
   * Then a cookie with the name "preferences" and a value containing "lightmode" should not exist
   * @endcode
   *
   * @Then a cookie with the name :name and a value containing :partial_value should not exist
   */
  public function cookieWithNamePartialValueShouldNotExist(string $name, string $partial_value): void {
    static::cookieNotExists($name, $partial_value, FALSE, TRUE);
  }

  /**
   * Check if a cookie with a partial name does not exist.
   *
   * @code
   * Then a cookie with a name containing "old" should not exist
   * @endcode
   *
   * @Then a cookie with a name containing :partial_name should not exist
   */
  public function cookieWithPartialNameShouldNotExist(string $partial_name): void {
    static::cookieNotExists($partial_name, NULL, TRUE);
  }

  /**
   * Check if a cookie with a partial name and value does not exist.
   *
   * @code
   * Then a cookie with a name containing "user" and the value "guest" should not exist
   * @endcode
   *
   * @Then a cookie with a name containing :partial_name and the value :value should not exist
   */
  public function cookieWithPartialNameValueShouldNotExist(string $partial_name, string $value): void {
    static::cookieNotExists($partial_name, $value, TRUE);
  }

  /**
   * Check if a cookie with a partial name and partial value does not exist.
   *
   * @code
   * Then a cookie with a name containing "user" and a value containing "guest" should not exist
   * @endcode
   *
   * @Then a cookie with a name containing :partial_name and a value containing :partial_value should not exist
   */
  public function cookieWithPartialNamePartialValueShouldNotExist(string $partial_name, string $partial_value): void {
    static::cookieNotExists($partial_name, $partial_value, TRUE, TRUE);
  }

  /**
   * Check if a cookie exists.
   */
  protected function cookieExists(string $name, ?string $value = NULL, bool $is_partial_name = FALSE, bool $is_partial_value = FALSE): void {
    $cookie = $this->cookieGetByName($name, $is_partial_name);

    if ($cookie === NULL) {
      if ($is_partial_name) {
        throw new \Exception(sprintf('The cookie with name containing "%s" was not set', $name));
      }

      throw new \Exception(sprintf('The cookie with name "%s" was not set', $name));
    }

    if ($value !== NULL) {
      if ($is_partial_value) {
        if (!str_contains((string) $cookie['value'], $value)) {
          if ($is_partial_name) {
            throw new \Exception(sprintf('The cookie with name containing "%s" was set with value "%s", but it should contain "%s"', $name, $cookie['value'], $value));
          }
          throw new \Exception(sprintf('The cookie with name "%s" was set with value "%s", but it should contain "%s"', $name, $cookie['value'], $value));
        }
      }
      elseif ($cookie['value'] !== $value) {
        if ($is_partial_name) {
          throw new \Exception(sprintf('The cookie with name containing "%s" was set with value "%s", but it should be "%s"', $name, $cookie['value'], $value));
        }
        throw new \Exception(sprintf('The cookie with name "%s" was set with value "%s", but it should be "%s"', $name, $cookie['value'], $value));
      }
    }
  }

  /**
   * Check if a cookie does not exist.
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
            throw new \Exception(sprintf('The cookie with name containing "%s" was set with value containing "%s", but it should not contain "%s"', $name, $cookie['value'], $value));
          }
          throw new \Exception(sprintf('The cookie with name "%s" was set with value containing "%s", but it should not contain "%s"', $name, $cookie['value'], $value));
        }
      }
      elseif ($cookie['value'] === $value) {
        if ($is_partial_name) {
          throw new \Exception(sprintf('The cookie with name containing "%s" was set with value "%s", but it should not be "%s"', $name, $cookie['value'], $value));
        }
        throw new \Exception(sprintf('The cookie with name "%s" was set with value "%s", but it should not be "%s"', $name, $cookie['value'], $value));
      }
    }
    else {
      if ($is_partial_name) {
        throw new \Exception(sprintf('The cookie with name containing "%s" was set but it should not be', $name));
      }
      throw new \Exception(sprintf('The cookie with name "%s" was set but it should not be', $name));
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
    $cookies = self::cookieGetAll();

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

      return $cookies;
    }

    // BrowserKit-based drivers like GoutteDriver.
    if (method_exists($driver, 'getClient')) {
      /** @var \Symfony\Component\BrowserKit\CookieJar $cookie_jar */
      $cookie_jar = $driver->getClient()->getCookieJar();

      // Use filtered cookies from the Driver's cookie jar and also add more
      // properties.
      /** @var \Symfony\Component\BrowserKit\Cookie[] $cookies_objs */
      $cookies_objs = $cookie_jar->all();
      $cookies_names = array_keys($cookie_jar->allValues($driver->getCurrentUrl()));

      $cookies = [];
      foreach ($cookies_objs as $cookie_obj) {
        if (!in_array($cookie_obj->getName(), $cookies_names)) {
          continue;
        }
        $cookies[] = [
          'name' => $cookie_obj->getName(),
          'value' => $cookie_obj->getValue(),
          'secure' => $cookie_obj->isSecure(),
        ];
      }

      return $cookies;
    }

    // Fallback to parsing headers.
    $cookies = [];
    $headers = $driver->getResponseHeaders();
    foreach ($headers as $header_name => $header_value) {
      if (strtolower((string) $header_name) !== 'set-cookie') {
        continue;
      }

      // Only support parsed cookies from a string header.
      if (is_string($header_value)) {
        $cookies = self::cookieParseHeader($header_value);
      }
      break;
    }

    return $cookies;
  }

  /**
   * Parse a cookie header string.
   *
   * @param string $string
   *   The cookie header string.
   *
   * @return array<int, array<string, mixed>>
   *   An array of cookies.
   */
  protected static function cookieParseHeader(string $string): array {
    $cookies = [];

    $parts = explode(';', $string);
    foreach ($parts as $part) {
      $part = trim($part);
      if (str_contains($part, '=')) {
        $cookie = [];
        [$name, $value] = explode('=', $part, 2);
        $cookie['name'] = $name;
        $cookie['value'] = rawurldecode($value);
        $cookie['secure'] = FALSE;
        $cookies[] = $cookie;
      }
    }

    return $cookies;
  }

}
