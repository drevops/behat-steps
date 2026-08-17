<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Mink\Exception\ExpectationException;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;

/**
 * Navigate and verify paths with URL validation.
 *
 * - Assert current page location with front page special handling.
 * - Configure basic authentication for protected path access.
 * - Validate URL query parameters with expected values.
 */
trait PathTrait {

  /**
   * Assert that the current page is a specified path.
   *
   * Note that "<front>" is supported as path.
   *
   * @code
   * Then the path should be "/about-us"
   * Then the path should be "/"
   * Then the path should be "<front>"
   * @endcode
   */
  #[Then('the path should be :path')]
  public function pathAssertCurrent(string $path): void {
    $current_path = $this->getSession()->getCurrentUrl();

    // @codeCoverageIgnoreStart
    if (empty($current_path)) {
      throw new ExpectationException('Current path is empty', $this->getSession()->getDriver());
    }
    // @codeCoverageIgnoreEnd
    $current_path = parse_url((string) $current_path, PHP_URL_PATH);

    // @codeCoverageIgnoreStart
    if ($current_path === FALSE) {
      throw new ExpectationException('Current path is not a valid URL', $this->getSession()->getDriver());
    }
    // @codeCoverageIgnoreEnd
    $normalized_current_path = ($current_path === '' || $current_path === '/') ? '<front>' : $current_path;
    $normalized_path = ($path === '/' || $path === '<front>') ? '<front>' : $path;

    if (ltrim((string) $normalized_current_path, '/') !== ltrim($normalized_path, '/')) {
      throw new ExpectationException(sprintf('Current path is "%s", but expected is "%s"', $current_path, $path), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that the current page is not a specified path.
   *
   * Note that "<front>" is supported as path.
   *
   * @code
   * Then the path should not be "/about-us"
   * Then the path should not be "/"
   * Then the path should not be "<front>"
   * @endcode
   */
  #[Then('the path should not be :path')]
  public function pathAssertNotCurrent(string $path): bool {
    $current_path = $this->getSession()->getCurrentUrl();

    // @codeCoverageIgnoreStart
    if (empty($current_path)) {
      throw new ExpectationException('Current path is empty', $this->getSession()->getDriver());
    }
    // @codeCoverageIgnoreEnd
    $current_path = parse_url((string) $current_path, PHP_URL_PATH);

    // @codeCoverageIgnoreStart
    if ($current_path === FALSE) {
      throw new ExpectationException('Current path is not a valid URL', $this->getSession()->getDriver());
    }
    // @codeCoverageIgnoreEnd
    $normalized_current_path = ($current_path === '' || $current_path === '/') ? '<front>' : $current_path;
    $normalized_path = ($path === '/' || $path === '<front>') ? '<front>' : $path;

    if (ltrim((string) $normalized_current_path, '/') === ltrim($normalized_path, '/')) {
      throw new ExpectationException(sprintf('Current path should not be "%s"', $path), $this->getSession()->getDriver());
    }

    return TRUE;
  }

  /**
   * Assert that current URL has a query parameter.
   *
   * @code
   * Then current url should have the "filter" parameter
   * @endcode
   */
  #[Then('current url should have the :param parameter')]
  public function pathAssertUrlHasParameter(string $param): void {
    $query = $this->pathGetCurrentUrlQuery();

    if (empty($query[$param])) {
      throw new ExpectationException(sprintf('The param "%s" is not in the URL', $param), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that current URL has a query parameter with a specific value.
   *
   * @code
   * Then current url should have the "filter" parameter with the "recent" value
   * @endcode
   */
  #[Then('current url should have the :param parameter with the :value value')]
  public function pathAssertUrlHasParameterWithValue(string $param, string $value): void {
    $this->pathAssertUrlHasParameter($param);

    $query = $this->pathGetCurrentUrlQuery();

    $actual_value = $query[$param] ?? '';

    if ($actual_value !== $value) {
      throw new ExpectationException(sprintf('The param "%s" is in the URL but with the wrong value "%s"', $param, is_array($actual_value) ? json_encode($actual_value) : $actual_value), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that current URL doesn't have a query parameter with specific value.
   *
   * @code
   * Then current url should not have the "filter" parameter
   * @endcode
   */
  #[Then('current url should not have the :param parameter')]
  public function pathAssertUrlHasNoParameter(string $param): void {
    $query = $this->pathGetCurrentUrlQuery();

    if (!empty($query[$param])) {
      throw new ExpectationException(sprintf('The param "%s" is in the URL but should not be', $param), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that current URL doesn't have a query parameter with specific value.
   *
   * @code
   * Then current url should not have the "filter" parameter with the "recent" value
   * @endcode
   */
  #[Then('current url should not have the :param parameter with the :value value')]
  public function pathAssertUrlHasNoParameterWithValue(string $param, string $value): void {
    $query = $this->pathGetCurrentUrlQuery();

    if (empty($query[$param])) {
      return;
    }

    if ($query[$param] === $value) {
      throw new ExpectationException(sprintf('The param "%s" with value "%s" is in the URL but should not be', $param, $value), $this->getSession()->getDriver());
    }
  }

  /**
   * Set basic authentication for the current session.
   *
   * @code
   * Given the basic authentication with the username "myusername" and the password "mypassword"
   * @endcode
   */
  #[Given('the basic authentication with the username :username and the password :password')]
  public function pathSetBasicAuth(string $username, string $password): void {
    $this->getSession()->setBasicAuth($username, $password);
  }

  /**
   * Navigate back in browser history.
   *
   * @code
   * When I go back
   * @endcode
   */
  #[When('I go back')]
  public function pathGoBack(): void {
    $this->getSession()->back();
  }

  /**
   * Get the query parameters of the current URL.
   *
   * @return array<int|string, mixed>
   *   Query parameters keyed by name, empty when the URL carries no query.
   */
  protected function pathGetCurrentUrlQuery(): array {
    $url = $this->getSession()->getCurrentUrl();

    $url_query = parse_url((string) $url, PHP_URL_QUERY);
    $url_query = $url_query === FALSE ? '' : (string) $url_query;

    $query = [];
    parse_str($url_query, $query);

    return $query;
  }

}
