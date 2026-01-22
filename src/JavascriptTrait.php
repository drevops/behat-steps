<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Hook\Scope\BeforeStepScope;
use Behat\Hook\AfterScenario;
use Behat\Hook\AfterStep;
use Behat\Hook\BeforeScenario;
use Behat\Hook\BeforeStep;
use Behat\Mink\Driver\Selenium2Driver;
use Behat\Mink\Exception\ExpectationException;

/**
 * Automatically detect JavaScript errors during test execution.
 *
 * - Collects JavaScript errors from `window.onerror` and `console.error`.
 * - Automatically asserts no errors at end of scenarios with `@javascript` tag.
 * - Errors collected only when URL changes (navigation occurs).
 * - Use `@js-errors` tag to bypass error checking when errors are expected.
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
 */
trait JavascriptTrait {

  /**
   * Registry of JavaScript errors collected during scenario execution.
   *
   * @var array<string, array<int, array<string, mixed>>>
   */
  protected array $javascriptErrorRegistry = [];

  /**
   * Current URL stored before each step for change detection.
   */
  protected ?string $javascriptCurrentUrl = NULL;

  /**
   * Whether JavaScript error checking is enabled for current scenario.
   */
  protected bool $javascriptEnabled = FALSE;

  /**
   * Initialize JavaScript error collection for scenarios.
   */
  #[BeforeScenario('@javascript')]
  public function javascriptBeforeScenario(BeforeScenarioScope $scope): void {
    if ($scope->getScenario()->hasTag('behat-steps-skip:JavascriptTrait')) {
      $this->javascriptEnabled = FALSE;
      $this->javascriptClearRegistry();
      return;
    }

    $this->javascriptEnabled = TRUE;

    $this->javascriptClearRegistry();
  }

  /**
   * Assert no JavaScript errors at end of scenario.
   */
  #[AfterScenario('@javascript')]
  public function javascriptAfterScenario(AfterScenarioScope $scope): void {
    if ($scope->getScenario()->hasTag('behat-steps-skip:JavascriptTrait')) {
      $this->javascriptEnabled = FALSE;
      $this->javascriptClearRegistry();
      return;
    }

    if ($scope->getScenario()->hasTag('js-errors')) {
      $this->javascriptClearRegistry();
      $this->javascriptEnabled = FALSE;
      return;
    }

    $this->javascriptAssertNoErrors();

    // Clean up for next scenario.
    $this->javascriptClearRegistry();
    $this->javascriptEnabled = FALSE;
  }

  /**
   * Inject JavaScript error collector before each step.
   */
  #[BeforeStep]
  public function javascriptBeforeStep(BeforeStepScope $scope): void {
    if ($scope->getFeature()->hasTag('behat-steps-skip:JavascriptTrait')) {
      return;
    }

    if (!$this->javascriptEnabled) {
      return;
    }

    $driver = $this->getSession()->getDriver();

    // @codeCoverageIgnoreStart
    if (!$driver instanceof Selenium2Driver) {
      return;
    }
    // @codeCoverageIgnoreEnd
    try {
      $this->javascriptCurrentUrl = $this->getSession()->getCurrentUrl();

      $this->javascriptInjectCollector();
    }
    // @codeCoverageIgnoreStart
    catch (\Exception) {
      // Silently fail if session not started yet.
    }
    // @codeCoverageIgnoreEnd
  }

  /**
   * Collect JavaScript errors after each step if URL changed.
   */
  #[AfterStep]
  public function javascriptAfterStep(AfterStepScope $scope): void {
    if ($scope->getFeature()->hasTag('behat-steps-skip:JavascriptTrait')) {
      return;
    }

    if (!$this->javascriptEnabled) {
      return;
    }

    $driver = $this->getSession()->getDriver();

    // @codeCoverageIgnoreStart
    if (!$driver instanceof Selenium2Driver) {
      return;
    }
    // @codeCoverageIgnoreEnd
    try {
      // Get current URL.
      $current_url = $this->getSession()->getCurrentUrl();

      // Only collect errors if URL changed (navigation occurred).
      if ($current_url !== $this->javascriptCurrentUrl) {
        // Re-inject collector on new page.
        $this->javascriptInjectCollector();
        $this->javascriptCurrentUrl = $current_url;
      }

      $this->javascriptCollectFromPage($current_url);
    }
    // @codeCoverageIgnoreStart
    catch (\Exception) {
      // Silently fail if there are issues.
    }
    // @codeCoverageIgnoreEnd
  }

  /**
   * Inject JavaScript error collector into the page.
   */
  protected function javascriptInjectCollector(): void {
    $script = <<<JS
      (function() {
        // Initialize error collector
        if (typeof window.jsErrors === 'undefined') {
          window.jsErrors = [];
        }

        // Only initialize once
        if (!window.jsErrorsInitialized) {
          window.jsErrorsInitialized = true;

          // Preserve existing window.onerror handler
          var previousOnError = window.onerror;
          window.onerror = function(message, source, lineno, colno, error) {
            window.jsErrors.push({
              message: message,
              source: source,
              line: lineno,
              column: colno,
              timestamp: new Date().toISOString()
            });

            // Call previous handler if it exists
            if (typeof previousOnError === 'function') {
              return previousOnError.apply(this, arguments);
            }

            // Don't suppress default error handling
            return false;
          };

          // Capture unhandled Promise rejections
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

          // Preserve and wrap console.error
          var oldError = console.error;
          console.error = function() {
            window.jsErrors.push({
              message: Array.prototype.slice.call(arguments).join(' '),
              source: 'console.error',
              line: 0,
              column: 0,
              timestamp: new Date().toISOString()
            });
            // Always call original console.error
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
      $errors = $this->getSession()->evaluateScript('return typeof window.jsErrors !== "undefined" ? window.jsErrors : [];');

      if (!empty($errors)) {
        // Store errors in registry under this URL.
        if (!isset($this->javascriptErrorRegistry[$url])) {
          $this->javascriptErrorRegistry[$url] = [];
        }

        foreach ($errors as $error) {
          $this->javascriptErrorRegistry[$url][] = $error;
        }

        // Clear errors from browser.
        $this->getSession()->executeScript('window.jsErrors = [];');
      }
    }
    // @codeCoverageIgnoreStart
    catch (\Exception) {
      // Silently fail if script evaluation fails.
    }
    // @codeCoverageIgnoreEnd
  }

  /**
   * Assert that no JavaScript errors were collected.
   *
   * @throws \Exception
   *   If JavaScript errors were detected.
   */
  protected function javascriptAssertNoErrors(): void {
    if (empty($this->javascriptErrorRegistry)) {
      return;
    }

    // Build detailed error message.
    $error_count = 0;
    $message_parts = ["JavaScript errors detected:\n"];

    foreach ($this->javascriptErrorRegistry as $url => $errors) {
      $error_count += count($errors);
      $message_parts[] = "\nURL: " . $url;

      foreach ($errors as $error) {
        $message_parts[] = sprintf(
          "  - Error: %s\n    Source: %s:%d",
          $error['message'] ?? 'Unknown error',
          $error['source'] ?? 'Unknown source',
          $error['line'] ?? 0
        );
      }
    }

    $message_parts[] = sprintf("\nTotal errors: %d", $error_count);

    throw new ExpectationException(implode("\n", $message_parts), $this->getSession()->getDriver());
  }

  /**
   * Clear the JavaScript error registry.
   */
  protected function javascriptClearRegistry(): void {
    $this->javascriptErrorRegistry = [];
  }

}
