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
    $this->testObject->session = new DiagnosticsFakeSession();
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
    // The block itself is still produced from the remaining fields.
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

  #[DataProvider('dataProviderUnavailableFieldIsOmitted')]
  public function testUnavailableFieldIsOmitted(string $property, mixed $value, string $absent_label): void {
    $this->testObject->session->{$property} = $value;

    $block = $this->testObject->buildBlock();

    $this->assertStringNotContainsString($absent_label, $block);
  }

  public static function dataProviderUnavailableFieldIsOmitted(): array {
    return [
      'blank url' => ['url', '', 'URL:'],
      'url driver error' => ['url', new \RuntimeException('unsupported'), 'URL:'],
      'status driver error' => ['status', new \RuntimeException('unsupported'), 'HTTP status:'],
      'driver error' => ['driver', new \RuntimeException('unsupported'), 'Mink driver:'],
      'no js errors' => ['script', [], 'JS console errors:'],
    ];
  }

  public function testBuildBlockIsEmptyWhenNothingIsAvailable(): void {
    $this->testObject->session = NULL;
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
    $this->testObject->session = NULL;
    $this->testObject->setRerunCoordinates(NULL, NULL);
    $exception = new \Exception('Original failure.');

    $this->testObject->appendTo($exception);

    $this->assertSame('Original failure.', $exception->getMessage());
  }

  #[DataProvider('dataProviderGetUrl')]
  public function testGetUrl(mixed $value, ?string $expected): void {
    $this->testObject->session->url = $value;

    $this->assertSame($expected, $this->testObject->getUrl());
  }

  public static function dataProviderGetUrl(): array {
    return [
      'value' => ['http://example.com/page', 'http://example.com/page'],
      'blank returns null' => ['', NULL],
      'driver error returns null' => [new \RuntimeException('unsupported'), NULL],
    ];
  }

  #[DataProvider('dataProviderGetStatusCode')]
  public function testGetStatusCode(mixed $value, ?int $expected): void {
    $this->testObject->session->status = $value;

    $this->assertSame($expected, $this->testObject->getStatusCode());
  }

  public static function dataProviderGetStatusCode(): array {
    return [
      'value' => [500, 500],
      'driver error returns null' => [new \RuntimeException('unsupported'), NULL],
    ];
  }

  public function testGetDriverNameReturnsClass(): void {
    $this->assertSame(DiagnosticsFakeDriver::class, $this->testObject->getDriverName());
  }

  public function testGetDriverNameReturnsNullOnError(): void {
    $this->testObject->session->driver = new \RuntimeException('unsupported');

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

  public function testGetJsErrorsReadsJavascriptTraitRegistryAndDeduplicates(): void {
    $object = new DiagnosticsTraitJsRegistryImplementation();
    $object->session = new DiagnosticsFakeSession();
    $object->javascriptErrorRegistry = [
      'http://example.com/a' => [['message' => 'TypeError: a'], ['no-message' => 'skip']],
      'http://example.com/b' => 'not-an-array',
    ];
    // The same message arrives from the live buffer and is de-duplicated.
    $object->session->script = [['message' => 'TypeError: a'], ['message' => 'ReferenceError: b']];

    $this->assertSame(['TypeError: a', 'ReferenceError: b'], $object->getJsErrors());
  }

  public function testGetJsErrorsIsEmptyWhenUnavailable(): void {
    $this->testObject->session->script = new \RuntimeException('unsupported');

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
   * The fake session returned by getSession(), or NULL to simulate its absence.
   */
  public ?DiagnosticsFakeSession $session = NULL;

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

  public function getSession(): DiagnosticsFakeSession {
    if (!$this->session instanceof DiagnosticsFakeSession) {
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
 * Any property assigned a Throwable is thrown when its accessor is called,
 * exercising the trait's graceful-degradation paths.
 */
class DiagnosticsFakeSession {

  public mixed $url = 'http://example.com/page';

  public mixed $status = 200;

  public mixed $driver;

  public mixed $script = [];

  public function __construct() {
    $this->driver = new DiagnosticsFakeDriver();
  }

  public function getCurrentUrl(): string {
    return $this->resolve($this->url);
  }

  public function getStatusCode(): int {
    return $this->resolve($this->status);
  }

  public function getDriver(): object {
    return $this->resolve($this->driver);
  }

  public function evaluateScript(string $script): mixed {
    return $this->resolve($this->script);
  }

  protected function resolve(mixed $value): mixed {
    if ($value instanceof \Throwable) {
      throw $value;
    }

    return $value;
  }

}

/**
 * A stand-in driver used only for its class name.
 */
class DiagnosticsFakeDriver {

}
