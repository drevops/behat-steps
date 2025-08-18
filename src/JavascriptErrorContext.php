<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeStepScope;
use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\MinkExtension\Context\MinkContext;

/**
 * Tracks javascript errors on the page.
 */
class JavascriptErrorContext extends MinkContext implements Context {

  /**
   * Errors registry keyed by path.
   */
  private array $jsErrorRegistry = [];

  /**
   * Step patterns.
   */
  private array $stepFilters = [
    '/^I (visit|go to)/',
  ];

  /**
   * Injects a JS error logger before certain step types.
   *
   * This ensures we capture errors starting from relevant points.
   */
  public function beforeStep(BeforeStepScope $scope): void {
    $stepText = $scope->getStep()->getText();
    if ($this->shouldCaptureForStep($stepText)) {
      $script = <<<JS
if (!window.__behatErrors) {
    window.__behatErrors = [];
    (function() {
        const originalError = console.error;
        console.error = function(...args) {
            window.__behatErrors.push(args.join(" "));
            originalError.apply(console, args);
        };
    })();
}
JS;
      $this->getSession()->executeScript($script);
    }
  }

  /**
   * Collects any JS errors after relevant steps and stores them in the
   * registry.
   */
  public function afterStep(AfterStepScope $scope): void {
    $stepText = $scope->getStep()->getText();
    if (!$this->shouldCaptureForStep($stepText)) {
      return;
    }

    $errors = $this->getSession()
      ->evaluateScript('return window.__behatErrors || [];');
    $path = parse_url($this->getSession()->getCurrentUrl(), PHP_URL_PATH);
    $this->jsErrorRegistry[$path] = array_merge(
      $this->jsErrorRegistry[$path] ?? [],
      $errors
    );

    // Clear the error buffer to avoid duplicate capture
    $this->getSession()->executeScript('window.__behatErrors = [];');
  }

  /**
   * Step-level assertion.
   *
   * @Then I should see no JavaScript errors
   */
  public function seeNoJavascriptErrors(): void {
    $path = parse_url($this->getSession()->getCurrentUrl(), PHP_URL_PATH);
    $errors = $this->jsErrorRegistry[$path] ?? [];

    if (!empty($errors)) {
      throw new \Exception("JS errors found on $path:\n" . implode("\n", $errors));
    }
  }

  /**
   * Scenario-level assertion.
   *
   * Triggered by @no-js-errors tag.
   */
  public function afterScenario(AfterScenarioScope $scope): void {
    if (!in_array('no-js-errors', $scope->getScenario()->getTags(), TRUE)) {
      return;
    }

    $allErrors = array_filter($this->jsErrorRegistry);
    if (!empty($allErrors)) {
      $message = "JavaScript errors detected across scenario:\n";
      foreach ($allErrors as $path => $errors) {
        $message .= "$path:\n" . implode("\n", $errors) . "\n";
      }
      throw new \Exception($message);
    }
  }

  /**
   * Determines whether the step text matches any of the filters.
   */
  private function shouldCaptureForStep(string $stepText): bool {
    foreach ($this->stepFilters as $pattern) {
      if (preg_match($pattern, $stepText)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Allows custom steps to be added to the collector filter.
   */
  public function addStepFilter(string $pattern): void {
    $this->stepFilters[] = $pattern;
  }

}
