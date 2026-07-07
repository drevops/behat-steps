<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use DrevOps\BehatSteps\DiagnosticsTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for DiagnosticsTrait.
 */
#[CoversClass(DiagnosticsTrait::class)]
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
    $this->testObject->setRerunCoordinates('/app/tests/behat/features/example.feature', 12);
  }

  public function testBuildBlockRendersEveryEnabledField(): void {
    $this->testObject->session->script = [['message' => 'ReferenceError: x is not defined']];

    $block = $this->testObject->buildBlock();

    $this->assertStringContainsString('--- Failure diagnostics ---', $block);
    $this->assertStringContainsString('URL: http://example.com/page', $block);
    $this->assertStringContainsString('HTTP status: 200', $block);
    $this->assertStringContainsString('Mink driver: ' . DiagnosticsFakeDriver::class, $block);
    $this->assertStringContainsString('JS console errors: ReferenceError: x is not defined', $block);
    $this->assertStringContainsString('Re-run: vendor/bin/behat', $block);
    $this->assertStringContainsString('example.feature:12', $block);
  }

  #[DataProvider('dataProviderDisabledFieldIsOmitted')]
  public function testDisabledFieldIsOmitted(string $toggle, string $absent_label): void {
    $this->testObject->session->script = [['message' => 'ReferenceError: x is not defined']];
    $this->testObject->show[$toggle] = FALSE;

    $block = $this->testObject->buildBlock();

    $this->assertStringNotContainsString($absent_label, $block);
    // The block is still produced from the remaining fields.
    $this->assertStringContainsString('--- Failure diagnostics ---', $block);
  }

  public static function dataProviderDisabledFieldIsOmitted(): array {
    return [
      'url off' => ['url', 'URL:'],
      'status off' => ['status', 'HTTP status:'],
      'driver off' => ['driver', 'Mink driver:'],
      'js errors off' => ['js', 'JS console errors:'],
      'rerun off' => ['rerun', 'Re-run:'],
    ];
  }

  public function testBlankUrlIsOmitted(): void {
    $this->testObject->session->url = '';

    $this->assertStringNotContainsString('URL:', $this->testObject->buildBlock());
  }

  public function testUrlIsOmittedWhenDriverErrors(): void {
    $this->testObject->session->urlError = new \RuntimeException('unsupported');

    $this->assertStringNotContainsString('URL:', $this->testObject->buildBlock());
  }

  public function testStatusIsOmittedWhenDriverErrors(): void {
    $this->testObject->session->statusError = new \RuntimeException('unsupported');

    $this->assertStringNotContainsString('HTTP status:', $this->testObject->buildBlock());
  }

  public function testDriverIsOmittedWhenDriverErrors(): void {
    $this->testObject->session->driverError = new \RuntimeException('unsupported');

    $this->assertStringNotContainsString('Mink driver:', $this->testObject->buildBlock());
  }

  public function testJsErrorsAreOmittedWhenNoneCaptured(): void {
    // The default session carries an empty buffer and there is no registry.
    $this->assertStringNotContainsString('JS console errors:', $this->testObject->buildBlock());
  }

  public function testBuildBlockIsEmptyWhenNothingIsAvailable(): void {
    $this->testObject->sessionAvailable = FALSE;
    $this->testObject->setRerunCoordinates(NULL, NULL);

    $this->assertSame('', $this->testObject->buildBlock());
  }

  public function testAppendAddsBlockToExceptionMessage(): void {
    $exception = new \Exception('Original failure.');

    $this->testObject->appendTo($exception);

    $this->assertStringContainsString('Original failure.', $exception->getMessage());
    $this->assertStringContainsString('--- Failure diagnostics ---', $exception->getMessage());
    $this->assertStringContainsString('URL: http://example.com/page', $exception->getMessage());
  }

  public function testAppendLeavesMessageUnchangedWhenBlockIsEmpty(): void {
    $this->testObject->sessionAvailable = FALSE;
    $this->testObject->setRerunCoordinates(NULL, NULL);
    $exception = new \Exception('Original failure.');

    $this->testObject->appendTo($exception);

    $this->assertSame('Original failure.', $exception->getMessage());
  }

  public function testGetUrlReturnsValue(): void {
    $this->assertSame('http://example.com/page', $this->testObject->getUrl());
  }

  public function testGetUrlReturnsNullWhenBlank(): void {
    $this->testObject->session->url = '';

    $this->assertNull($this->testObject->getUrl());
  }

  public function testGetUrlReturnsNullWhenDriverErrors(): void {
    $this->testObject->session->urlError = new \RuntimeException('unsupported');

    $this->assertNull($this->testObject->getUrl());
  }

  public function testGetStatusCodeReturnsValue(): void {
    $this->testObject->session->status = 500;

    $this->assertSame(500, $this->testObject->getStatusCode());
  }

  public function testGetStatusCodeReturnsNullWhenDriverErrors(): void {
    $this->testObject->session->statusError = new \RuntimeException('unsupported');

    $this->assertNull($this->testObject->getStatusCode());
  }

  public function testGetDriverNameReturnsClass(): void {
    $this->assertSame(DiagnosticsFakeDriver::class, $this->testObject->getDriverName());
  }

  public function testGetDriverNameReturnsNullWhenDriverErrors(): void {
    $this->testObject->session->driverError = new \RuntimeException('unsupported');

    $this->assertNull($this->testObject->getDriverName());
  }

  public function testGetJsErrorsReadsLiveBrowserBuffer(): void {
    $this->testObject->session->script = [
      ['message' => 'TypeError: a'],
      ['message' => 'ReferenceError: b'],
      ['not-a-message' => 'ignored'],
    ];

    $this->assertSame(['TypeError: a', 'ReferenceError: b'], $this->testObject->getJsErrors());
  }

  public function testGetJsErrorsReadsRegistryAndDeduplicates(): void {
    $object = new DiagnosticsTraitJsRegistryImplementation();
    $object->javascriptErrorRegistry = [
      'http://example.com/a' => [['message' => 'TypeError: a'], ['no-message' => 'skip']],
      'http://example.com/b' => 'not-an-array',
    ];
    // The same message arrives from the live buffer and is de-duplicated.
    $object->session->script = [['message' => 'TypeError: a'], ['message' => 'ReferenceError: b']];

    $this->assertSame(['TypeError: a', 'ReferenceError: b'], $object->getJsErrors());
  }

  public function testGetJsErrorsIsEmptyWhenUnavailable(): void {
    $this->testObject->session->scriptError = new \RuntimeException('unsupported');

    $this->assertSame([], $this->testObject->getJsErrors());
  }

  #[DataProvider('dataProviderRerunCommand')]
  public function testRerunCommand(?string $file, ?int $line, ?string $expected): void {
    $this->testObject->setRerunCoordinates($file, $line);

    $this->assertSame($expected, $this->testObject->rerunCommand());
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
    $this->testObject->setRerunCoordinates($cwd . '/features/x.feature', 7);

    $this->assertSame('vendor/bin/behat features/x.feature:7', $this->testObject->rerunCommand());
  }

}

/**
 * Test implementation of DiagnosticsTrait.
 */
class DiagnosticsTraitTestImplementation {

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
   * Per-field toggle state, keyed to match the diagnosticsShow*() overrides.
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
    $this->session = new DiagnosticsFakeSession();
  }

  public function getSession(): DiagnosticsFakeSession {
    if (!$this->sessionAvailable) {
      throw new \RuntimeException('Session is not available.');
    }

    return $this->session;
  }

  public function setRerunCoordinates(?string $file, ?int $line): void {
    $this->diagnosticsFeatureFile = $file;
    $this->diagnosticsScenarioLine = $line;
  }

  public function buildBlock(): string {
    return $this->diagnosticsBuildBlock();
  }

  public function appendTo(\Exception $exception): void {
    $this->diagnosticsAppendToException($exception);
  }

  public function getUrl(): ?string {
    return $this->diagnosticsGetUrl();
  }

  public function getStatusCode(): ?int {
    return $this->diagnosticsGetStatusCode();
  }

  public function getDriverName(): ?string {
    return $this->diagnosticsGetDriverName();
  }

  /**
   * @return array<int, string>
   *   Collected JavaScript error messages.
   */
  public function getJsErrors(): array {
    return $this->diagnosticsGetJsErrors();
  }

  public function rerunCommand(): ?string {
    return $this->diagnosticsGetRerunCommand();
  }

  protected function diagnosticsShowUrl(): bool {
    return $this->show['url'];
  }

  protected function diagnosticsShowStatusCode(): bool {
    return $this->show['status'];
  }

  protected function diagnosticsShowDriver(): bool {
    return $this->show['driver'];
  }

  protected function diagnosticsShowJsErrors(): bool {
    return $this->show['js'];
  }

  protected function diagnosticsShowRerun(): bool {
    return $this->show['rerun'];
  }

}

/**
 * Test implementation that also exposes a JavascriptTrait-style registry.
 */
class DiagnosticsTraitJsRegistryImplementation extends DiagnosticsTraitTestImplementation {

  /**
   * Mimics the registry property maintained by JavascriptTrait.
   *
   * @var array<string, mixed>
   */
  public array $javascriptErrorRegistry = [];

}

/**
 * A minimal fake Mink session whose accessors return values or throw.
 *
 * Assigning a Throwable to one of the *Error properties makes the matching
 * accessor throw, exercising the trait's graceful-degradation paths.
 */
class DiagnosticsFakeSession {

  public string $url = 'http://example.com/page';

  public int $status = 200;

  public object $driver;

  /**
   * The live browser error buffer returned by evaluateScript().
   *
   * @var array<int, mixed>
   */
  public array $script = [];

  public ?\Throwable $urlError = NULL;

  public ?\Throwable $statusError = NULL;

  public ?\Throwable $driverError = NULL;

  public ?\Throwable $scriptError = NULL;

  public function __construct() {
    $this->driver = new DiagnosticsFakeDriver();
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

  public function getDriver(): object {
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
class DiagnosticsFakeDriver {

}
