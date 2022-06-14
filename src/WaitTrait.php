<?php

namespace DrevOps\BehatSteps;

use Behat\Mink\Driver\Selenium2Driver;
use Behat\Mink\Exception\UnsupportedDriverActionException;

/**
 * Trait WaitTrait.
 *
 * Wait for time or other actions on the page.
 *
 * @package DrevOps\BehatSteps
 */
trait WaitTrait {

  /**
   * Wait for a specified number of seconds.
   *
   * @Then /^(?:|I )wait (\d+) second(s?)$/
   */
  public function waitSeconds($seconds) {
    sleep($seconds);
  }

  /**
   * Wait for AJAX to finish.
   *
   * @see \Drupal\FunctionalJavascriptTests\JSWebAssert::assertWaitOnAjaxRequest()
   *
   * @Given I wait :timeout seconds for AJAX to finish
   */
  public function waitForAjaxToFinish($timeout) {
    $driver = $this->getSession()->getDriver();
    if (!($driver->supportsJavascript())) {
      throw new UnsupportedDriverActionException('Method can be used only with Selenium driver', $driver);
    }

    $condition = <<<JS
    (function() {
      function isAjaxing(instance) {
        return instance && instance.ajaxing === true;
      }
      var d7_not_ajaxing = true;
      if (typeof Drupal !== 'undefined' && typeof Drupal.ajax !== 'undefined' && typeof Drupal.ajax.instances === 'undefined') {
        for(var i in Drupal.ajax) { if (isAjaxing(Drupal.ajax[i])) { d7_not_ajaxing = false; } }
      }
      var d8_not_ajaxing = (typeof Drupal === 'undefined' || typeof Drupal.ajax === 'undefined' || typeof Drupal.ajax.instances === 'undefined' || !Drupal.ajax.instances.some(isAjaxing))
      return (
        // Assert no AJAX request is running (via jQuery or Drupal) and no
        // animation is running.
        (typeof jQuery === 'undefined' || (jQuery.active === 0 && jQuery(':animated').length === 0)) &&
        d7_not_ajaxing && d8_not_ajaxing
      );
    }());
JS;

    $result = $this->getSession()->wait($timeout * 1000, $condition);

    if (!$result) {
      throw new \RuntimeException('Unable to complete AJAX request.');
    }
  }

}
