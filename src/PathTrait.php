<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

/**
 * Trait PathTrait.
 *
 * Path-related assertions.
 *
 * @package DrevOps\BehatSteps
 */
trait PathTrait {

  /**
   * Assert that the current page is a specified path.
   *
   * Note that "<front>" is supported as path.
   *
   * @code
   * Then the path should be "/about-us"
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

    $current_path = $current_path === '' ? '<front>' : $current_path;

    if (ltrim((string) $current_path, '/') !== ltrim($path, '/')) {
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

    $current_path = $current_path === '/' ? '<front>' : $current_path;

    if (ltrim((string) $current_path, '/') === ltrim($path, '/')) {
      throw new \Exception(sprintf('Current path should not be "%s"', $current_path));
    }

    return TRUE;
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
