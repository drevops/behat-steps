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
   * Assert that current URL has a query parameter with specific value.
   *
   * @code
   * Then current url should have the "filter" param with "recent" value
   * @endcode
   *
   * @Then current url should have the :param param with :value value
   */
  public function pathAssertUrlHasParameterWithValue(string $param, string $value): void {
    $this->pathAssertUrlParameterValue($param, $value, TRUE);
  }

  /**
   * Assert that current URL doesn't have a query parameter with specific value.
   *
   * @code
   * Then current url should not have the "filter" param with "recent" value
   * @endcode
   *
   * @Then current url should not have the :param param with :value value
   */
  public function pathAssertUrlNotHasParameterWithValue(string $param, string $value): void {
    $this->pathAssertUrlParameterValue($param, $value, FALSE);
  }

  /**
   * Helper method for URL parameter assertions.
   *
   * @param string $param
   *   The parameter name.
   * @param string $value
   *   The expected parameter value.
   * @param bool $should_have
   *   TRUE if the parameter should have the value, FALSE otherwise.
   */
  protected function pathAssertUrlParameterValue(string $param, string $value, bool $should_have): void {
    $url = $this->getSession()->getCurrentUrl();
    $url_query = parse_url((string) $url, PHP_URL_QUERY) ?? '';
    $url_query = is_string($url_query) ? $url_query : '';
    $queries = [];
    parse_str($url_query, $queries);

    if (!(isset($queries[$param]) && $queries[$param] === $value) && $should_have) {
      throw new \RuntimeException(sprintf('The param "%s" with value "%s" is not in the URL', $param, $value));
    }
    elseif (isset($queries[$param]) && $queries[$param] === $value && !$should_have) {
      throw new \RuntimeException(sprintf('The param "%s" with value "%s" is in the URL but it should not be', $param, $value));
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
