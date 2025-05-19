<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Mink\Exception\DriverException;

/**
 * Provides Behat step definitions for tracking and verifying JavaScript errors
 * in web pages using a JavaScript-capable driver.
 */
trait JavascriptTrait
{
  /**
   * Empty the in-page error buffer at the beginning of every scenario.
   *
   * @BeforeScenario @javascript
   */
  public function javascriptResetErrors(): void
  {
    if ($this->driverIsJsCapable()) {
      $this->injectCollector();
      $this->getSession()->getDriver()->executeScript('window.jsErrors = [];');
    }
  }

  /**
   * Execute JavaScript code on the page.
   *
   * @When I execute the JavaScript code :code
   */
  public function javascriptExecuteCode(string $code): void
  {
    if (!$this->driverIsJsCapable()) {
      throw new \RuntimeException('This step requires a JavaScript-capable driver.');
    }

    // Execute the code directly
    try {
      $this->getSession()->getDriver()->executeScript($code);
    } catch (\Throwable $e) {
      // Ignore JavaScript errors
      if (stripos($e->getMessage(), 'javascript error') === false) {
        throw $e;
      }
    }
  }

  /**
   * Assert that at least one JavaScript error occurred.
   *
   * @Then I should get a JavaScript error
   */
  public function javascriptAssertError(): void
  {
    if (!$this->driverIsJsCapable()) {
      throw new \RuntimeException('This step requires a JavaScript-capable driver.');
    }

    $this->injectCollector();

    $errors = $this->getJavaScriptErrors();
    if (empty($errors)) {
      throw new \RuntimeException('No JavaScript errors were found on the page, but at least one was expected.');
    }
  }

  /**
   * Assert that no JavaScript errors occurred.
   *
   * @Then there should be no JavaScript errors on the page
   */
  public function javascriptAssertNoErrors(): void
  {
    if (!$this->driverIsJsCapable()) {
      throw new \RuntimeException('This step requires a JavaScript-capable driver.');
    }

    $this->injectCollector();

    $errors = $this->getJavaScriptErrors();
    if (!empty($errors)) {
      throw new \RuntimeException("JavaScript errors found on the page: " . json_encode($errors));
    }
  }


  /**
   * Inject the JS error collector into the page.
   */
  protected function injectCollector(): void
  {
    $isInstalled = $this->getSession()->getDriver()->executeScript(
      'return typeof window.__behatErrorCollectorInstalled !== "undefined" && window.__behatErrorCollectorInstalled;'
    );

    if ($isInstalled) {
      return;
    }

    $script = <<<'JS'
(function () {
    window.__behatErrorCollectorInstalled = true;
    window.jsErrors = window.jsErrors || [];

    window.addEventListener('error', function (event) {
        window.jsErrors.push({
            message: event.message || 'Unknown error',
            type: 'window.onerror'
        });
    });

    // Intercept console.error
    if (!window.__originalConsoleError) {
        window.__originalConsoleError = console.error;
        console.error = function() {
            var message = Array.from(arguments).join(" ");
            window.jsErrors.push({
                message: message,
                type: "console.error"
            });
            window.__originalConsoleError.apply(console, arguments);
        };
    }
})();
JS;
    $this->getSession()->getDriver()->executeScript($script);
  }

  /**
   * Get current JavaScript errors from the page.
   *
   * @return array<int, array<string,mixed>>
   */
  protected function getJavaScriptErrors(): array
  {
    try {
      $errors = $this->getSession()->getDriver()->executeScript(
        'return (typeof window.jsErrors !== "undefined") ? window.jsErrors : [];'
      );
      return is_array($errors) ? $errors : [];
    } catch (DriverException $e) {
      throw new \RuntimeException('Failed to retrieve JavaScript errors: ' . $e->getMessage(), 0, $e);
    }
  }

  /**
   * Convenience wrapper.
   */
  protected function driverIsJsCapable(): bool
  {
    return $this->getSession()->getDriver() instanceof \Behat\Mink\Driver\Selenium2Driver;
  }
}
