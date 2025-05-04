<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Mink\Exception\UnsupportedDriverActionException;

/**
 * Wait for a period of time or for AJAX to finish.
 */
trait WaitTrait {

  /**
   * Wait for a specified number of seconds.
   *
   * @code
   * When I wait for 5 seconds
   * When I wait for 1 second
   * @endcode
   *
   * @When I wait for :seconds second(s)
   */
  public function waitWaitForSeconds(int|string $seconds): void {
    sleep((int) $seconds);
  }

  /**
   * Wait for the AJAX calls to finish.
   *
   * @see \Drupal\FunctionalJavascriptTests\JSWebAssert::assertWaitOnAjaxRequest()
   *
   * @code
   * When I wait for 5 seconds for AJAX to finish
   * When I wait for 1 second for AJAX to finish
   * @endcode
   *
   * @When I wait for :seconds second(s) for AJAX to finish
   */
  public function waitForAjaxToFinish(string|int $seconds): void {
    $seconds = intval($seconds);

    $driver = $this->getSession()->getDriver();

    try {
      $driver->evaluateScript('true');
    }
    catch (UnsupportedDriverActionException) {
      throw new \RuntimeException(sprintf('Method can be used only with JS-capable driver. Driver %s is not JS-capable driver', $driver::class));
    }

    $condition = <<<JS
    (function() {
      function isAjaxing(instance) {
        return instance && instance.ajaxing === true;
      }
      var not_ajaxing = (typeof Drupal === 'undefined' || typeof Drupal.ajax === 'undefined' || typeof Drupal.ajax.instances === 'undefined' || !Drupal.ajax.instances.some(isAjaxing))
      return (
        // Assert no AJAX request is running (via jQuery or Drupal) and no
        // animation is running.
        (typeof jQuery === 'undefined' || (jQuery.active === 0 && jQuery(':animated').length === 0)) &&
        not_ajaxing
      );
    }());
JS;

    $result = $this->getSession()->wait($seconds * 1000, $condition);

    if (!$result) {
      throw new \RuntimeException('Unable to complete an AJAX request.');
    }
  }

}
