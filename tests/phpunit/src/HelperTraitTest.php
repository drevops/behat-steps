<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use DrevOps\BehatSteps\HelperTrait;
use Drupal\Driver\Core\CoreInterface;
use Drupal\Driver\DrupalDriverInterface;
use Drupal\Driver\Entity\EntityStub;
use Drupal\Driver\Entity\EntityStubInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for HelperTrait.
 */
#[CoversClass(HelperTrait::class)]
class HelperTraitTest extends UnitTestCase {

  /**
   * A test implementation of HelperTrait.
   */
  protected HelperTraitTestImplementation $testObject;

  /**
   * Per-test fixtures directory.
   *
   * Absolute path of an isolated fixtures dir created per test, with trailing
   * separator.
   */
  protected string $fixturesPath;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->testObject = new HelperTraitTestImplementation();

    $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'behat-steps-helper-' . uniqid('', TRUE);
    mkdir($tmp, 0777, TRUE);
    $this->fixturesPath = $tmp . DIRECTORY_SEPARATOR;
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    if (is_dir($this->fixturesPath)) {
      foreach (scandir($this->fixturesPath) as $entry) {
        if ($entry === '.' || $entry === '..') {
          continue;
        }

        unlink($this->fixturesPath . $entry);
      }

      rmdir(rtrim($this->fixturesPath, DIRECTORY_SEPARATOR));
    }

    parent::tearDown();
  }

  #[DataProvider('dataProviderSlug')]
  public function testSlug(string $value, string $expected): void {
    $this->assertSame($expected, $this->testObject->callHelperSlug($value));
  }

  public static function dataProviderSlug(): array {
    return [
      'lowercase ASCII passes through' => ['hello', 'hello'],
      'spaces become single hyphen' => ['hello world', 'hello-world'],
      'mixed case is lowercased' => ['Hello World', 'hello-world'],
      'leading and trailing whitespace trimmed' => ['  hello  ', 'hello'],
      'collapses runs of non-alphanumeric to single hyphen' => ['a___b---c   d', 'a-b-c-d'],
      'punctuation becomes hyphens' => ['feature/scenario: title!', 'feature-scenario-title'],
      'digits preserved' => ['version 1.2.3', 'version-1-2-3'],
      'leading and trailing hyphens stripped' => ['---hello---', 'hello'],
      'empty string falls back to untitled' => ['', 'untitled'],
      'whitespace-only falls back to untitled' => ['   ', 'untitled'],
      'punctuation-only falls back to untitled' => ['!!!', 'untitled'],
      'unicode strips to untitled when no ASCII alnum remains' => ['héllo wörld', 'h-llo-w-rld'],
      'a11y digit boundary stays joined' => ['A11y Trait', 'a11y-trait'],
    ];
  }

  #[DataProvider('dataProviderLooksLikeCompoundCell')]
  public function testLooksLikeCompoundCell(string $value, bool $expected): void {
    $this->assertSame($expected, $this->testObject->callHelperLooksLikeCompoundCell($value));
  }

  public static function dataProviderLooksLikeCompoundCell(): array {
    return [
      'plain basename' => ['document.pdf', FALSE],
      'empty string' => ['', FALSE],
      'colon without quote' => ['foo:bar', FALSE],
      'colon with quote in middle' => ['some text with key:"value" inside', FALSE],
      'target_id with quoted value' => ['target_id:"foo.jpg"', TRUE],
      'target_id with spaces' => ['target_id : "foo.jpg"', TRUE],
      'compound with extra columns' => ['target_id:"foo.jpg", alt:"A"', TRUE],
      'token shape' => ['target_id:[node:1]', TRUE],
      'uppercase key' => ['TARGET_ID:"foo.jpg"', TRUE],
      'leading whitespace' => ['  target_id:"foo.jpg"', TRUE],
      'key starting with digit' => ['1key:"foo.jpg"', FALSE],
      'numeric like value' => ['12345', FALSE],
    ];
  }

  #[DataProvider('dataProviderExpandCompoundCellFixtures')]
  public function testExpandCompoundCellFixtures(string $value, array $existing_fixture_files, array $existing_managed_basenames, string $expected_template): void {
    foreach ($existing_fixture_files as $basename) {
      file_put_contents($this->fixturesPath . $basename, 'fixture content');
    }

    $this->testObject->managedBasenames = $existing_managed_basenames;

    $expected = str_replace('{FIXTURES}', $this->fixturesPath, $expected_template);
    $actual = $this->testObject->callHelperExpandCompoundCellFixtures($value, $this->fixturesPath);

    $this->assertSame($expected, $actual);
  }

  public static function dataProviderExpandCompoundCellFixtures(): array {
    return [
      'rewrites target_id to fixture path when file exists' => [
        'target_id:"text.txt", description:"My file"',
        ['text.txt'],
        [],
        'target_id:"{FIXTURES}text.txt", description:"My file"',
      ],
      'leaves cell unchanged when fixture file is missing' => [
        'target_id:"missing.txt", description:"My file"',
        [],
        [],
        'target_id:"missing.txt", description:"My file"',
      ],
      'leaves cell unchanged when managed file already exists' => [
        'target_id:"text.txt", description:"My file"',
        ['text.txt'],
        ['text.txt'],
        'target_id:"text.txt", description:"My file"',
      ],
      'leaves target_id unchanged when value contains a separator' => [
        'target_id:"sub/text.txt", description:"My file"',
        [],
        [],
        'target_id:"sub/text.txt", description:"My file"',
      ],
      'leaves cell unchanged when no target_id key present' => [
        'alt:"description only", description:"No file"',
        ['text.txt'],
        [],
        'alt:"description only", description:"No file"',
      ],
      'handles image compound with alt sibling' => [
        'target_id:"image.png", alt:"Some alt"',
        ['image.png'],
        [],
        'target_id:"{FIXTURES}image.png", alt:"Some alt"',
      ],
      'rewrites multiple records separated by semicolons' => [
        'target_id:"text.txt", description:"One"; target_id:"image.png", alt:"Two"',
        ['text.txt', 'image.png'],
        [],
        'target_id:"{FIXTURES}text.txt", description:"One"; target_id:"{FIXTURES}image.png", alt:"Two"',
      ],
    ];
  }

  #[DataProvider('dataProviderExpandEntityFieldsFixtures')]
  public function testExpandEntityFieldsFixtures(array $existing_fixture_files, array $existing_managed_basenames, array $field_types, array $stub_values, callable $expected_factory): void {
    foreach ($existing_fixture_files as $basename) {
      file_put_contents($this->fixturesPath . $basename, 'fixture content');
    }

    $core = $this->createStub(CoreInterface::class);
    $core->method('getEntityFieldTypes')->willReturn($field_types);

    $driver = $this->createStub(DrupalDriverInterface::class);
    $driver->method('getCore')->willReturn($core);

    $this->testObject->managedBasenames = $existing_managed_basenames;
    $this->testObject->driver = $driver;
    $this->testObject->minkFilesPath = rtrim($this->fixturesPath, DIRECTORY_SEPARATOR);

    $stub = new EntityStub('node', 'article', $stub_values);

    $this->testObject->callHelperExpandEntityFieldsFixtures('node', $stub);

    $this->assertSame($expected_factory($this->fixturesPath), $stub->getValues());
  }

  public static function dataProviderExpandEntityFieldsFixtures(): array {
    return [
      'rewrites bare scalar file' => [
        ['document.pdf'],
        [],
        ['field_file' => 'file'],
        ['field_file' => 'document.pdf'],
        fn(string $f): array => ['field_file' => $f . 'document.pdf'],
      ],
      'rewrites every entry in a multi-value scalar file list' => [
        ['document.pdf', 'image.png'],
        [],
        ['field_files' => 'file'],
        ['field_files' => ['document.pdf', 'image.png']],
        fn(string $f): array => ['field_files' => [$f . 'document.pdf', $f . 'image.png']],
      ],
      'rewrites target_id in a single keyed record' => [
        ['document.pdf'],
        [],
        ['field_file' => 'file'],
        ['field_file' => ['target_id' => 'document.pdf', 'description' => 'My doc']],
        fn(string $f): array => ['field_file' => ['target_id' => $f . 'document.pdf', 'description' => 'My doc']],
      ],
      'rewrites target_id in every entry of a multi-value compound list' => [
        ['document.pdf', 'image.png'],
        [],
        ['field_files' => 'file'],
        [
          'field_files' => [
            ['target_id' => 'document.pdf', 'description' => 'A'],
            ['target_id' => 'image.png', 'description' => 'B'],
          ],
        ],
        fn(string $f): array => [
          'field_files' => [
            ['target_id' => $f . 'document.pdf', 'description' => 'A'],
            ['target_id' => $f . 'image.png', 'description' => 'B'],
          ],
        ],
      ],
      'leaves non-file field values unchanged' => [
        ['document.pdf'],
        [],
        ['title' => 'string'],
        ['title' => 'document.pdf'],
        fn(string $f): array => ['title' => 'document.pdf'],
      ],
      'leaves missing fixture file unchanged' => [
        [],
        [],
        ['field_file' => 'file'],
        ['field_file' => 'missing.pdf'],
        fn(string $f): array => ['field_file' => 'missing.pdf'],
      ],
      'leaves managed-file basenames unchanged' => [
        ['document.pdf'],
        ['document.pdf'],
        ['field_file' => 'file'],
        ['field_file' => 'document.pdf'],
        fn(string $f): array => ['field_file' => 'document.pdf'],
      ],
      'rewrites raw compound cell string in target_id segment' => [
        ['document.pdf'],
        [],
        ['field_file' => 'file'],
        ['field_file' => 'target_id:"document.pdf", description:"A"'],
        fn(string $f): array => ['field_file' => 'target_id:"' . $f . 'document.pdf", description:"A"'],
      ],
    ];
  }

}

/**
 * Test implementation of HelperTrait.
 *
 * Exposes the protected helper methods under the test and stubs the
 * Drupal-dependent 'helperManagedFileExists()' so unit tests can simulate
 * pre-existing managed files without bootstrapping Drupal.
 */
class HelperTraitTestImplementation {

  use HelperTrait;

  /**
   * Basenames the stubbed 'helperManagedFileExists()' should report as managed.
   *
   * @var string[]
   */
  public array $managedBasenames = [];

  /**
   * Value returned by 'getMinkParameter(\'files_path\')'.
   */
  public string $minkFilesPath = '';

  public function callHelperSlug(string $value): string {
    return $this->helperSlug($value);
  }

  public function callHelperLooksLikeCompoundCell(string $value): bool {
    return $this->helperLooksLikeCompoundCell($value);
  }

  public function callHelperExpandCompoundCellFixtures(string $value, string $fixture_path): string {
    return $this->helperExpandCompoundCellFixtures($value, $fixture_path);
  }

  public function callHelperExpandEntityFieldsFixtures(string $entity_type, EntityStubInterface $stub): void {
    $this->helperExpandEntityFieldsFixtures($entity_type, $stub);
  }

  public function getMinkParameter(string $name): mixed {
    return $name === 'files_path' ? $this->minkFilesPath : NULL;
  }

  /**
   * Holds the stubbed driver instance once the test sets it.
   */
  public ?DrupalDriverInterface $driver = NULL;

  public function getDriver(): ?DrupalDriverInterface {
    return $this->driver;
  }

  /**
   * {@inheritdoc}
   *
   * Overridden to avoid bootstrapping Drupal in unit tests.
   */
  protected function helperManagedFileExists(string $basename): bool {
    return in_array($basename, $this->managedBasenames, TRUE);
  }

}
