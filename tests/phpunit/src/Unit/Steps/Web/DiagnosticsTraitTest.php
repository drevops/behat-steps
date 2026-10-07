<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Steps\Web;

use Behat\Mink\Driver\CoreDriver;
use Behat\Mink\Driver\DriverInterface;
use Behat\Mink\Session;
use DrevOps\BehatSteps\Behat\Context\WebRawContext;
use DrevOps\BehatSteps\Steps\Web\DiagnosticsTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for DiagnosticsTrait.
 */
#[CoversTrait(DiagnosticsTrait::class)]
class DiagnosticsTraitTest extends UnitTestCase {

  /**
   * A test implementation of DiagnosticsTrait.
   */
  protected DiagnosticsTraitTestImplementation $testObject;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->testObject = new DiagnosticsTraitTestImplementation();
    $this->testObject->testSetRerunCoordinates('/app/tests/behat/features/example.feature', 12);
  }

  public function testBuildBlockRendersEveryEnabledField(): void {
    $this->testObject->session->script = [['message' => 'ReferenceError: x is not defined']];

    $block = $this->testObject->callBuildBlock();

    $this->assertStringContainsString('--- Failure diagnostics ---', $block);
    $this->assertStringContainsString('URL: http://example.com/page', $block);
    $this->assertStringContainsString('HTTP status: 200', $block);
    $this->assertStringContainsString('Browser driver: ' . DiagnosticsFakeDriver::class, $block);
    $this->assertStringContainsString('JS console errors: ReferenceError: x is not defined', $block);
    $this->assertStringContainsString('Re-run: vendor/bin/behat', $block);
    $this->assertStringContainsString('example.feature:12', $block);
  }

  #[DataProvider('dataProviderDisabledFieldIsOmitted')]
  public function testDisabledFieldIsOmitted(string $toggle, string $absent_label): void {
    $this->testObject->session->script = [['message' => 'ReferenceError: x is not defined']];
    $this->testObject->show[$toggle] = FALSE;

    $block = $this->testObject->callBuildBlock();

    $this->assertStringNotContainsString($absent_label, $block);
    $this->assertStringContainsString('--- Failure diagnostics ---', $block);
  }

  public static function dataProviderDisabledFieldIsOmitted(): array {
    return [
      'url off' => ['url', 'URL:'],
      'status off' => ['status', 'HTTP status:'],
      'driver off' => ['driver', 'Browser driver:'],
      'js errors off' => ['js', 'JS console errors:'],
      'rerun off' => ['rerun', 'Re-run:'],
    ];
  }

  public function testBlankUrlIsOmitted(): void {
    $this->testObject->session->url = '';

    $this->assertStringNotContainsString('URL:', $this->testObject->callBuildBlock());
  }

  public function testUrlIsOmittedWhenDriverErrors(): void {
    $this->testObject->session->urlError = new \RuntimeException('unsupported');

    $this->assertStringNotContainsString('URL:', $this->testObject->callBuildBlock());
  }

  public function testStatusIsOmittedWhenDriverErrors(): void {
    $this->testObject->session->statusError = new \RuntimeException('unsupported');

    $this->assertStringNotContainsString('HTTP status:', $this->testObject->callBuildBlock());
  }

  public function testDriverIsOmittedWhenDriverErrors(): void {
    $this->testObject->session->driverError = new \RuntimeException('unsupported');

    $this->assertStringNotContainsString('Browser driver:', $this->testObject->callBuildBlock());
  }

  public function testJsErrorsAreOmittedWhenNoneCaptured(): void {
    // The default session carries an empty buffer and there is no registry.
    $this->assertStringNotContainsString('JS console errors:', $this->testObject->callBuildBlock());
  }

  public function testBuildBlockIsEmptyWhenNothingIsAvailable(): void {
    $this->testObject->sessionAvailable = FALSE;
    $this->testObject->testSetRerunCoordinates(NULL, NULL);

    $this->assertSame('', $this->testObject->callBuildBlock());
  }

  public function testAppendAddsBlockToExceptionMessage(): void {
    $exception = new \Exception('Original failure.');

    $this->testObject->callAppendToException($exception);

    $this->assertStringContainsString('Original failure.', $exception->getMessage());
    $this->assertStringContainsString('--- Failure diagnostics ---', $exception->getMessage());
    $this->assertStringContainsString('URL: http://example.com/page', $exception->getMessage());
  }

  public function testAppendLeavesMessageUnchangedWhenBlockIsEmpty(): void {
    $this->testObject->sessionAvailable = FALSE;
    $this->testObject->testSetRerunCoordinates(NULL, NULL);
    $exception = new \Exception('Original failure.');

    $this->testObject->callAppendToException($exception);

    $this->assertSame('Original failure.', $exception->getMessage());
  }

  public function testFindUrlReturnsValue(): void {
    $this->assertSame('http://example.com/page', $this->testObject->diagnosticsFindUrl());
  }

  public function testFindUrlReturnsNullWhenBlank(): void {
    $this->testObject->session->url = '';

    $this->assertNull($this->testObject->diagnosticsFindUrl());
  }

  public function testFindUrlReturnsNullWhenDriverErrors(): void {
    $this->testObject->session->urlError = new \RuntimeException('unsupported');

    $this->assertNull($this->testObject->diagnosticsFindUrl());
  }

  public function testFindStatusCodeReturnsValue(): void {
    $this->testObject->session->status = 500;

    $this->assertSame(500, $this->testObject->diagnosticsFindStatusCode());
  }

  public function testFindStatusCodeReturnsNullWhenDriverErrors(): void {
    $this->testObject->session->statusError = new \RuntimeException('unsupported');

    $this->assertNull($this->testObject->diagnosticsFindStatusCode());
  }

  public function testFindBrowserDriverNameReturnsClass(): void {
    $this->assertSame(DiagnosticsFakeDriver::class, $this->testObject->diagnosticsFindBrowserDriverName());
  }

  public function testFindBrowserDriverNameReturnsNullWhenDriverErrors(): void {
    $this->testObject->session->driverError = new \RuntimeException('unsupported');

    $this->assertNull($this->testObject->diagnosticsFindBrowserDriverName());
  }

  public function testGetJsErrorsReadsLiveBrowserBuffer(): void {
    $this->testObject->session->script = [
      ['message' => 'TypeError: a'],
      ['message' => 'ReferenceError: b'],
      ['not-a-message' => 'ignored'],
    ];

    $this->assertSame(['TypeError: a', 'ReferenceError: b'], $this->testObject->diagnosticsGetJsErrors());
  }

  public function testGetJsErrorsReadsRegistryAndDeduplicates(): void {
    $this->testObject->callRecord('http://example.com/a', [['message' => 'TypeError: a'], ['no-message' => 'skip']]);
    // The same message arrives from the live buffer and is de-duplicated.
    $this->testObject->session->script = [['message' => 'TypeError: a'], ['message' => 'ReferenceError: b']];

    $this->assertSame(['TypeError: a', 'ReferenceError: b'], $this->testObject->diagnosticsGetJsErrors());
  }

  public function testGetJsErrorsReadsRegistryWhenBufferIsUnavailable(): void {
    $this->testObject->callRecord('http://example.com/a', [['message' => 'TypeError: a']]);
    $this->testObject->session->scriptError = new \RuntimeException('unsupported');

    $this->assertSame(['TypeError: a'], $this->testObject->diagnosticsGetJsErrors());
  }

  public function testGetJsErrorsIsEmptyWhenUnavailable(): void {
    $this->testObject->session->scriptError = new \RuntimeException('unsupported');

    $this->assertSame([], $this->testObject->diagnosticsGetJsErrors());
  }

  #[DataProvider('dataProviderRerunCommand')]
  public function testRerunCommand(?string $file, ?int $line, ?string $expected): void {
    $this->testObject->testSetRerunCoordinates($file, $line);

    $this->assertSame($expected, $this->testObject->diagnosticsFindRerunCommand());
  }

  public static function dataProviderRerunCommand(): array {
    return [
      'absolute path outside cwd kept as-is' => ['/elsewhere/features/x.feature', 7, 'vendor/bin/behat /elsewhere/features/x.feature:7'],
      'missing file returns null' => [NULL, 7, NULL],
      'missing line returns null' => ['/elsewhere/features/x.feature', NULL, NULL],
    ];
  }

  public function testRerunCommandShortensPathUnderWorkingDirectory(): void {
    $cwd = getcwd();
    $this->assertNotFalse($cwd);
    $this->testObject->testSetRerunCoordinates($cwd . '/features/x.feature', 7);

    $this->assertSame('vendor/bin/behat features/x.feature:7', $this->testObject->diagnosticsFindRerunCommand());
  }

}

/**
 * Test implementation of DiagnosticsTrait.
 */
class DiagnosticsTraitTestImplementation extends WebRawContext {

  use DiagnosticsTrait;

  /**
   * The fake session returned by getSession().
   */
  public DiagnosticsFakeSession $session;

  /**
   * Whether getSession() yields the session or throws to simulate its absence.
   */
  public bool $sessionAvailable = TRUE;

  /**
   * Per-field toggle state, keyed to match the diagnosticsGetShow*() overrides.
   *
   * @var array<string, bool>
   */
  public array $show = [
    'url' => TRUE,
    'status' => TRUE,
    'driver' => TRUE,
    'js' => TRUE,
    'rerun' => TRUE,
  ];

  public function __construct() {
    parent::__construct();

    $this->session = new DiagnosticsFakeSession();
  }

  public function getSession(mixed $name = NULL): Session {
    if (!$this->sessionAvailable) {
      throw new \RuntimeException('Session is not available.');
    }

    return $this->session;
  }

  public function testSetRerunCoordinates(?string $file, ?int $line): void {
    $this->diagnosticsFeatureFile = $file;
    $this->diagnosticsScenarioLine = $line;
  }

  public function callBuildBlock(): string {
    return $this->diagnosticsBuildBlock();
  }

  public function callAppendToException(\Exception $exception): void {
    $this->diagnosticsAppendToException($exception);
  }

  /**
   * Records errors the way JavascriptTrait does after a step.
   *
   * @param string $url
   *   The URL of the page the errors were collected from.
   * @param array<int, array<string, mixed>> $errors
   *   The errors.
   */
  public function callRecord(string $url, array $errors): void {
    $this->javascriptErrorRecord($url, $errors);
  }

  protected function diagnosticsGetShowUrl(): bool {
    return $this->show['url'];
  }

  protected function diagnosticsGetShowStatusCode(): bool {
    return $this->show['status'];
  }

  protected function diagnosticsGetShowBrowserDriver(): bool {
    return $this->show['driver'];
  }

  protected function diagnosticsGetShowJsErrors(): bool {
    return $this->show['js'];
  }

  protected function diagnosticsGetShowRerun(): bool {
    return $this->show['rerun'];
  }

}

/**
 * A minimal fake Mink session whose accessors return values or throw.
 *
 * Assigning a Throwable to one of the *Error properties makes the matching
 * accessor throw, exercising the trait's graceful-degradation paths.
 */
class DiagnosticsFakeSession extends Session {

  /**
   * The current page URL returned by getCurrentUrl().
   */
  public string $url = 'http://example.com/page';

  /**
   * The response status code returned by getStatusCode().
   */
  public int $status = 200;

  /**
   * The driver instance returned by getDriver().
   */
  public DriverInterface $driver;

  /**
   * The live browser error buffer returned by evaluateScript().
   *
   * @var array<int, mixed>
   */
  public array $script = [];

  /**
   * When set, getCurrentUrl() throws this instead of returning a value.
   */
  public ?\Throwable $urlError = NULL;

  /**
   * When set, getStatusCode() throws this instead of returning a value.
   */
  public ?\Throwable $statusError = NULL;

  /**
   * When set, getDriver() throws this instead of returning a value.
   */
  public ?\Throwable $driverError = NULL;

  /**
   * When set, evaluateScript() throws this instead of returning a value.
   */
  public ?\Throwable $scriptError = NULL;

  public function __construct() {
    $this->driver = new DiagnosticsFakeDriver();

    parent::__construct($this->driver);
  }

  public function getCurrentUrl(): string {
    if ($this->urlError instanceof \Throwable) {
      throw $this->urlError;
    }

    return $this->url;
  }

  public function getStatusCode(): int {
    if ($this->statusError instanceof \Throwable) {
      throw $this->statusError;
    }

    return $this->status;
  }

  public function getDriver(): DriverInterface {
    if ($this->driverError instanceof \Throwable) {
      throw $this->driverError;
    }

    return $this->driver;
  }

  public function evaluateScript(string $script): mixed {
    if ($this->scriptError instanceof \Throwable) {
      throw $this->scriptError;
    }

    return $this->script;
  }

}

/**
 * A stand-in driver used only for its class name.
 */
class DiagnosticsFakeDriver extends CoreDriver {}
