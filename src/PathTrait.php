<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

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
   *
   * @Then the path should be :path
   */
  public function pathAssertCurrent(string $path): void {
    $current_path = $this->getSession()->getCurrentUrl();

    if (empty($current_path)) {
      throw new \Exception('Current path is empty');
    }

    $current_path = parse_url((string) $current_path, PHP_URL_PATH);

    if ($current_path === FALSE) {
      throw new \Exception('Current path is not a valid URL');
    }

    $normalized_current_path = ($current_path === '' || $current_path === '/') ? '<front>' : $current_path;
    $normalized_path = ($path === '/' || $path === '<front>') ? '<front>' : $path;

    if (ltrim((string) $normalized_current_path, '/') !== ltrim($normalized_path, '/')) {
      throw new \Exception(sprintf('Current path is "%s", but expected is "%s"', $current_path, $path));
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
   *
   * @Then the path should not be :path
   */
  public function pathAssertNotCurrent(string $path): bool {
    $current_path = $this->getSession()->getCurrentUrl();

    if (empty($current_path)) {
      throw new \Exception('Current path is empty');
    }

    $current_path = parse_url((string) $current_path, PHP_URL_PATH);

    if ($current_path === FALSE) {
      throw new \Exception('Current path is not a valid URL');
    }

    $normalized_current_path = ($current_path === '' || $current_path === '/') ? '<front>' : $current_path;
    $normalized_path = ($path === '/' || $path === '<front>') ? '<front>' : $path;

    if (ltrim((string) $normalized_current_path, '/') === ltrim($normalized_path, '/')) {
      throw new \Exception(sprintf('Current path should not be "%s"', $path));
    }

    return TRUE;
  }

  /**
   * Assert that current URL has a query parameter.
   *
   * @code
   * Then current url should have the "filter" parameter
   * @endcode
   *
   * @Then current url should have the :param parameter
   */
  public function pathAssertUrlHasParameter(string $param): void {
    $url = $this->getSession()->getCurrentUrl();

    $url_query = parse_url((string) $url, PHP_URL_QUERY);
    $url_query = $url_query === FALSE ? '' : (string) $url_query;

    $q = [];
    parse_str($url_query, $q);

    if (empty($q[$param])) {
      throw new \Exception(sprintf('The param "%s" is not in the URL', $param));
    }
  }

  /**
   * Assert that current URL has a query parameter with a specific value.
   *
   * @code
   * Then current url should have the "filter" parameter with the "recent" value
   * @endcode
   *
   * @Then current url should have the :param parameter with the :value value
   */
  public function pathAssertUrlHasParameterWithValue(string $param, string $value): void {
    $this->pathAssertUrlHasParameter($param);

    $url = $this->getSession()->getCurrentUrl();

    $url_query = parse_url((string) $url, PHP_URL_QUERY);
    $url_query = $url_query === FALSE ? '' : (string) $url_query;

    $q = [];
    parse_str($url_query, $q);

    $actual_value = $q[$param] ?? '';

    if ($actual_value !== $value) {
      throw new \Exception(sprintf('The param "%s" is in the URL but with the wrong value "%s"', $param, is_array($actual_value) ? json_encode($actual_value) : $actual_value));
    }
  }

  /**
   * Assert that current URL doesn't have a query parameter with specific value.
   *
   * @code
   * Then current url should not have the "filter" parameter
   * @endcode
   *
   * @Then current url should not have the :param parameter
   */
  public function pathAssertUrlHasNoParameter(string $param): void {
    $url = $this->getSession()->getCurrentUrl();

    $url_query = parse_url((string) $url, PHP_URL_QUERY);
    $url_query = $url_query === FALSE ? '' : (string) $url_query;

    $q = [];
    parse_str($url_query, $q);

    if (!empty($q[$param])) {
      throw new \Exception(sprintf('The param "%s" is in the URL but should not be', $param));
    }
  }

  /**
   * Assert that current URL doesn't have a query parameter with specific value.
   *
   * @code
   * Then current url should not have the "filter" parameter with the "recent" value
   * @endcode
   *
   * @Then current url should not have the :param parameter with the :value value
   */
  public function pathAssertUrlHasNoParameterWithValue(string $param, string $value): void {
    $url = $this->getSession()->getCurrentUrl();

    $url_query = parse_url((string) $url, PHP_URL_QUERY);
    $url_query = $url_query === FALSE ? '' : (string) $url_query;

    $q = [];
    parse_str($url_query, $q);

    if (empty($q[$param])) {
      return;
    }

    if ($q[$param] === $value) {
      throw new \Exception(sprintf('The param "%s" with value "%s" is in the URL but should not be', $param, $value));
    }
  }

  /**
   * Set basic authentication for the current session.
   *
   * @code
   * Given the basic authentication with the username "myusername" and the password "mypassword"
   * @endcode
   *
   * @Given the basic authentication with the username :username and the password :password
   */
  public function pathSetBasicAuth(string $username, string $password): void {
    $this->getSession()->setBasicAuth($username, $password);
  }

}
