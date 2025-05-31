<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Hook\Scope\BeforeStepScope;
use Behat\Mink\Exception\DriverException;

trait JavascriptTrait
{
  private array $jsErrorRegistry = [];

  private array $monitoredStepPatterns = [
    '/I visit /i',
    '/I go to /i',
    '/I open /i',
    '/I click /i',
    '/I press /i',
  ];

  public function addJsMonitorStepPattern(string $regex): void
  {
    $this->monitoredStepPatterns[] = $regex;
  }

  /** @BeforeScenario @javascript */
  public function jsCollectorBeforeScenario(BeforeScenarioScope $scope): void
  {
    $this->jsErrorRegistry = [];
  }

  /** @BeforeStep @javascript */
  public function jsCollectorBeforeStep(BeforeStepScope $scope): void
  {
    if (!$this->stepNeedsMonitoring($scope->getStep()->getText())) {
      return;
    }
    if (!$this->driverIsJsCapable()) {
      return;
    }

    $this->injectJsCollector();
    $this->safeExecute('window.__behatJsErrors = [];');
  }

  /** @AfterStep @javascript */
  public function jsCollectorAfterStep(AfterStepScope $scope): void
  {
    if (!$this->stepNeedsMonitoring($scope->getStep()->getText())) {
      return;
    }
    if (!$this->driverIsJsCapable()) {
      return;
    }

    $this->injectJsCollector();
    $this->harvestErrorsIntoRegistry();
  }

  /** @AfterScenario @no-js-errors @javascript */
  public function jsCollectorAssertScenarioClean(AfterScenarioScope $scope): void
  {
    $this->harvestErrorsIntoRegistry();
    if ($this->flattenRegistry() !== []) {
      throw new \RuntimeException(
        "JavaScript errors detected during scenario:\n" .
        json_encode($this->jsErrorRegistry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
      );
    }
  }

  /** @Then /^no JavaScript errors should have occurred on this page$/ */
  public function assertNoJsErrorsOnCurrentPage(): void
  {
    if (!$this->driverIsJsCapable()) {
      return;
    }

    $this->harvestErrorsIntoRegistry();
    $path   = $this->currentPath();
    $errors = $this->jsErrorRegistry[$path] ?? [];

    if ($errors !== []) {
      throw new \RuntimeException(
        "JavaScript errors on page $path:\n" .
        json_encode($errors, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
      );
    }
  }

  private function injectJsCollector(): void
  {
    $js = <<<'JS'
            (function () {
                if (window.__behatJsCollectorInstalled) { return; }
                window.__behatJsCollectorInstalled = true;
                window.__behatJsErrors = window.__behatJsErrors || [];

                function push(msg) {
                    try { window.__behatJsErrors.push(String(msg)); } catch (e) {}
                }

                var origConsoleError = console.error;
                console.error = function () {
                    push([].slice.call(arguments).join(' '));
                    return origConsoleError.apply(console, arguments);
                };

                var origOnError = window.onerror;
                window.onerror = function (msg, src, line, col) {
                    push(msg + ' @ ' + src + ':' + line + ':' + col);
                    if (origOnError) { return origOnError.apply(this, arguments); }
                };

                window.addEventListener('error', function (ev) {
                    if (ev.target && (ev.target.src || ev.target.href)) {
                        push('Resource error: ' + (ev.target.src || ev.target.href));
                    }
                }, true);

                window.addEventListener('unhandledrejection', function (ev) {
                    push('Unhandled rejection: ' + (ev.reason && ev.reason.message
                        ? ev.reason.message
                        : ev.reason));
                });
            }());
        JS;

    $this->safeExecute($js);
  }

  private function harvestErrorsIntoRegistry(): void
  {
    try {
      $errors = $this->getSession()
        ->evaluateScript('return window.__behatJsErrors || [];');
    } catch (DriverException $e) {
      return;
    }

    if (!is_array($errors) || $errors === []) {
      return;
    }

    $path = $this->currentPath();
    $this->jsErrorRegistry[$path] = array_merge(
      $this->jsErrorRegistry[$path] ?? [],
      $errors
    );

    $this->safeExecute('window.__behatJsErrors = [];');
  }

  private function stepNeedsMonitoring(string $text): bool
  {
    foreach ($this->monitoredStepPatterns as $rx) {
      if (preg_match($rx, $text)) {
        return true;
      }
    }
    return false;
  }

  private function flattenRegistry(): array
  {
    return array_merge(...array_values($this->jsErrorRegistry ?: [[]]));
  }

  private function currentPath(): string
  {
    $url = $this->getSession()->getCurrentUrl();
    return parse_url($url, PHP_URL_PATH) ?: $url;
  }

  private function driverIsJsCapable(): bool
  {
    return method_exists($this, 'getSession')
      && $this->getSession()->getDriver()->supportsJavascript();
  }

  private function safeExecute(string $script): void
  {
    try {
      $this->getSession()->executeScript($script);
    } catch (DriverException $e) {
    }
  }
}
