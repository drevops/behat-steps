<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Helper;

use DrevOps\BehatSteps\Behat\Context\DrupalApiInterface;
use DrevOps\BehatSteps\Behat\Context\WebRawContext;
use DrevOps\BehatSteps\Behat\Manager\DriverManager;
use DrevOps\BehatSteps\Behat\Manager\DriverManagerInterface;
use DrevOps\BehatSteps\Driver\Core\CoreInterface;
use DrevOps\BehatSteps\Driver\DriverInterface;
use DrevOps\BehatSteps\Driver\DrupalDriverInterface;
use DrevOps\BehatSteps\Driver\Entity\EntityStub;
use DrevOps\BehatSteps\Driver\Entity\EntityStubInterface;
use DrevOps\BehatSteps\Helper\DrupalApiTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests resolving a fixture file path for a file or image field.
 */
#[CoversTrait(DrupalApiTrait::class)]
class DrupalApiTraitFixturesTest extends UnitTestCase {

  /**
   * A host composing the trait under test.
   */
  protected DrupalApiTraitFixturesTestImplementation $testObject;

  /**
   * Per-test fixtures directory, with trailing separator.
   */
  protected string $fixturesPath;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->testObject = new DrupalApiTraitFixturesTestImplementation();
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
    $this->createFixtureFiles($existing_fixture_files);

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
    $this->testObject->callHelperExpandEntityFieldsFixtures('node', $stub);

    $this->assertSame(['field_file' => 'document.pdf'], $stub->getValues());
  }

  public function testMissingFilesDirectoryLeavesTheStubAlone(): void {
    $stub = new EntityStub('node', 'article', ['field_file' => 'document.pdf']);

    $this->testObject->minkFilesPath = $this->fixturesPath . 'no-such-directory';
    $this->testObject->callHelperExpandEntityFieldsFixtures('node', $stub);

    $this->assertSame(['field_file' => 'document.pdf'], $stub->getValues());
  }

  public function testDriverWithoutTheCoreCapabilityLeavesTheStubAlone(): void {
    $this->createFixtureFiles(['document.pdf']);

    $stub = new EntityStub('node', 'article', ['field_file' => 'document.pdf']);

    $this->testObject->driver = $this->createStub(DriverInterface::class);
    $this->testObject->minkFilesPath = rtrim($this->fixturesPath, DIRECTORY_SEPARATOR);
    $this->testObject->callHelperExpandEntityFieldsFixtures('node', $stub);

    $this->assertSame(['field_file' => 'document.pdf'], $stub->getValues());
  }

}

/**
 * Host composing the trait under test.
 *
 * Exposes the protected helper methods under the test and stubs the
 * Drupal-dependent 'managedFileExists()' so unit tests can simulate
 * pre-existing managed files without bootstrapping Drupal.
 */
class DrupalApiTraitFixturesTestImplementation extends WebRawContext implements DrupalApiInterface {

  use DrupalApiTrait;

  /**
   * Basenames the stubbed 'managedFileExists()' should report as managed.
   *
   * @var string[]
   */
  public array $managedBasenames = [];

  /**
   * Value returned by 'getMinkParameter(\'files_path\')'.
   */
  public string $minkFilesPath = '';

  /**
   * Holds the stubbed driver instance once the test sets it.
   */
  public ?DriverInterface $driver = NULL;

  public function callHelperLooksLikeCompoundCell(string $value): bool {
    return $this->looksLikeCompoundCell($value);
  }

  public function callHelperExpandCompoundCellFixtures(string $value, string $fixture_path): string {
    return $this->expandCompoundCellFixtures($value, $fixture_path);
  }

  public function callHelperExpandEntityFieldsFixtures(string $entity_type, EntityStubInterface $stub): void {
    $this->expandEntityFieldsFixtures($entity_type, $stub);
  }

  public function getMinkParameter(mixed $name): mixed {
    return $name === 'files_path' ? $this->minkFilesPath : NULL;
  }

  /**
   * {@inheritdoc}
   *
   * Serves the stubbed driver from a manager holding it as the only one, so
   * the helper resolves through the same capability walk it uses in a run.
   */
  public function getDriverManager(): DriverManagerInterface {
    if (!$this->driver instanceof DriverInterface) {
      throw new \RuntimeException('Set the driver double before the helper reaches it.');
    }

    $manager = new DriverManager(['drupal' => $this->driver]);
    $manager->setScenarioDrivers(['drupal' => 'drupal']);

    return $manager;
  }

  /**
   * {@inheritdoc}
   *
   * Overridden to avoid bootstrapping Drupal in unit tests.
   */
  protected function managedFileExists(string $basename): bool {
    return in_array($basename, $this->managedBasenames, TRUE);
  }

}
