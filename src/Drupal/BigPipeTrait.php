<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Drupal;

use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Hook\BeforeScenario;
use Behat\Hook\BeforeStep;
use Behat\Mink\Exception\DriverException;

/**
 * Wait for Drupal BigPipe placeholders to be replaced on JavaScript scenarios.
 *
 * Drupal BigPipe streams parts of a page in after the initial response and
 * replaces its `<span data-big-pipe-placeholder-id="...">` markers with the
 * real markup using JavaScript. Assertions that run before those replacements
 * land intermittently fail with "element not found". When this trait is
 * included, every `@javascript` scenario waits - before each step - until no
 * BigPipe placeholder markers remain in the DOM, removing that race without an
 * explicit step.
 *
 * The wait is best-effort: on timeout the step still runs, so a genuinely stuck
 * placeholder surfaces as the real assertion failure rather than being masked
 * here. Non-JavaScript scenarios are left untouched, where BigPipe renders
 * server-side (see the drupal-extension `@bigpipe` cookie handling).
 *
 * Skip processing with tag: `@behat-steps-skip:BigPipeTrait`.
 *
 * Override `bigPipeGetWaitTimeout()` (or set `$bigPipeWaitTimeout`) in your
 * `FeatureContext` to change the maximum wait.
 */
trait BigPipeTrait {

  /**
   * Whether the automatic BigPipe wait is active for the current scenario.
   */
  protected bool $bigPipeAutoWaitEnabled = FALSE;

  /**
   * Maximum time to wait for BigPipe placeholders to be replaced, in milliseconds.
   */
  protected int $bigPipeWaitTimeout = 10000;

  /**
   * Resolve whether the automatic BigPipe wait applies to this scenario.
   */
  #[BeforeScenario]
  public function bigPipeBeforeScenario(BeforeScenarioScope $scope): void {
    $scenario = $scope->getScenario();
    $feature = $scope->getFeature();

    // Resolved here, not in the BeforeStep hook, because a BeforeStep scope
    // cannot read scenario-level tags.
    $is_javascript = $feature->hasTag('javascript') || $scenario->hasTag('javascript');
    $is_skipped = $feature->hasTag('behat-steps-skip:BigPipeTrait') || $scenario->hasTag('behat-steps-skip:BigPipeTrait');

    $this->bigPipeAutoWaitEnabled = $is_javascript && !$is_skipped;
  }

  /**
   * Wait for BigPipe placeholders to settle before each step runs.
   *
   * Named to avoid overriding the drupal-extension BigPipeTrait's own
   * `bigPipeBeforeStep()` hook, inherited through `DrupalContext`.
   */
  #[BeforeStep]
  public function bigPipeWaitBeforeStep(): void {
    if (!$this->bigPipeAutoWaitEnabled) {
      return;
    }

    $this->bigPipeWaitForPlaceholders($this->bigPipeGetWaitTimeout());
  }

  /**
   * Wait until no BigPipe placeholder markers remain in the DOM.
   *
   * @param int $timeout_ms
   *   Maximum time to wait, in milliseconds.
   */
  protected function bigPipeWaitForPlaceholders(int $timeout_ms): void {
    try {
      $this->getSession()->wait($timeout_ms, "document.querySelectorAll('[data-big-pipe-placeholder-id]').length === 0");
    }
    // @codeCoverageIgnoreStart
    catch (DriverException) {
      // The driver session is not ready (e.g. no page has been visited yet),
      // so there is nothing to synchronise.
    }
    // @codeCoverageIgnoreEnd
  }

  /**
   * Maximum time to wait for BigPipe placeholders, in milliseconds.
   *
   * @return int
   *   The timeout in milliseconds.
   */
  protected function bigPipeGetWaitTimeout(): int {
    return $this->bigPipeWaitTimeout;
  }

}
