<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Web;

use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Hook\AfterStep;
use Behat\Hook\BeforeScenario;
use Behat\Testwork\Tester\Result\ExceptionResult;
use DrevOps\BehatSteps\Attribute\Steps;

/**
 * Append on-failure diagnostics to the failure message of any failed step.
 *
 * When a step fails, the exception message alone is often not enough to
 * diagnose a red CI run. This trait hooks every step and, only when the step
 * failed, appends a compact diagnostics block to the failure message:
 *
 * - `URL` - the current page URL.
 * - `HTTP status` - the last response status code.
 * - `Mink driver` - the active Mink driver class.
 * - `JS console errors` - collected JavaScript errors, when a JavaScript-capable
 *   driver is active and errors were captured. Reads the buffer maintained by
 *   `JavascriptTrait` when the context also uses it, and the live browser buffer.
 * - `Re-run` - a ready-to-paste command that re-runs just the failing scenario.
 *
 * The trait is opt-in: `use` it in the context and it is active with no further
 * configuration. Every field is individually toggleable by overriding its
 * `diagnosticsGetShow*()` method to return FALSE. Each value source degrades
 * to nothing when the driver cannot provide it, so a failed step is never
 * turned into a different failure.
 *
 * Skip processing with tags: `@behat-steps-skip:DiagnosticsTrait`.
 *
 * @code
 * Scenario: A failing step prints diagnostics
 *   Given I am on "/some-page"
 *   Then I should see "text that is not there"
 *   # On failure the message gains:
 *   # --- Failure diagnostics ---
 *   # URL: http://example.com/some-page
 *   # HTTP status: 200
 *   # Mink driver: Behat\Mink\Driver\BrowserKitDriver
 *   # Re-run: vendor/bin/behat features/example.feature:3
 * @endcode
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
 */
#[Steps]
trait DiagnosticsTrait {

  /**
   * Absolute path to the current feature file, captured for the re-run command.
   */
  protected ?string $diagnosticsFeatureFile = NULL;

  /**
   * Line of the current scenario, captured for the re-run command.
   */
  protected ?int $diagnosticsScenarioLine = NULL;

  /**
   * Whether the current scenario is opted out of diagnostics.
   */
  protected bool $diagnosticsSkip = FALSE;

  /**
   * Capture re-run coordinates and resolve the opt-out for the scenario.
   *
   * The after-step scope exposes no scenario to read a scenario-level skip tag
   * from, so the opt-out is resolved here.
   */
  #[BeforeScenario]
  public function diagnosticsBeforeScenario(BeforeScenarioScope $scope): void {
    $this->diagnosticsSkip = $this->skipTag('DiagnosticsTrait', $scope);

    $this->diagnosticsFeatureFile = $scope->getFeature()->getFile();
    $this->diagnosticsScenarioLine = $scope->getScenario()->getLine();
  }

  /**
   * Append the diagnostics block to the message of a failed step.
   */
  #[AfterStep]
  public function diagnosticsAfterStep(AfterStepScope $scope): void {
    if ($this->diagnosticsSkip) {
      return;
    }

    $result = $scope->getTestResult();
    $exception = $result instanceof ExceptionResult ? $result->getException() : NULL;

    if (!$exception instanceof \Exception) {
      return;
    }

    $this->diagnosticsAppendToException($exception);
  }

  /**
   * Append the diagnostics block to an exception's message in place.
   *
   * The exception is the same instance the formatter later prints, so mutating
   * its message here surfaces the block in the output without re-throwing.
   *
   * @param \Exception $exception
   *   The exception raised by the failed step.
   */
  protected function diagnosticsAppendToException(\Exception $exception): void {
    $block = $this->diagnosticsBuildBlock();

    if ($block === '') {
      return;
    }

    $property = new \ReflectionProperty(\Exception::class, 'message');
    $property->setValue($exception, $exception->getMessage() . PHP_EOL . PHP_EOL . $block);
  }

  /**
   * Build the diagnostics block from the enabled fields.
   *
   * @return string
   *   The rendered block, or an empty string when no field yielded a value.
   */
  protected function diagnosticsBuildBlock(): string {
    $lines = [];

    if ($this->diagnosticsGetShowUrl()) {
      $url = $this->diagnosticsGetUrl();
      if ($url !== NULL) {
        $lines[] = 'URL: ' . $url;
      }
    }

    if ($this->diagnosticsGetShowStatusCode()) {
      $status = $this->diagnosticsGetStatusCode();
      if ($status !== NULL) {
        $lines[] = 'HTTP status: ' . $status;
      }
    }

    if ($this->diagnosticsGetShowDriver()) {
      $driver = $this->diagnosticsGetDriverName();
      if ($driver !== NULL) {
        $lines[] = 'Mink driver: ' . $driver;
      }
    }

    if ($this->diagnosticsGetShowJsErrors()) {
      $errors = $this->diagnosticsGetJsErrors();
      if ($errors !== []) {
        $lines[] = 'JS console errors: ' . implode('; ', $errors);
      }
    }

    if ($this->diagnosticsGetShowRerun()) {
      $rerun = $this->diagnosticsGetRerunCommand();
      if ($rerun !== NULL) {
        $lines[] = 'Re-run: ' . $rerun;
      }
    }

    if ($lines === []) {
      return '';
    }

    return $this->diagnosticsGetHeader() . PHP_EOL . implode(PHP_EOL, $lines);
  }

  /**
   * Return the current page URL, or NULL when it cannot be determined.
   */
  public function diagnosticsGetUrl(): ?string {
    try {
      $url = $this->getSession()->getCurrentUrl();
    }
    catch (\Throwable) {
      return NULL;
    }

    return $url === '' ? NULL : $url;
  }

  /**
   * Return the last response status code, or NULL when it is unavailable.
   */
  public function diagnosticsGetStatusCode(): ?int {
    try {
      return $this->getSession()->getStatusCode();
    }
    catch (\Throwable) {
      return NULL;
    }
  }

  /**
   * Return the active Mink driver class, or NULL when it is unavailable.
   */
  public function diagnosticsGetDriverName(): ?string {
    try {
      return $this->getSession()->getDriver()::class;
    }
    catch (\Throwable) {
      return NULL;
    }
  }

  /**
   * Return collected JavaScript console error messages.
   *
   * Two sources are merged and de-duplicated: the `JavascriptTrait` registry
   * when the context also uses it, and the live browser buffer its collector
   * populates. The registry is detected at runtime, so there is no hard
   * dependency on that trait. Both are best-effort and yield nothing under a
   * driver that cannot evaluate JavaScript.
   *
   * @return array<int, string>
   *   Distinct error messages, in the order first seen.
   */
  public function diagnosticsGetJsErrors(): array {
    $messages = [];

    $registry = get_object_vars($this)['javascriptErrorRegistry'] ?? NULL;
    if (is_array($registry)) {
      foreach ($registry as $errors) {
        foreach (is_array($errors) ? $errors : [] as $error) {
          if (is_array($error) && isset($error['message'])) {
            $messages[] = (string) $error['message'];
          }
        }
      }
    }

    try {
      $live = $this->getSession()->evaluateScript('return (typeof window.jsErrors !== "undefined") ? window.jsErrors : [];');
      foreach (is_array($live) ? $live : [] as $error) {
        if (is_array($error) && isset($error['message'])) {
          $messages[] = (string) $error['message'];
        }
      }
    }
    catch (\Throwable) {
      // A non-JavaScript driver or an unstarted session has no buffer to read.
    }

    return array_values(array_unique($messages));
  }

  /**
   * Return the command that re-runs just the failing scenario.
   *
   * @return string|null
   *   The re-run command, or NULL when the scenario coordinates are unknown.
   */
  public function diagnosticsGetRerunCommand(): ?string {
    if ($this->diagnosticsFeatureFile === NULL || $this->diagnosticsScenarioLine === NULL) {
      return NULL;
    }

    return sprintf('%s %s:%d', $this->diagnosticsGetRerunBinary(), $this->diagnosticsRelativePath($this->diagnosticsFeatureFile), $this->diagnosticsScenarioLine);
  }

  /**
   * Shorten a path to be relative to the working directory when possible.
   *
   * @param string $path
   *   Absolute path to shorten.
   *
   * @return string
   *   The path relative to the working directory, or the input unchanged when
   *   it does not sit under it.
   */
  protected function diagnosticsRelativePath(string $path): string {
    $cwd = getcwd();

    if ($cwd !== FALSE && str_starts_with($path, $cwd . DIRECTORY_SEPARATOR)) {
      return substr($path, strlen($cwd) + 1);
    }

    return $path;
  }

  /**
   * Return the header line that precedes the diagnostics block.
   */
  public function diagnosticsGetHeader(): string {
    return (string) $this->getOption('diagnostics', 'header');
  }

  /**
   * Return the binary used in the re-run command. Override to customise.
   */
  public function diagnosticsGetRerunBinary(): string {
    return (string) $this->getOption('diagnostics', 'rerun_binary');
  }

  /**
   * Return TRUE to include the current URL. Override to suppress.
   */
  public function diagnosticsGetShowUrl(): bool {
    return (bool) $this->getOption('diagnostics', 'show_url');
  }

  /**
   * Return TRUE to include the HTTP status code. Override to suppress.
   */
  public function diagnosticsGetShowStatusCode(): bool {
    return (bool) $this->getOption('diagnostics', 'show_status_code');
  }

  /**
   * Return TRUE to include the Mink driver class. Override to suppress.
   */
  public function diagnosticsGetShowDriver(): bool {
    return (bool) $this->getOption('diagnostics', 'show_driver');
  }

  /**
   * Return TRUE to include JavaScript console errors. Override to suppress.
   */
  public function diagnosticsGetShowJsErrors(): bool {
    return (bool) $this->getOption('diagnostics', 'show_js_errors');
  }

  /**
   * Return TRUE to include the re-run command. Override to suppress.
   */
  public function diagnosticsGetShowRerun(): bool {
    return (bool) $this->getOption('diagnostics', 'show_rerun');
  }

  /**
   * Declares the options this trait reads.
   *
   * @return array<string, array<string, mixed>>
   *   Option declarations keyed by option name.
   */
  protected function diagnosticsConfigSchema(): array {
    return [
      'enabled' => [
        'default' => TRUE,
        'description' => 'Append a diagnostics block to the message of a failing step.',
      ],
      'header' => [
        'default' => '--- Failure diagnostics ---',
        'description' => 'Header line that precedes the diagnostics block.',
      ],
      'rerun_binary' => [
        'default' => 'vendor/bin/behat',
        'description' => 'Binary named in the re-run command the block prints.',
      ],
      'show_url' => [
        'default' => TRUE,
        'description' => 'Include the current URL in the block.',
      ],
      'show_status_code' => [
        'default' => TRUE,
        'description' => 'Include the HTTP status code in the block.',
      ],
      'show_driver' => [
        'default' => TRUE,
        'description' => 'Include the Mink driver class in the block.',
      ],
      'show_js_errors' => [
        'default' => TRUE,
        'description' => 'Include the JavaScript console errors in the block.',
      ],
      'show_rerun' => [
        'default' => TRUE,
        'description' => 'Include the command that re-runs the failing scenario in the block.',
      ],
    ];
  }

}
