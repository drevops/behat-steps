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
   * Assert current page is specified path.
   *
   * Note that "<front>" is supported as path.
   *
   * @code
   * Then I should be in the "/about-us" path
   * Then I should be in the "<front>" path
   * @endcode
   *
   * @Then I should be in the :path path
   */
  public function pathAssertCurrent(string $path): void {
    $current_path = $this->getSession()->getCurrentUrl();
    $current_path = parse_url((string) $current_path, PHP_URL_PATH);
    $current_path = ltrim($current_path, '/');
    $current_path = $current_path === '' ? '<front>' : $current_path;

    if ($current_path !== ltrim($path, '/')) {
      throw new \Exception(sprintf('Current path is "%s", but expected is "%s"', $current_path, $path));
    }
  }

  /**
   * Assert current page is not specified path.
   *
   * Note that "<front>" is supported as path.
   *
   * @code
   * Then I should not be in the "/about-us" path
   * Then I should not be in the "<front>" path
   * @endcode
   *
   * @Then I should not be in the :path path
   */
  public function pathAssertNotCurrent(string $path): bool {
    $current_path = $this->getSession()->getCurrentUrl();
    $current_path = parse_url((string) $current_path, PHP_URL_PATH);
    $current_path = ltrim($current_path, '/');
    $current_path = $current_path === '' ? '<front>' : $current_path;

    if ($current_path === $path) {
      throw new \Exception(sprintf('Current path should not be "%s"', $current_path));
    }

    return TRUE;
  }

  /**
   * Assert that a path can be visited or not with HTTP credentials.
   *
   * @code
   * Then I "can" visit "/about-us" with HTTP credentials "user" "pass"
   * Then I "cannot" visit "/about-us" with HTTP credentials "user" "pass"
   * @endcode
   *
   * @Then I :can visit :path with HTTP credentials :user :pass
   */
  public function pathAssertVisitWithBasicAuth(string $can, string $path, string $user, string $pass): void {
    $this->getSession()->setBasicAuth($user, $pass);
    $this->visitPath($path);

    if ($can === 'can') {
      $this->assertSession()->statusCodeEquals(200);
    }
    else {
      $this->assertSession()->statusCodeNotEquals(200);
    }
  }

  /**
   * Visit a path and assert the final destination.
   *
   * Useful for pages with redirects.
   *
   * @code
   * When I visit "/node/123" then the final URL should be "/about/us"
   * @endcode
   *
   * @When I visit :path then the final URL should be :alias
   */
  public function pathAssertWithRedirect(string $path, string $alias): void {
    $this->getSession()->visit($this->locatePath($path));
    $this->pathAssertCurrent($alias);
  }

}
