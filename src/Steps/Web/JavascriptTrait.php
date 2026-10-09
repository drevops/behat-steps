<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Web;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Hook\Scope\BeforeStepScope;
use Behat\Hook\AfterScenario;
use Behat\Hook\AfterStep;
use Behat\Hook\BeforeScenario;
use Behat\Hook\BeforeStep;
use Behat\Mink\Exception\ExpectationException;
use DrevOps\BehatSteps\Behat\Config\Option;
use DrevOps\BehatSteps\Behat\Mink\Capability\JavascriptCapabilityInterface;
use DrevOps\BehatSteps\Helper\Web\JavascriptErrorTrait;
use DrevOps\BehatSteps\Helper\Web\LastStepTrait;

/**
 * Automatically detect JavaScript errors during test execution.
 *
 * - Collects JavaScript errors from `window.onerror`, `unhandledrejection`
 *   and `console.error`.
 * - Automatically asserts no errors at end of scenarios with `@javascript` tag.
 * - Collects errors after every step and re-injects the collector when the
 *   URL changes.
 * - The `@js-errors` tag bypasses error checking when errors are expected.
 *
 * Skip processing with tags: `@behat-steps-skip:JavascriptTrait`
 *
 * Special tags:
 * - `@js-errors` - bypasses error assertion for a scenario.
 *
 * Automatic error detection:
 * @code
 * @javascript
 * Scenario: Navigation without JS errors (will fail if errors occur)
 *   Given I visit "/home"
 *   When I click on "About"
 *   Then I should see "About Us"
 * @endcode
 *
 * Bypassing error detection:
 * @code
 * @javascript @js-errors
 * Scenario: Legacy page with known errors (will not fail)
 *   Given I visit "/legacy-page"
 * @endcode
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
 */
trait JavascriptTrait {

  use JavascriptErrorTrait;
  use LastStepTrait;

  /**
   * The tag that keeps a scenario collecting JavaScript errors from failing.
   */
  protected const string JAVASCRIPT_ERRORS_TAG = 'js-errors';

  /**
   * Current URL stored before each step for change detection.
   */
  protected ?string $javascriptCurrentUrl = NULL;

  /**
   * Whether JavaScript error checking is enabled for current scenario.
   */
  protected bool $javascriptEnabled = FALSE;

  /**
   * Whether the collected errors have already been asserted.
   */
  protected bool $javascriptAsserted = FALSE;

  /**
   * Initialize JavaScript error collection for scenarios.
   */
  #[BeforeScenario('@javascript')]
  public function javascriptBeforeScenario(BeforeScenarioScope $scope): void {
    $this->javascriptErrorClear();
    $this->javascriptAsserted = FALSE;

    if ($this->skipTag(__TRAIT__, $scope)) {
      $this->javascriptEnabled = FALSE;
      return;
    }

    $this->javascriptEnabled = TRUE;

    $this->lastStepSetLine($scope);
  }

  /**
   * Assert collected errors when the last step did not run, then reset state.
   *
   * A step that fails by itself skips every later step, including the
   * asserting one, so the errors collected so far are reported here instead.
   * The scenario has already failed by then, so the assertion cannot mask a
   * passing scenario from the rerun cache.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   If JavaScript errors were detected.
   */
  #[AfterScenario('@javascript')]
  public function javascriptAfterScenario(AfterScenarioScope $scope): void {
    $should_assert = $this->javascriptEnabled
      && $this->javascriptGetFailOnErrors()
      && !$this->javascriptAsserted
      && !$scope->getTestResult()->isPassed();

    $this->javascriptEnabled = FALSE;
    $this->javascriptAsserted = FALSE;

    if (!$should_assert) {
      $this->javascriptErrorClear();
      return;
    }

    try {
      $this->javascriptAssertErrorsNotExist();
    }
    finally {
      $this->javascriptErrorClear();
    }
  }

  /**
   * Inject JavaScript error collector before each step.
   */
  #[BeforeStep]
  public function javascriptBeforeStep(BeforeStepScope $scope): void {
    if (!$this->javascriptEnabled) {
      return;
    }

    // @codeCoverageIgnoreStart
    if (!$this->browserDriverHas(JavascriptCapabilityInterface::class)) {
      return;
    }

    // @codeCoverageIgnoreEnd
    try {
      $this->javascriptCurrentUrl = $this->getSession()->getCurrentUrl();

      $this->javascriptInjectCollector();
    }
    // @codeCoverageIgnoreStart
    catch (\Exception) {
      // The session may not be started yet.
    }
    // @codeCoverageIgnoreEnd
  }

  /**
   * Collect JavaScript errors after each step, asserting on the last one.
   *
   * Behat composes a step teardown into that step's result, so a failure
   * raised here marks the scenario as failed for the rerun cache. Asserting
   * on the last step rather than on every step lets the registry accumulate
   * errors from every page the scenario visited.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   If JavaScript errors were detected.
   */
  #[AfterStep]
  public function javascriptAfterStep(AfterStepScope $scope): void {
    if (!$this->javascriptEnabled) {
      return;
    }

    // @codeCoverageIgnoreStart
    if (!$this->browserDriverHas(JavascriptCapabilityInterface::class)) {
      return;
    }

    // @codeCoverageIgnoreEnd
    try {
      $current_url = $this->getSession()->getCurrentUrl();

      if ($current_url !== $this->javascriptCurrentUrl) {
        $this->javascriptInjectCollector();
        $this->javascriptCurrentUrl = $current_url;
      }

      $this->javascriptCollectFromPage($current_url);
    }
    // @codeCoverageIgnoreStart
    catch (\Exception) {
    }

    // @codeCoverageIgnoreEnd
    if (!$this->javascriptGetFailOnErrors() || !$this->lastStepReached($scope)) {
      return;
    }

    // The assertion runs outside the try block above, so the blanket catch
    // cannot swallow the failure.
    $this->javascriptAsserted = TRUE;
    $this->javascriptAssertErrorsNotExist();
  }

  /**
   * Inject JavaScript error collector into the page.
   */
  protected function javascriptInjectCollector(): void {
    $script = <<<JS
      (function() {
        if (typeof window.jsErrors === 'undefined') {
          window.jsErrors = [];
        }

        if (!window.jsErrorsInitialized) {
          window.jsErrorsInitialized = true;

          var previousOnError = window.onerror;
          window.onerror = function(message, source, lineno, colno, error) {
            window.jsErrors.push({
              message: message,
              source: source,
              line: lineno,
              column: colno,
              timestamp: new Date().toISOString()
            });

            if (typeof previousOnError === 'function') {
              return previousOnError.apply(this, arguments);
            }

            // Returning false keeps the default error handling.
            return false;
          };

          window.addEventListener('unhandledrejection', function(event) {
            var reason = event.reason;
            var message = reason && reason.message ? reason.message : String(reason);
            var source = reason && reason.fileName ? reason.fileName : 'unhandledrejection';
            var line = reason && reason.lineNumber ? reason.lineNumber : 0;
            var column = reason && reason.columnNumber ? reason.columnNumber : 0;

            window.jsErrors.push({
              message: 'Unhandled Promise rejection: ' + message,
              source: source,
              line: line,
              column: column,
              timestamp: new Date().toISOString()
            });
          });

          var oldError = console.error;
          console.error = function() {
            window.jsErrors.push({
              message: Array.prototype.slice.call(arguments).join(' '),
              source: 'console.error',
              line: 0,
              column: 0,
              timestamp: new Date().toISOString()
            });
            oldError.apply(console, arguments);
          };
        }
      })();
JS;

    $this->getSession()->executeScript($script);
  }

  /**
   * Collect JavaScript errors from the page.
   *
   * @param string $url
   *   The current page URL.
   */
  protected function javascriptCollectFromPage(string $url): void {
    try {
      $errors = $this->javascriptErrorReadBuffer();

      if ($errors !== []) {
        $this->javascriptErrorRecord($url, $errors);
        $this->javascriptErrorClearBuffer();
      }
    }
    // @codeCoverageIgnoreStart
    catch (\Exception) {
    }
    // @codeCoverageIgnoreEnd
  }

  /**
   * Assert that no JavaScript errors were collected.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   If JavaScript errors were detected.
   */
  public function javascriptAssertErrorsNotExist(): void {
    $registry = $this->javascriptErrorGetAll();

    if ($registry === []) {
      return;
    }

    $error_count = 0;
    $message_parts = ['JavaScript errors detected:' . PHP_EOL];

    foreach ($registry as $url => $errors) {
      $error_count += count($errors);
      $message_parts[] = PHP_EOL . 'URL: ' . $url;

      foreach ($errors as $error) {
        $message_parts[] = sprintf(
          '  - Error: %s' . PHP_EOL . '    Source: %s:%d',
          $error['message'] ?? 'Unknown error',
          $error['source'] ?? 'Unknown source',
          $error['line'] ?? 0
        );
      }
    }

    $message_parts[] = sprintf(PHP_EOL . 'Total errors: %d', $error_count);

    throw new ExpectationException(implode(PHP_EOL, $message_parts), $this->getSession()->getDriver());
  }

  /**
   * Return TRUE if a scenario that collected a console error should fail.
   */
  public function javascriptGetFailOnErrors(): bool {
    return $this->getOptionBool('javascript', 'fail_on_errors');
  }

  /**
   * Declares the options this trait reads.
   *
   * @return array<int, \DrevOps\BehatSteps\Behat\Config\Option>
   *   The options this trait declares.
   */
  protected function javascriptConfigSchema(): array {
    return [
      new Option('enabled', default: TRUE, description: 'Collect JavaScript console errors on a `@javascript` scenario.'),
      new Option('fail_on_errors', default: TRUE, description: 'Fail a scenario that collected a console error. Errors are still collected when this is off.', tags: [self::JAVASCRIPT_ERRORS_TAG => FALSE]),
    ];
  }

}
