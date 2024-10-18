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
   * @Then a cookie with( the) name :name should exist
   */
  public function cookieWithNameShouldExist($name): void {
    static::cookieExists($name);
  }

  /**
   * Check if a cookie exists with a specific value.
   *
   * @Then a cookie with( the) name :name and value :value should exist
   */
  public function cookieWithNameValueShouldExist($name, $value): void {
    static::cookieExists($name, $value);
  }

  /**
   * Check if a cookie exists with a value containing a partial value.
   *
   * @Then a cookie with( the) name :name and value containing :partial_value should exist
   */
  public function cookieWithNamePartialValueShouldExist($name, $partial_value): void {
    static::cookieExists($name, $partial_value, FALSE, TRUE);
  }

  /**
   * Check if a cookie with a partial name exists.
   *
   * @Then a cookie with( the) name containing :partial_name should exist
   */
  public function cookieWithPartialNameShouldExist($partial_name): void {
    static::cookieExists($partial_name, NULL, TRUE);
  }

  /**
   * Check if a cookie with a partial name and value exists.
   *
   * @Then a cookie with( the) name containing :partial_name and value :value should exist
   */
  public function cookieWithPartialNameValueShouldExist($partial_name, $value): void {
    static::cookieExists($partial_name, $value, TRUE);
  }

  /**
   * Check if a cookie with a partial name and partial value exists.
   *
   * @Then a cookie with( the) name containing :partial_name and value containing :partial_value should exist
   */
  public function cookieWithPartialNamePartialValueShouldExist($partial_name, $partial_value): void {
    static::cookieExists($partial_name, $partial_value, TRUE, TRUE);
  }

  /**
   * Check if a cookie does not exist.
   *
   * @Then a cookie with( the) name :name should not exist
   */
  public function cookieWithNameShouldNotExist($name): void {
    static::cookieNotExists($name);
  }

  /**
   * Check if a cookie with a specific value does not exist.
   *
   * @Then a cookie with( the) name :name and value :value should not exist
   */
  public function cookieWithNameValueShouldNotExist($name, $value): void {
    static::cookieNotExists($name, $value);
  }

  /**
   * Check if a cookie with a value containing a partial value does not exist.
   *
   * @Then a cookie with( the) name :name and value containing :partial_value should not exist
   */
  public function cookieWithNamePartialValueShouldNotExist($name, $partial_value): void {
    static::cookieNotExists($name, $partial_value, FALSE, TRUE);
  }

  /**
   * Check if a cookie with a partial name does not exist.
   *
   * @Then a cookie with( the) name containing :partial_name should not exist
   */
  public function cookieWithPartialNameShouldNotExist($partial_name): void {
    static::cookieNotExists($partial_name, NULL, TRUE);
  }

  /**
   * Check if a cookie with a partial name and value does not exist.
   *
   * @Then a cookie with( the) name containing :partial_name and value :value should not exist
   */
  public function cookieWithPartialNameValueShouldNotExist($partial_name, $value): void {
    static::cookieNotExists($partial_name, $value, TRUE);
  }

  /**
   * Check if a cookie with a partial name and partial value does not exist.
   *
   * @Then a cookie with( the) name containing :partial_name and value containing :partial_value should not exist
   */
  public function cookieWithPartialNamePartialValueShouldNotExist($partial_name, $partial_value): void {
    static::cookieNotExists($partial_name, $partial_value, TRUE, TRUE);
  }

  /**
   * Check if a cookie exists.
   */
  protected function cookieExists($name, $value = NULL, $is_partial_name = FALSE, $is_partial_value = FALSE): void {
    $cookie = $this->cookieGetByName($name, $is_partial_name);

    if ($cookie === NULL) {
      if ($is_partial_name) {
        throw new \Exception(sprintf('The cookie with name containing "%s" was not set', $name));
      }

      throw new \Exception(sprintf('The cookie with name "%s" was not set', $name));
    }

    if ($value !== NULL) {
      if ($is_partial_value) {
        if (!str_contains((string) $cookie['value'], (string) $value)) {
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
  protected function cookieNotExists($name, $value = NULL, $is_partial_name = FALSE, $is_partial_value = FALSE): void {
    $cookie = $this->cookieGetByName($name, $is_partial_name);

    if ($cookie === NULL) {
      return;
    }

    if ($value !== NULL) {
      if ($is_partial_value) {
        if (str_contains((string) $cookie['value'], (string) $value)) {
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
   */
  protected function cookieGetByName($name, $is_partial = FALSE): ?array {
    $cookies = self::cookieGetAll();

    foreach ($cookies as $cookie) {
      if ($is_partial) {
        if (str_contains((string) $cookie['name'], (string) $name)) {
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

      $cookies = self::cookieParseHeader($header_value);
      break;
    }

    return $cookies;
  }

  /**
   * Parse a cookie header string.
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
