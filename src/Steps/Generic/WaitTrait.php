<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Generic;

use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Hook\Scope\BeforeStepScope;
use Behat\Behat\Hook\Scope\StepScope;
use Behat\Hook\AfterStep;
use Behat\Hook\BeforeScenario;
use Behat\Hook\BeforeStep;
use Behat\Step\When;

/**
 * Wait for a period of time or for AJAX to finish.
 *
 * - Wait a fixed number of seconds.
 * - Wait for jQuery and Drupal AJAX activity to settle, on demand or around
 *   every step that navigates or submits.
 *
 * Mink's own AJAX wait watches `jQuery.active` alone, while Drupal renders many
 * updates through `Drupal.ajax`, so an assertion following a click can read the
 * page before the update lands. The wait here watches both.
 *
 * Skip the automatic waits with tag: `@behat-steps-skip:WaitTrait`.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\RawContext
 */
trait WaitTrait {

  use HelperTrait;

  /**
   * Step text that changes the page, and so warrants an AJAX wait around it.
   */
  protected const WAIT_STEP_PATTERN = '/\b(follow|press|click|submit|attach)\b/i';

  /**
   * Whether the scenario takes the automatic waits.
   */
  protected bool $waitAroundSteps = FALSE;

  /**
   * Resolve whether this scenario waits for AJAX around every step.
   *
   * Step scopes carry no scenario tags, so the decision is made here and read
   * by the step hooks below.
   */
  #[BeforeScenario]
  public function waitBeforeScenario(BeforeScenarioScope $scope): void {
    $this->waitAroundSteps = !$this->skipTag('WaitTrait', $scope)
      && ($scope->getFeature()->hasTag('javascript') || $scope->getScenario()->hasTag('javascript'));
  }

  /**
   * Wait for AJAX before a step that navigates or submits.
   */
  #[BeforeStep]
  public function waitBeforeStep(BeforeStepScope $scope): void {
    $this->waitAroundStep($scope);
  }

  /**
   * Wait for AJAX after a step that navigates or submits.
   */
  #[AfterStep]
  public function waitAfterStep(AfterStepScope $scope): void {
    $this->waitAroundStep($scope);
  }

  /**
   * Wait for the AJAX calls to finish, using the configured timeout.
   *
   * @code
   * When I wait for AJAX to finish
   * @endcode
   *
   * @javascript
   */
  #[When('I wait for AJAX to finish')]
  public function waitForAjaxDefault(): void {
    $this->waitForAjax($this->waitGetAjaxTimeout());
  }

  /**
   * Wait for a specified number of seconds.
   *
   * @code
   * When I wait for 5 seconds
   * When I wait for 1 second
   * @endcode
   */
  #[When('I wait for :seconds second(s)')]
  public function waitSeconds(string|int $seconds): void {
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
   */
  #[When('I wait for :seconds second(s) for AJAX to finish')]
  public function waitForAjax(string|int $seconds): void {
    $seconds = (int) $seconds;

    if (!$this->helperIsJavascriptSupported()) {
      $driver = $this->getSession()->getDriver();
      throw new \RuntimeException(sprintf('Method can be used only with JS-capable driver. Driver %s is not JS-capable driver.', $driver::class));
    }

    $script = <<<JS
      (function() {
        function isAjaxing(instance) {
          return instance && instance.ajaxing === true;
        }
        var notAjaxing = (typeof Drupal === 'undefined' || typeof Drupal.ajax === 'undefined' || typeof Drupal.ajax.instances === 'undefined' || !Drupal.ajax.instances.some(isAjaxing))
        return (
          // Assert no AJAX request is running (via jQuery or Drupal) and no
          // animation is running.
          (typeof jQuery === 'undefined' || (jQuery.active === 0 && jQuery(':animated').length === 0)) &&
          notAjaxing
        );
      }());
JS;

    $result = $this->getSession()->wait($seconds * 1000, $script);

    if (!$result) {
      throw new \RuntimeException('Unable to complete an AJAX request.');
    }
  }

  /**
   * Wait for AJAX around a step when the step changes the page.
   *
   * @param \Behat\Behat\Hook\Scope\StepScope $scope
   *   The step scope the hook received.
   */
  protected function waitAroundStep(StepScope $scope): void {
    if (!$this->waitAroundSteps || !$this->getSession()->isStarted()) {
      return;
    }

    // Match against the step's own words: the same verbs appear inside
    // arguments, as in 'I fill in "Search" with "Click here"', which names no
    // navigation at all.
    $text = preg_replace('/"[^"]*"/', '', $scope->getStep()->getText());

    if (preg_match(self::WAIT_STEP_PATTERN, (string) $text) !== 1) {
      return;
    }

    $this->waitForAjax($this->waitGetAjaxTimeout());
  }

  /**
   * Return the configured AJAX timeout, in seconds.
   */
  protected function waitGetAjaxTimeout(): int {
    $timeout = $this->getParameter('ajax_timeout');

    return is_numeric($timeout) ? (int) $timeout : 5;
  }

}
