<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Web;

use DrevOps\BehatSteps\Attribute\Steps;
use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Hook\Scope\BeforeStepScope;
use Behat\Behat\Hook\Scope\StepScope;
use Behat\Hook\AfterStep;
use Behat\Hook\BeforeScenario;
use Behat\Hook\BeforeStep;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use Behat\Step\When;
use DrevOps\BehatSteps\Behat\Tag;
use DrevOps\BehatSteps\Helper\JavascriptSupportTrait;

/**
 * Wait for a period of time or for AJAX to finish.
 *
 * - Wait a fixed number of seconds.
 * - Wait for jQuery and Drupal AJAX activity to settle, on demand or around
 *   every step that navigates or submits.
 *
 * Mink's own AJAX wait watches `jQuery.active` alone, while Drupal renders many
 * updates through `Drupal.ajax`. An assertion following a click can read the
 * page before the update applies, so the wait here watches both.
 *
 * Skip the automatic waits with tag: `@behat-steps-skip:WaitTrait`.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
 */
#[Steps]
trait WaitTrait {

  use JavascriptSupportTrait;

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
      && in_array('javascript', Tag::all($scope), TRUE);
  }

  /**
   * Wait for AJAX before a step that navigates or submits.
   *
   * The after-step wait fires only on steps matching the same pattern. AJAX
   * from a non-matching step, such as a select or keystroke bound to a Drupal
   * behaviour, is still in flight at the next click. This hook settles it.
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

    if (!$this->isJavascriptSupported()) {
      throw new UnsupportedDriverActionException('Method can be used only with JS-capable driver. Driver %s is not JS-capable driver.', $this->getSession()->getDriver());
    }

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

    $this->waitForAjax($this->waitGetAjaxTimeout());
  }

  /**
   * Return the configured AJAX timeout, in seconds.
   */
  public function waitGetAjaxTimeout(): int {
    return (int) $this->getOption('wait', 'ajax_timeout');
  }

  /**
   * Declares the options this trait reads.
   *
   * @return array<string, array<string, mixed>>
   *   Option declarations keyed by option name.
   */
  protected function waitConfigSchema(): array {
    return [
      'enabled' => [
        'default' => TRUE,
        'description' => 'Wait for AJAX around every navigating or submitting step of a `@javascript` scenario.',
      ],
      'ajax_timeout' => [
        'default' => 5,
        'description' => 'Maximum time, in seconds, to wait for AJAX calls to complete.',
      ],
    ];
  }

}
