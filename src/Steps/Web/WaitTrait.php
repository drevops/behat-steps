<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Web;

use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Hook\Scope\BeforeStepScope;
use Behat\Behat\Hook\Scope\StepScope;
use Behat\Hook\AfterStep;
use Behat\Hook\BeforeScenario;
use Behat\Hook\BeforeStep;
use Behat\Step\When;
use DrevOps\BehatSteps\Behat\Config\Option;
use DrevOps\BehatSteps\Behat\Mink\Capability\JavascriptCapabilityInterface;
use DrevOps\BehatSteps\Behat\Tag;
use DrevOps\BehatSteps\Helper\Web\StringTrait;

/**
 * Wait for a period of time or for AJAX to finish.
 *
 * - Wait a fixed number of seconds.
 * - Wait for jQuery and Drupal AJAX activity to settle, on demand or around
 *   every step that navigates or submits.
 *
 * A wait on `jQuery.active` alone misses `Drupal.ajax` updates, so an
 * assertion following a click can read the page before the update applies.
 * The wait here watches both.
 *
 * Skip the automatic waits with tag: `@behat-steps-skip:WaitTrait`.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
 */
trait WaitTrait {

  use StringTrait;

  /**
   * Step text that changes the page, and so warrants an AJAX wait around it.
   */
  protected const string WAIT_STEP_PATTERN = '/\b(follow|press|click|submit|attach)\b/i';

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
    $this->waitAroundSteps = !$this->skipTag(__TRAIT__, $scope) && Tag::has($scope, Tag::JAVASCRIPT);
  }

  /**
   * Wait for AJAX before a step that navigates or submits.
   *
   * The after-step wait fires only on steps matching the pattern, so AJAX
   * from a non-matching step is still in flight at the next click. A select
   * or keystroke bound to a Drupal behavior is one such step, so this hook
   * settles the AJAX first.
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
    $this->waitForAjaxWithin($this->waitGetAjaxTimeout());
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
  public function waitSeconds(string $seconds): void {
    sleep($this->stringParseInteger($seconds, 'number of seconds', 0));
  }

  /**
   * Wait for the AJAX calls to finish.
   *
   * @code
   * When I wait for 5 seconds for AJAX to finish
   * When I wait for 1 second for AJAX to finish
   * @endcode
   */
  #[When('I wait for :seconds second(s) for AJAX to finish')]
  public function waitForAjax(string $seconds): void {
    $this->waitForAjaxWithin($this->stringParseInteger($seconds, 'number of seconds', 0));
  }

  /**
   * Wait for the AJAX calls to finish within a number of seconds.
   *
   * @param int $seconds
   *   The longest time to wait, in seconds.
   *
   * @throws \Behat\Mink\Exception\UnsupportedDriverActionException
   *   When the browser driver cannot run JavaScript.
   * @throws \RuntimeException
   *   When the AJAX calls are still running after the time is up.
   *
   * @see \Drupal\FunctionalJavascriptTests\JSWebAssert::assertWaitOnAjaxRequest()
   */
  public function waitForAjaxWithin(int $seconds): void {
    $this->browserDriverFor(JavascriptCapabilityInterface::class);

    $script = <<<JS
      (function() {
        function isAjaxing(instance) {
          return instance && instance.ajaxing === true;
        }
        var notAjaxing = (typeof Drupal === 'undefined' || typeof Drupal.ajax === 'undefined' || typeof Drupal.ajax.instances === 'undefined' || !Drupal.ajax.instances.some(isAjaxing))
        return (
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

    // The same verbs appear inside quoted arguments, as in 'I fill in
    // "Search" with "Click here"', so the arguments are stripped before
    // matching.
    $text = preg_replace('/"[^"]*"/', '', $scope->getStep()->getText());

    if (preg_match(self::WAIT_STEP_PATTERN, (string) $text) !== 1) {
      return;
    }

    $this->waitForAjaxWithin($this->waitGetAjaxTimeout());
  }

  /**
   * Return the configured AJAX timeout, in seconds.
   */
  public function waitGetAjaxTimeout(): int {
    return $this->getOptionInt('wait', 'ajax_timeout');
  }

  /**
   * Declares the options this trait reads.
   *
   * @return array<int, \DrevOps\BehatSteps\Behat\Config\Option>
   *   The options this trait declares.
   */
  protected function waitConfigSchema(): array {
    return [
      new Option('enabled', default: TRUE, description: 'Wait for AJAX around every navigating or submitting step of a `@javascript` scenario.'),
      new Option('ajax_timeout', default: 5, description: 'Maximum time, in seconds, to wait for AJAX calls to complete.'),
    ];
  }

}
