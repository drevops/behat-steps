<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Hook\Scope\BeforeStepScope;
use Behat\Hook\BeforeScenario;
use Behat\Hook\BeforeStep;
use Behat\Mink\Exception\DriverException;
use DrevOps\BehatSteps\Behat\Config\Option;
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
 * step until no BigPipe placeholder marker remains in the DOM. The wait
 * removes the race without an explicit step.
 *
 * The wait is best-effort: on timeout the step still runs, so a placeholder
 * that is never replaced fails the following assertion rather than the wait.
 *
 * A browser driver that runs no JavaScript never replaces those placeholders,
 * and does not follow the `http-equiv=refresh` fallback either. An
 * authenticated-user assertion on such a browser driver silently misses the
 * content BigPipe deferred.
 *
 * A scenario tagged `@bigpipe` gets the `big_pipe_nojs` cookie, so Drupal
 * renders the page in full server-side.
 *
 * Skip processing with tag: `@behat-steps-skip:BigPipeTrait`.
 *
 * Special tags:
 * - `@bigpipe` - render server-side on a browser driver without JavaScript.
 *
 * Set the `big_pipe.wait_timeout` option to change the maximum wait, or assign
 * `$bigPipeWaitTimeout` to override it for 1 scenario.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
 */
trait BigPipeTrait {

  /**
   * Cookie name BigPipe reads to bypass streaming and render server-side.
   *
   * The literal avoids a hard dependency on the big_pipe module; it is the
   * value of 'BigPipeStrategy::NOJS_COOKIE'.
   */
  protected const string BIG_PIPE_SERVER_RENDER_COOKIE = 'big_pipe_nojs';

  /**
   * The tag that renders BigPipe placeholders server-side.
   */
  protected const string BIG_PIPE_TAG = 'bigpipe';

  /**
   * Whether the automatic BigPipe wait is active for the current scenario.
   */
  protected bool $bigPipeAutoWaitEnabled = FALSE;

  /**
   * Whether server-side rendering is enabled for the scenario.
   */
  protected bool $bigPipeServerRenderEnabled = FALSE;

  /**
   * Whether the browser driver runs JavaScript, NULL until first probed.
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
    $is_skipped = $this->skipTag(__TRAIT__, $scope);

    $this->bigPipeAutoWaitEnabled = Tag::has($scope, Tag::JAVASCRIPT) && !$is_skipped;
    $this->bigPipeJavascriptProbe = NULL;

    $this->bigPipeServerRenderEnabled = !$is_skipped && Tag::has($scope, self::BIG_PIPE_TAG);

    $this->bigPipeApplyServerRenderCookie();
  }

  /**
   * Keep the state BigPipe needs applied before each step runs.
   *
   * Logging in or out drops session cookies, so the no-JS cookie is re-applied
   * rather than set once for the scenario.
   */
  #[BeforeStep]
  public function bigPipeBeforeStep(BeforeStepScope $scope): void {
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
      // The browser driver session is not ready (e.g. no page has been visited
      // yet), so there is nothing to synchronize.
    }
    // @codeCoverageIgnoreEnd
  }

  /**
   * Set the no-JS cookie for a scenario with server-side rendering enabled.
   *
   * A browser driver that runs JavaScript replaces the placeholders itself, so
   * the cookie is only for the ones that do not. 'setCookie()' is idempotent,
   * so re-applying it on every step costs nothing.
   */
  protected function bigPipeApplyServerRenderCookie(): void {
    if (!$this->bigPipeServerRenderEnabled) {
      return;
    }

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
   * Whether the active browser driver can run JavaScript.
   *
   * An unstarted one counts as unable, and the probe is retried on the next
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
    return $this->bigPipeWaitTimeout ?? $this->getOptionInt('big_pipe', 'wait_timeout');
  }

  /**
   * Declares the options this trait reads.
   *
   * @return array<int, \DrevOps\BehatSteps\Behat\Config\Option>
   *   The options this trait declares.
   */
  protected function bigPipeConfigSchema(): array {
    return [
      new Option('enabled', default: TRUE, description: 'Wait for BigPipe placeholders to be replaced before each step of a `@javascript` scenario.'),
      new Option('wait_timeout', default: 10000, description: 'Maximum time, in milliseconds, to wait for BigPipe placeholders to be replaced.'),
    ];
  }

}
