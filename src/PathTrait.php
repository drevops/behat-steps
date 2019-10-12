<?php

namespace IntegratedExperts\BehatSteps;

/**
 * Trait PathTrait.
 *
 * @package IntegratedExperts\BehatSteps
 */
trait PathTrait {

  /**
   * @Then I should be in the :path path
   */
  public function pathAssertCurrent($path) {
    $current_path = $this->getSession()->getCurrentUrl();
    $current_path = parse_url($current_path, PHP_URL_PATH);
    $current_path = ltrim($current_path, '/');
    $current_path = $current_path == '' ? '<front>' : $current_path;

    if ($current_path != ltrim($path, '/')) {
      throw new \Exception(sprintf('Current path is "%s", but expected is "%s"', $current_path, $path));
    }
  }

  /**
   * @Then I should not be in the :path path
   */
  public function pathAssertNotCurrent($path) {
    $current_path = $this->getSession()->getCurrentUrl();
    $current_path = parse_url($current_path, PHP_URL_PATH);
    $current_path = ltrim($current_path, '/');

    if ($current_path == $path) {
      throw new \Exception(sprintf('Current path should not be "%s"', $current_path));
    }

    return TRUE;
  }

  /**
   * @Then I :can visit :path with HTTP credentials :user :pass
   */
  public function pathAssertVisitWithBasicAuth($can, $path, $user, $pass) {
    $this->getSession()->setBasicAuth($user, $pass);
    $this->visitPath($path);

    if ($can == 'can') {
      $this->assertSession()->statusCodeEquals(200);
    }
    else {
      $this->assertSession()->statusCodeNotEquals(200);
    }
  }

  /**
   * @When I visit :path then the final URL should be :alias
   */
  public function pathAssertWithRedirect($path, $alias) {
    $this->getSession()->visit($this->locatePath($path));
    $this->pathAssertCurrent($alias);
  }

}
