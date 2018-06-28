<?php

namespace IntegratedExperts\BehatSteps;

/**
 * Trait PathTrait.
 *
 * @package IntegratedExperts\BehatSteps
 */
trait PathTrait {

  /**
   * @Then I am in the :path path
   */
  public function pathAssertCurrent($path) {
    $current_path = $this->getSession()->getCurrentUrl();
    $current_path = parse_url($current_path, PHP_URL_PATH);
    $current_path = ltrim($current_path, '/');
    $current_path = $current_path == '' ? '<front>' : $current_path;

    if ($current_path != $path) {
      throw new \Exception(sprintf('Current path is "%s", but expected is "%s"', $current_path, $path));
    }
  }

}
