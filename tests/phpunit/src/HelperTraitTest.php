<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use DrevOps\BehatSteps\HelperTrait;
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

  public function callHelperLooksLikeCompoundCell(string $value): bool {
    return $this->helperLooksLikeCompoundCell($value);
  }

  public function callHelperExpandCompoundCellFixtures(string $value, string $fixture_path): string {
    return $this->helperExpandCompoundCellFixtures($value, $fixture_path);
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
