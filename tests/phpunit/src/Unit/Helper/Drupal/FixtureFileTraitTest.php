<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Helper\Drupal;

use DrevOps\BehatSteps\Backend\BackendInterface;
use DrevOps\BehatSteps\Backend\Core\CoreInterface;
use DrevOps\BehatSteps\Backend\DrupalBackendInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;
use DrevOps\BehatSteps\Behat\Context\WebRawContext;
use DrevOps\BehatSteps\Behat\Manager\BackendRegistry;
use DrevOps\BehatSteps\Behat\Manager\BackendRegistryInterface;
use DrevOps\BehatSteps\Helper\Drupal\FixtureFileTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests resolving a fixture file path for a file or image field.
 */
#[CoversTrait(FixtureFileTrait::class)]
class FixtureFileTraitTest extends UnitTestCase {

  /**
   * A host composing the trait under test.
   */
  protected FixtureFileTraitTestImplementation $testObject;

  /**
   * Per-test fixtures directory, with trailing separator.
   */
  protected string $fixturesPath;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->testObject = new FixtureFileTraitTestImplementation();
    $this->fixturesPath = static::$tmp . DIRECTORY_SEPARATOR;
  }

  /**
   * Creates fixture files with placeholder content in the fixtures directory.
   *
   * @param array<int, string> $paths
   *   Paths to create, relative to the per-test fixtures directory. Missing
   *   parent directories are created.
   */
  protected function createFixtureFiles(array $paths): void {
    foreach ($paths as $path) {
      $full_path = $this->fixturesPath . $path;
      $directory = dirname($full_path);

      if (!is_dir($directory)) {
        mkdir($directory, 0777, TRUE);
      }

      file_put_contents($full_path, 'fixture content');
    }
  }

  #[DataProvider('dataProviderLooksLikeCompoundCell')]
  public function testLooksLikeCompoundCell(string $value, bool $expected): void {
    $this->assertSame($expected, $this->testObject->callLooksLikeCompoundCell($value));
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
    $this->createFixtureFiles($existing_fixture_files);

    $this->testObject->managedBasenames = $existing_managed_basenames;

    $expected = str_replace('{FIXTURES}', $this->fixturesPath, $expected_template);
    $actual = $this->testObject->callExpandCompoundCell($value, $this->fixturesPath);

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
      'rewrites target_id for a fixture in a subdirectory' => [
        'target_id:"sub/text.txt", description:"My file"',
        ['sub/text.txt'],
        [],
        'target_id:"{FIXTURES}sub/text.txt", description:"My file"',
      ],
      'leaves target_id unchanged when the subdirectory fixture is missing' => [
        'target_id:"sub/missing.txt", description:"My file"',
        [],
        [],
        'target_id:"sub/missing.txt", description:"My file"',
      ],
      'leaves target_id unchanged for a stream wrapper uri' => [
        'target_id:"public://text.txt", description:"My file"',
        ['text.txt'],
        [],
        'target_id:"public://text.txt", description:"My file"',
      ],
      'leaves target_id unchanged for an absolute path' => [
        'target_id:"/var/www/text.txt", description:"My file"',
        ['text.txt'],
        [],
        'target_id:"/var/www/text.txt", description:"My file"',
      ],
      'leaves target_id unchanged when traversal escapes the fixtures directory' => [
        'target_id:"../outside.txt", description:"My file"',
        ['../outside.txt'],
        [],
        'target_id:"../outside.txt", description:"My file"',
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
    $this->createFixtureFiles($existing_fixture_files);

    $core = $this->createStub(CoreInterface::class);
    $core->method('getEntityFieldTypes')->willReturn($field_types);

    $backend = $this->createStub(DrupalBackendInterface::class);
    $backend->method('getCore')->willReturn($core);

    $this->testObject->managedBasenames = $existing_managed_basenames;
    $this->testObject->backend = $backend;
    $this->testObject->minkFilesPath = rtrim($this->fixturesPath, DIRECTORY_SEPARATOR);

    $stub = new EntityStub('node', 'article', $stub_values);

    $this->testObject->callExpandEntityFields('node', $stub);

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
      'rewrites a fixture in a subdirectory' => [
        ['sub/document.pdf'],
        [],
        ['field_file' => 'file'],
        ['field_file' => 'sub/document.pdf'],
        fn(string $f): array => ['field_file' => $f . 'sub/document.pdf'],
      ],
      'rewrites a fixture nested several directories deep' => [
        ['sub/nested/image.png'],
        [],
        ['field_image' => 'image'],
        ['field_image' => 'sub/nested/image.png'],
        fn(string $f): array => ['field_image' => $f . 'sub/nested/image.png'],
      ],
      'rewrites every entry in a multi-value list of subdirectory fixtures' => [
        ['sub/document.pdf', 'sub/image.png'],
        [],
        ['field_files' => 'file'],
        ['field_files' => ['sub/document.pdf', 'sub/image.png']],
        fn(string $f): array => ['field_files' => [$f . 'sub/document.pdf', $f . 'sub/image.png']],
      ],
      'leaves missing subdirectory fixture unchanged' => [
        [],
        [],
        ['field_file' => 'file'],
        ['field_file' => 'sub/missing.pdf'],
        fn(string $f): array => ['field_file' => 'sub/missing.pdf'],
      ],
      'leaves stream wrapper uri unchanged' => [
        ['document.pdf'],
        [],
        ['field_file' => 'file'],
        ['field_file' => 'public://document.pdf'],
        fn(string $f): array => ['field_file' => 'public://document.pdf'],
      ],
      'leaves absolute path unchanged' => [
        ['document.pdf'],
        [],
        ['field_file' => 'file'],
        ['field_file' => '/var/www/document.pdf'],
        fn(string $f): array => ['field_file' => '/var/www/document.pdf'],
      ],
      'leaves traversal escaping the fixtures directory unchanged' => [
        ['../outside.pdf'],
        [],
        ['field_file' => 'file'],
        ['field_file' => '../outside.pdf'],
        fn(string $f): array => ['field_file' => '../outside.pdf'],
      ],
      'skips a delta that carries no path and rewrites the rest' => [
        ['document.pdf'],
        [],
        ['field_file' => 'file'],
        ['field_file' => ['', 'document.pdf']],
        fn(string $f): array => ['field_file' => ['', $f . 'document.pdf']],
      ],
      'rewrites a keyed record that indexes its path at zero' => [
        ['document.pdf'],
        [],
        ['field_file' => 'file'],
        ['field_file' => [0 => 'document.pdf', 'description' => 'One']],
        fn(string $f): array => ['field_file' => [0 => $f . 'document.pdf', 'description' => 'One']],
      ],
    ];
  }

  public function testNoFilesPathLeavesTheStubAlone(): void {
    $stub = new EntityStub('node', 'article', ['field_file' => 'document.pdf']);

    $this->testObject->minkFilesPath = '';
    $this->testObject->callExpandEntityFields('node', $stub);

    $this->assertSame(['field_file' => 'document.pdf'], $stub->getValues());
  }

  public function testMissingFilesDirectoryLeavesTheStubAlone(): void {
    $stub = new EntityStub('node', 'article', ['field_file' => 'document.pdf']);

    $this->testObject->minkFilesPath = $this->fixturesPath . 'no-such-directory';
    $this->testObject->callExpandEntityFields('node', $stub);

    $this->assertSame(['field_file' => 'document.pdf'], $stub->getValues());
  }

  public function testBackendWithoutTheCoreCapabilityLeavesTheStubAlone(): void {
    $this->createFixtureFiles(['document.pdf']);

    $stub = new EntityStub('node', 'article', ['field_file' => 'document.pdf']);

    $this->testObject->backend = $this->createStub(BackendInterface::class);
    $this->testObject->minkFilesPath = rtrim($this->fixturesPath, DIRECTORY_SEPARATOR);
    $this->testObject->callExpandEntityFields('node', $stub);

    $this->assertSame(['field_file' => 'document.pdf'], $stub->getValues());
  }

}

/**
 * Host composing the trait under test.
 *
 * Exposes the protected helper methods under the test and stubs the
 * Drupal-dependent 'fixtureFileManagedExists()' so unit tests can simulate
 * pre-existing managed files without bootstrapping Drupal.
 */
class FixtureFileTraitTestImplementation extends WebRawContext {

  use FixtureFileTrait;

  /**
   * Basenames the stubbed 'fixtureFileManagedExists()' reports as managed.
   *
   * @var string[]
   */
  public array $managedBasenames = [];

  /**
   * Value returned by 'getMinkParameter(\'files_path\')'.
   */
  public string $minkFilesPath = '';

  /**
   * Holds the stubbed backend instance once the test sets it.
   */
  public ?BackendInterface $backend = NULL;

  public function callLooksLikeCompoundCell(string $value): bool {
    return $this->fixtureFileLooksLikeCompoundCell($value);
  }

  public function callExpandCompoundCell(string $value, string $fixture_path): string {
    return $this->fixtureFileExpandCompoundCell($value, $fixture_path);
  }

  public function callExpandEntityFields(string $entity_type, EntityStubInterface $stub): void {
    $this->fixtureFileExpandEntityFields($entity_type, $stub);
  }

  public function getMinkParameter(mixed $name): mixed {
    return $name === 'files_path' ? $this->minkFilesPath : NULL;
  }

  /**
   * {@inheritdoc}
   *
   * Serves the stubbed backend from a one-backend manager, so the helper
   * resolves through the same capability walk it uses in a run.
   */
  public function getBackendRegistry(): BackendRegistryInterface {
    if (!$this->backend instanceof BackendInterface) {
      throw new \RuntimeException('Set the backend double before the helper reaches it.');
    }

    $backend_registry = new BackendRegistry(['drupal' => $this->backend]);
    $backend_registry->setScenarioBackends(['drupal' => 'drupal']);

    return $backend_registry;
  }

  /**
   * {@inheritdoc}
   *
   * Overridden to avoid bootstrapping Drupal in unit tests.
   */
  protected function fixtureFileManagedExists(string $basename): bool {
    return in_array($basename, $this->managedBasenames, TRUE);
  }

}
