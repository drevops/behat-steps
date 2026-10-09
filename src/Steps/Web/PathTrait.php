<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Web;

use Behat\Mink\Exception\ExpectationException;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use DrevOps\BehatSteps\Helper\Web\RequestHeadersTrait;

/**
 * Navigate and verify paths with URL validation.
 *
 * - Assert current page location with front page special handling.
 * - Configure basic authentication for protected path access.
 * - Validate URL query parameters with expected values.
 *
 * @phpstan-require-extends \Behat\MinkExtension\Context\RawMinkContext
 */
trait PathTrait {

  use RequestHeadersTrait;

  /**
   * Set basic authentication for the current session.
   *
   * @code
   * Given the basic authentication has the username "myusername" and the password "mypassword"
   * @endcode
   */
  #[Given('the basic authentication has the username :username and the password :password')]
  public function pathSetBasicAuth(string $username, string $password): void {
    $this->getSession()->setBasicAuth($username, $password);

    // The browser driver does not expose the credentials, so requests the
    // library sends outside the session read them from `$requestHeaders`.
    $this->requestHeadersSet('Authorization', 'Basic ' . base64_encode($username . ':' . $password));
  }

  /**
   * Navigate to a path.
   *
   * The path is resolved against the configured base URL, so both a relative
   * path and an absolute URL work.
   *
   * @code
   * When I visit "/about-us"
   * When I visit "https://example.com/about-us"
   * @endcode
   */
  #[When('I visit :path')]
  public function pathVisit(string $path): void {
    $this->getSession()->visit($this->locatePath($path));
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
    if (!$this->pathIsCurrent($path)) {
      throw new ExpectationException(sprintf('The current path is "%s", but it should be "%s".', $this->pathGetCurrent(), $path), $this->getSession()->getDriver());
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
  public function pathAssertNotCurrent(string $path): void {
    if ($this->pathIsCurrent($path)) {
      throw new ExpectationException(sprintf('The current path should not be "%s", but it is.', $path), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that current URL has a query parameter.
   *
   * @code
   * Then the current URL should have the query parameter "filter"
   * @endcode
   */
  #[Then('the current URL should have the query parameter :name')]
  public function pathAssertUrlParameterExists(string $name): void {
    $this->pathGetUrlParameter($name);
  }

  /**
   * Assert that current URL has a query parameter with a specific value.
   *
   * @code
   * Then the current URL should have the query parameter "filter" with the value "recent"
   * @endcode
   */
  #[Then('the current URL should have the query parameter :name with the value :value')]
  public function pathAssertUrlParameterEquals(string $name, string $value): void {
    $actual_value = $this->pathGetUrlParameter($name);

    if ($actual_value !== $value) {
      throw new ExpectationException(sprintf('The parameter "%s" is in the URL but with the wrong value "%s".', $name, is_array($actual_value) ? json_encode($actual_value) : $actual_value), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that current URL has no query parameter.
   *
   * @code
   * Then the current URL should not have the query parameter "filter"
   * @endcode
   */
  #[Then('the current URL should not have the query parameter :name')]
  public function pathAssertUrlParameterNotExists(string $name): void {
    $query = $this->pathGetCurrentUrlQuery();

    if (array_key_exists($name, $query)) {
      throw new ExpectationException(sprintf('The parameter "%s" is in the URL, but it should not be.', $name), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that current URL does not have a query parameter with a value.
   *
   * An absent parameter satisfies the assertion.
   *
   * @code
   * Then the current URL should not have the query parameter "filter" with the value "recent"
   * @endcode
   */
  #[Then('the current URL should not have the query parameter :name with the value :value')]
  public function pathAssertUrlParameterNotEquals(string $name, string $value): void {
    $query = $this->pathGetCurrentUrlQuery();

    if (!array_key_exists($name, $query)) {
      return;
    }

    if ($query[$name] === $value) {
      throw new ExpectationException(sprintf('The parameter "%s" with value "%s" is in the URL, but it should not be.', $name, $value), $this->getSession()->getDriver());
    }
  }

  /**
   * Get the path of the current URL.
   *
   * @return string
   *   The path, or an empty string when the URL carries none.
   *
   * @throws \RuntimeException
   *   When the session holds no URL, or the URL cannot be parsed.
   */
  public function pathGetCurrent(): string {
    $current_url = $this->getSession()->getCurrentUrl();

    // @codeCoverageIgnoreStart
    if ($current_url === '') {
      throw new \RuntimeException('Current path is empty.');
    }

    // @codeCoverageIgnoreEnd
    $current_path = parse_url($current_url, PHP_URL_PATH);

    // @codeCoverageIgnoreStart
    if ($current_path === FALSE) {
      throw new \RuntimeException('Current path is not a valid URL.');
    }

    // @codeCoverageIgnoreEnd
    return (string) $current_path;
  }

  /**
   * Check whether the current URL is at a path.
   *
   * "<front>" and "/" both name the front page, and a leading slash is
   * optional on either side.
   *
   * @param string $path
   *   The path to compare the current URL's path against.
   *
   * @return bool
   *   TRUE when the current URL is at the path.
   */
  public function pathIsCurrent(string $path): bool {
    $current_path = $this->pathGetCurrent();

    $normalized_current_path = ($current_path === '' || $current_path === '/') ? '<front>' : $current_path;
    $normalized_path = ($path === '/' || $path === '<front>') ? '<front>' : $path;

    return ltrim($normalized_current_path, '/') === ltrim($normalized_path, '/');
  }

  /**
   * Get a query parameter of the current URL.
   *
   * @param string $name
   *   The name of the parameter.
   *
   * @return string|array<int|string, mixed>
   *   The value of the parameter, an array for a name written with brackets.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   When the current URL does not carry the parameter.
   */
  public function pathGetUrlParameter(string $name): string|array {
    $query = $this->pathGetCurrentUrlQuery();

    if (!array_key_exists($name, $query)) {
      throw new ExpectationException(sprintf('The parameter "%s" is not in the URL.', $name), $this->getSession()->getDriver());
    }

    return $query[$name];
  }

  /**
   * Get the query parameters of the current URL.
   *
   * @return array<int|string, mixed>
   *   Query parameters keyed by name, empty when the URL carries no query.
   */
  public function pathGetCurrentUrlQuery(): array {
    $url = $this->getSession()->getCurrentUrl();

    $url_query = parse_url((string) $url, PHP_URL_QUERY);
    $url_query = $url_query === FALSE ? '' : (string) $url_query;

    $query = [];
    parse_str($url_query, $query);

    return $query;
  }

}
