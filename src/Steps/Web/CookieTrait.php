<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Web;

use Behat\Mink\Exception\ExpectationException;
use Behat\Step\Then;
use DrevOps\BehatSteps\Behat\Mink\Capability\CookieCapabilityInterface;

/**
 * Verify and inspect browser cookies.
 *
 * - Assert cookie existence and values with exact or partial matching.
 * - Support both WebDriver and BrowserKit browser drivers for test
 *   compatibility.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
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
  public function cookieAssertExistsWithName(string $name): void {
    $this->cookieAssertExists($name);
  }

  /**
   * Assert that a cookie exists with a specific value.
   *
   * @code
   * Then a cookie with the name "language" and the value "en" should exist
   * @endcode
   */
  #[Then('a cookie with the name :name and the value :value should exist')]
  public function cookieAssertExistsWithNameValue(string $name, string $value): void {
    $this->cookieAssertExists($name, $value);
  }

  /**
   * Assert that a cookie exists with a value containing a partial value.
   *
   * @code
   * Then a cookie with the name "preferences" and a value containing "darkmode" should exist
   * @endcode
   */
  #[Then('a cookie with the name :name and a value containing :partial_value should exist')]
  public function cookieAssertExistsWithNamePartialValue(string $name, string $partial_value): void {
    $this->cookieAssertExists($name, $partial_value, FALSE, TRUE);
  }

  /**
   * Assert that a cookie with a partial name exists.
   *
   * @code
   * Then a cookie with a name containing "session" should exist
   * @endcode
   */
  #[Then('a cookie with a name containing :partial_name should exist')]
  public function cookieAssertExistsWithPartialName(string $partial_name): void {
    $this->cookieAssertExists($partial_name, NULL, TRUE);
  }

  /**
   * Assert that a cookie with a partial name and value exists.
   *
   * @code
   * Then a cookie with a name containing "user" and the value "admin" should exist
   * @endcode
   */
  #[Then('a cookie with a name containing :partial_name and the value :value should exist')]
  public function cookieAssertExistsWithPartialNameValue(string $partial_name, string $value): void {
    $this->cookieAssertExists($partial_name, $value, TRUE);
  }

  /**
   * Assert that a cookie with a partial name and partial value exists.
   *
   * @code
   * Then a cookie with a name containing "user" and a value containing "admin" should exist
   * @endcode
   */
  #[Then('a cookie with a name containing :partial_name and a value containing :partial_value should exist')]
  public function cookieAssertExistsWithPartialNamePartialValue(string $partial_name, string $partial_value): void {
    $this->cookieAssertExists($partial_name, $partial_value, TRUE, TRUE);
  }

  /**
   * Assert that a cookie does not exist.
   *
   * @code
   * Then a cookie with the name "old_session" should not exist
   * @endcode
   */
  #[Then('a cookie with the name :name should not exist')]
  public function cookieAssertNotExistsWithName(string $name): void {
    $this->cookieAssertNotExists($name);
  }

  /**
   * Assert that a cookie with a specific value does not exist.
   *
   * @code
   * Then a cookie with the name "language" and the value "fr" should not exist
   * @endcode
   */
  #[Then('a cookie with the name :name and the value :value should not exist')]
  public function cookieAssertNotExistsWithNameValue(string $name, string $value): void {
    $this->cookieAssertNotExists($name, $value);
  }

  /**
   * Assert that a cookie with a value containing a partial value does not exist.
   *
   * @code
   * Then a cookie with the name "preferences" and a value containing "lightmode" should not exist
   * @endcode
   */
  #[Then('a cookie with the name :name and a value containing :partial_value should not exist')]
  public function cookieAssertNotExistsWithNamePartialValue(string $name, string $partial_value): void {
    $this->cookieAssertNotExists($name, $partial_value, FALSE, TRUE);
  }

  /**
   * Assert that a cookie with a partial name does not exist.
   *
   * @code
   * Then a cookie with a name containing "old" should not exist
   * @endcode
   */
  #[Then('a cookie with a name containing :partial_name should not exist')]
  public function cookieAssertNotExistsWithPartialName(string $partial_name): void {
    $this->cookieAssertNotExists($partial_name, NULL, TRUE);
  }

  /**
   * Assert that a cookie with a partial name and value does not exist.
   *
   * @code
   * Then a cookie with a name containing "user" and the value "guest" should not exist
   * @endcode
   */
  #[Then('a cookie with a name containing :partial_name and the value :value should not exist')]
  public function cookieAssertNotExistsWithPartialNameValue(string $partial_name, string $value): void {
    $this->cookieAssertNotExists($partial_name, $value, TRUE);
  }

  /**
   * Assert that a cookie with a partial name and partial value does not exist.
   *
   * @code
   * Then a cookie with a name containing "user" and a value containing "guest" should not exist
   * @endcode
   */
  #[Then('a cookie with a name containing :partial_name and a value containing :partial_value should not exist')]
  public function cookieAssertNotExistsWithPartialNamePartialValue(string $partial_name, string $partial_value): void {
    $this->cookieAssertNotExists($partial_name, $partial_value, TRUE, TRUE);
  }

  /**
   * Assert that a cookie exists.
   */
  public function cookieAssertExists(string $name, ?string $value = NULL, bool $is_partial_name = FALSE, bool $is_partial_value = FALSE): void {
    $cookie = $this->cookieFindByName($name, $is_partial_name);

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
  public function cookieAssertNotExists(string $name, ?string $value = NULL, bool $is_partial_name = FALSE, bool $is_partial_value = FALSE): void {
    $cookie = $this->cookieFindByName($name, $is_partial_name);

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
   * Find a cookie by exact or partial name.
   *
   * @param string $name
   *   The name of the cookie.
   * @param bool $is_partial
   *   Whether to search for a partial name.
   *
   * @return array<string, mixed>|null
   *   The cookie or NULL if not found.
   */
  public function cookieFindByName(string $name, bool $is_partial = FALSE): ?array {
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
  public function cookieGetAll(): array {
    $cookies = $this->browserDriverFor(CookieCapabilityInterface::class)->cookieGetAll();

    // The capability reports wire-form values; an assertion compares against
    // the value a step was written with.
    foreach ($cookies as &$cookie) {
      $cookie['value'] = rawurldecode($cookie['value']);
    }

    return $cookies;
  }

}
