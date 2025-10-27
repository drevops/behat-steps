<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Hook\Scope\BeforeStepScope;
use Behat\Mink\Driver\Selenium2Driver;

/**
 * Automatically detect JavaScript errors during test execution.
 *
 * - Collects JavaScript errors from `window.onerror` and `console.error`.
 * - Automatically asserts no errors at end of scenarios with `@javascript` tag.
 * - Errors collected only when URL changes (navigation occurs).
 * - Use `@js-errors` tag to bypass error checking when errors are expected.
 *
 * Skip processing with tags: `@behat-steps-skip:javascriptBeforeScenario`,
 * `@behat-steps-skip:javascriptAfterScenario`,
 * `@behat-steps-skip:javascriptBeforeStep`,
 * `@behat-steps-skip:javascriptAfterStep`
 *
 * Or skip all hooks: `@behat-steps-skip:JavascriptTrait`
 *
 * Special tags:
 * - `@js-errors` - bypasses error assertion (allows errors)
 *
 * @code
 * @javascript
 * Scenario: Navigation without JS errors
 *   Given I visit "/home"
 *   When I click on "About"
 *   Then I should see "About Us"
 *   # Automatically fails if JS errors detected
 * @endcode
 *
 * @code
 * @javascript @js-errors
 * Scenario: Legacy page with known errors
 *   Given I visit "/legacy-page"
 *   # Errors collected but not asserted - test passes
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
   *
   * @BeforeScenario @javascript
   */
  public function javascriptBeforeScenario(BeforeScenarioScope $scope): void {
    if ($scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__)) {
      return;
    }
    if ($scope->getScenario()->hasTag('behat-steps-skip:JavascriptTrait')) {
      return;
    }

    $this->javascriptEnabled = TRUE;

    $this->javascriptClearRegistry();
  }

  /**
   * Assert no JavaScript errors at end of scenario.
   *
   * @AfterScenario @javascript
   */
  public function javascriptAfterScenario(AfterScenarioScope $scope): void {
    if (
      $scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__) ||
      $scope->getScenario()->hasTag('behat-steps-skip:JavascriptTrait')
    ) {
      return;
    }

    if ($scope->getScenario()->hasTag('js-errors')) {
      $this->javascriptClearRegistry();
      return;
    }

    $this->javascriptAssertNoErrors();

    // Clean up for next scenario.
    $this->javascriptClearRegistry();
  }

  /**
   * Inject JavaScript error collector before each step.
   *
   * @BeforeStep
   */
  public function javascriptBeforeStep(BeforeStepScope $scope): void {
    if (
      $scope->getFeature()->hasTag('behat-steps-skip:' . __FUNCTION__) ||
      $scope->getFeature()->hasTag('behat-steps-skip:JavascriptTrait')
    ) {
      return;
    }

    if (!$this->javascriptEnabled) {
      return;
    }

    $driver = $this->getSession()->getDriver();

    if (!$driver instanceof Selenium2Driver) {
      return;
    }

    try {
      $this->javascriptCurrentUrl = $this->getSession()->getCurrentUrl();

      $this->javascriptInjectCollector();
    }
    catch (\Exception) {
      // Silently fail if session not started yet.
    }
  }

  /**
   * Collect JavaScript errors after each step if URL changed.
   *
   * @AfterStep
   */
  public function javascriptAfterStep(AfterStepScope $scope): void {
    if (
      $scope->getFeature()->hasTag('behat-steps-skip:' . __FUNCTION__) ||
      $scope->getFeature()->hasTag('behat-steps-skip:JavascriptTrait')
    ) {
      return;
    }

    if (!$this->javascriptEnabled) {
      return;
    }

    $driver = $this->getSession()->getDriver();

    if (!$driver instanceof Selenium2Driver) {
      return;
    }

    try {
      // Get current URL.
      $current_url = $this->getSession()->getCurrentUrl();

      // Only collect errors if URL changed (navigation occurred).
      if ($current_url !== $this->javascriptCurrentUrl) {
        // Re-inject collector on new page.
        $this->javascriptInjectCollector();

        // Small wait to let page errors be caught by collector.
        // 50ms.
        usleep(50000);

        $this->javascriptCollectFromPage($current_url);
        $this->javascriptCurrentUrl = $current_url;
      }
    }
    catch (\Exception) {
      // Silently fail if there are issues.
    }
  }

  /**
   * Inject JavaScript error collector into the page.
   */
  protected function javascriptInjectCollector(): void {
    $script = <<<'JS'
(function() {
  // Initialize error collector
  if (typeof window.jsErrors === 'undefined') {
    window.jsErrors = [];
  }

  // Override window.onerror to capture errors
  if (!window.jsErrorsInitialized) {
    window.jsErrorsInitialized = true;

    window.onerror = function(message, source, lineno, colno, error) {
      window.jsErrors.push({
        message: message,
        source: source,
        line: lineno,
        column: colno,
        timestamp: new Date().toISOString()
      });

      // Don't suppress default error handling
      return false;
    };

    // Also capture console.error
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
    $script = 'return typeof window.jsErrors !== "undefined" ? window.jsErrors : [];';

    try {
      $errors = $this->getSession()->evaluateScript($script);

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
    catch (\Exception) {
      // Silently fail if script evaluation fails.
    }
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
      $message_parts[] = "\nPage: " . $url;

      foreach ($errors as $error) {
        $error_message = $error['message'] ?? 'Unknown error';
        $error_source = $error['source'] ?? 'Unknown source';
        $error_line = $error['line'] ?? 0;

        $message_parts[] = sprintf(
          "  - Error: %s\n    Source: %s:%d",
          $error_message,
          $error_source,
          $error_line
        );
      }
    }

    $message_parts[] = sprintf("\nTotal errors: %d", $error_count);

    throw new \Exception(implode("\n", $message_parts));
  }

  /**
   * Clear the JavaScript error registry.
   */
  protected function javascriptClearRegistry(): void {
    $this->javascriptErrorRegistry = [];
  }

  /**
   * Get the current JavaScript error registry.
   *
   * Useful for debugging purposes.
   *
   * @return array<string, array<int, array<string, mixed>>>
   *   The error registry.
   */
  protected function javascriptGetRegistry(): array {
    return $this->javascriptErrorRegistry;
  }

}
