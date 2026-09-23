<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Hook\Scope\BeforeStepScope;
use Behat\Hook\BeforeScenario;
use Behat\Hook\BeforeStep;
use Behat\Mink\Exception\DriverException;
use DrevOps\BehatSteps\Behat\Tag;

/**
 * Wait for Drupal BigPipe placeholders to be replaced on JavaScript scenarios.
 *
 * Drupal BigPipe streams parts of a page in after the initial response and
 * replaces its `<span data-big-pipe-placeholder-id="...">` markers with the
 * real markup using JavaScript. An assertion that runs before those
 * replacements complete fails intermittently with "element not found".
 *
 * With this trait included, every `@javascript` scenario waits before each
 * step until no BigPipe placeholder marker remains in the DOM, which removes
 * that race without an explicit step.
 *
 * The wait is best-effort: on timeout the step still runs, so a placeholder
 * that is never replaced fails the following assertion rather than the wait.
 *
 * A driver that runs no JavaScript never replaces those placeholders, and does
 * not follow the `http-equiv=refresh` fallback either. An authenticated-user
 * assertion on such a driver silently misses whatever BigPipe deferred. A
 * scenario tagged `@bigpipe` gets the `big_pipe_nojs` cookie, which makes
 * Drupal render the page in full server-side.
 *
 * Skip processing with tag: `@behat-steps-skip:BigPipeTrait`.
 *
 * Special tags:
 * - `@bigpipe` - render server-side on a driver without JavaScript.
 *
 * Set the `big_pipe.wait_timeout` option to change the maximum wait, or assign
 * `$bigPipeWaitTimeout` to override it for one scenario.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\RawContext
 */
trait BigPipeTrait {

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
   * Per-scenario wait override, NULL to take the configured option.
   */
  protected ?int $bigPipeWaitTimeout = NULL;

  /**
   * Resolve whether the automatic BigPipe wait applies to this scenario.
   */
  #[BeforeScenario]
  public function bigPipeBeforeScenario(BeforeScenarioScope $scope): void {
    $tags = Tag::all($scope);
    $is_skipped = $this->skipTag('BigPipeTrait', $scope);

    $this->bigPipeAutoWaitEnabled = in_array('javascript', $tags, TRUE) && !$is_skipped;
    $this->bigPipeJavascriptProbe = NULL;

    $this->bigPipeServerRenderEnabled = !$is_skipped && in_array('bigpipe', $tags, TRUE);

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
  public function bigPipeWaitForPlaceholders(int $timeout_ms): void {
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
  public function bigPipeGetWaitTimeout(): int {
    return $this->bigPipeWaitTimeout ?? (int) $this->getOption('big_pipe', 'wait_timeout');
  }

  /**
   * Declares the options this trait reads.
   *
   * @return array<string, array<string, mixed>>
   *   Option declarations keyed by option name.
   */
  protected function bigPipeConfigSchema(): array {
    return [
      'enabled' => [
        'default' => TRUE,
        'description' => 'Wait for BigPipe placeholders to be replaced before each step of a `@javascript` scenario.',
      ],
      'wait_timeout' => [
        'default' => 10000,
        'description' => 'Maximum time, in milliseconds, to wait for BigPipe placeholders to be replaced.',
      ],
    ];
  }

}
