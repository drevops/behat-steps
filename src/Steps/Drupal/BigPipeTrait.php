<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Hook\Scope\BeforeStepScope;
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
 * here.
 *
 * A driver that runs no JavaScript never replaces those placeholders and does
 * not follow the `http-equiv=refresh` fallback either, so an authenticated-user
 * assertion silently misses whatever BigPipe deferred. Tag such a scenario
 * `@bigpipe` and the `big_pipe_nojs` cookie is set for it, which makes Drupal
 * render the page in full server-side.
 *
 * Skip processing with tag: `@behat-steps-skip:BigPipeTrait`.
 *
 * Special tags:
 * - `@bigpipe` - render server-side on a driver without JavaScript.
 *
 * Override `bigPipeGetWaitTimeout()` (or set `$bigPipeWaitTimeout`) in your
 * `FeatureContext` to change the maximum wait.
 *
 * @phpstan-require-extends \Behat\MinkExtension\Context\RawMinkContext
 */
trait BigPipeTrait {

  /**
   * Default maximum time to wait for BigPipe placeholders, in milliseconds.
   */
  protected const BIG_PIPE_DEFAULT_WAIT_TIMEOUT = 10000;

  /**
   * Cookie name BigPipe reads to bypass streaming and render server-side.
   *
   * The literal avoids a hard dependency on the big_pipe module; it is the
   * value of 'BigPipeStrategy::NOJS_COOKIE'.
   */
  protected const BIG_PIPE_SERVER_RENDER_COOKIE = 'big_pipe_nojs';

  /**
   * Whether the automatic BigPipe wait is active for the current scenario.
   */
  protected bool $bigPipeAutoWaitEnabled = FALSE;

  /**
   * Whether the scenario asked for server-side rendering.
   */
  protected bool $bigPipeServerRenderEnabled = FALSE;

  /**
   * Whether the driver runs JavaScript, NULL until first probed.
   */
  protected ?bool $bigPipeJavascriptProbe = NULL;

  /**
   * Maximum time to wait for BigPipe placeholders to be replaced, in milliseconds.
   */
  protected int $bigPipeWaitTimeout = self::BIG_PIPE_DEFAULT_WAIT_TIMEOUT;

  /**
   * Resolve whether the automatic BigPipe wait applies to this scenario.
   */
  #[BeforeScenario]
  public function bigPipeBeforeScenario(BeforeScenarioScope $scope): void {
    // Resolved here, not in the BeforeStep hook, because a BeforeStep scope
    // cannot read scenario-level tags.
    $is_javascript = $scope->getFeature()->hasTag('javascript') || $scope->getScenario()->hasTag('javascript');
    $is_skipped = $this->skipTag('BigPipeTrait', $scope);

    $this->bigPipeAutoWaitEnabled = $is_javascript && !$is_skipped;
    $this->bigPipeJavascriptProbe = NULL;

    $this->bigPipeServerRenderEnabled = !$is_skipped
      && ($scope->getFeature()->hasTag('bigpipe') || $scope->getScenario()->hasTag('bigpipe'));

    $this->bigPipeApplyServerRenderCookie();
  }

  /**
   * Keep the state BigPipe needs applied before each step runs.
   *
   * Logging in or out drops session cookies, so the no-JS cookie is re-applied
   * rather than set once for the scenario.
   */
  #[BeforeStep]
  public function bigPipeWaitBeforeStep(BeforeStepScope $scope): void {
    $this->bigPipeApplyServerRenderCookie();

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
   * Set the no-JS cookie when the scenario asked for server-side rendering.
   *
   * A driver that runs JavaScript replaces the placeholders itself, so the
   * cookie is only for the drivers that do not. 'setCookie()' is idempotent,
   * so re-applying it on every step costs nothing.
   */
  protected function bigPipeApplyServerRenderCookie(): void {
    if (!$this->bigPipeServerRenderEnabled) {
      return;
    }

    // The probe runs once a scenario: a driver does not gain or lose script
    // support between steps.
    $this->bigPipeJavascriptProbe ??= $this->bigPipeJavascriptIsSupported();

    if ($this->bigPipeJavascriptProbe === TRUE) {
      return;
    }

    try {
      $this->getSession()->setCookie(self::BIG_PIPE_SERVER_RENDER_COOKIE, 'true');
    }
    // @codeCoverageIgnoreStart
    catch (DriverException) {
      // The session is not ready yet; the next step retries.
    }
    // @codeCoverageIgnoreEnd
  }

  /**
   * Whether the active driver can run JavaScript.
   *
   * An unstarted driver counts as unable, and the probe is retried on the next
   * step once the session has started.
   */
  protected function bigPipeJavascriptIsSupported(): ?bool {
    try {
      $driver = $this->getSession()->getDriver();

      if (!$driver->isStarted()) {
        return NULL;
      }

      $driver->evaluateScript('true');

      return TRUE;
    }
    catch (DriverException) {
      return FALSE;
    }
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
