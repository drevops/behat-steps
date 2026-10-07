<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Helper\Web;

use Behat\MinkExtension\Context\RawMinkContext;
use DrevOps\BehatSteps\Helper\Web\FixtureDirectoryTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for FixtureDirectoryTrait.
 */
#[CoversTrait(FixtureDirectoryTrait::class)]
class FixtureDirectoryTraitTest extends UnitTestCase {

  /**
   * A test implementation of FixtureDirectoryTrait.
   */
  protected FixtureDirectoryTraitTestImplementation $testObject;

  /**
   * The fixtures directory of the test, without a trailing separator.
   */
  protected string $directory;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->writeFixture('fixtures/document.txt', 'Document content');
    $this->writeFixture('fixtures/sub/nested.txt', 'Nested content');
    $this->writeFixture('outside.txt', 'Outside content');

    $this->directory = static::$tmp . '/fixtures';

    $this->testObject = new FixtureDirectoryTraitTestImplementation();
    $this->testObject->filesPath = $this->directory;
  }

  #[DataProvider('dataProviderFind')]
  public function testFind(?string $files_path, bool $is_found): void {
    $this->testObject->filesPath = $files_path === NULL ? NULL : str_replace('{TMP}', static::$tmp, $files_path);

    $this->assertSame($is_found ? $this->directory : NULL, $this->testObject->fixtureDirectoryFind());
  }

  public static function dataProviderFind(): array {
    return [
      'directory' => ['{TMP}/fixtures', TRUE],
      'directory with a trailing separator' => ['{TMP}/fixtures/', TRUE],
      'unset' => [NULL, FALSE],
      'empty' => ['', FALSE],
      'missing directory' => ['{TMP}/missing', FALSE],
      'a file' => ['{TMP}/outside.txt', FALSE],
    ];
  }

  public function testGetReturnsTheDirectory(): void {
    $this->assertSame($this->directory, $this->testObject->fixtureDirectoryGet());
  }

  #[DataProvider('dataProviderGetThrows')]
  public function testGetThrows(?string $files_path, string $expected_message): void {
    $this->testObject->filesPath = $files_path === NULL ? NULL : str_replace('{TMP}', static::$tmp, $files_path);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage($expected_message);

    $this->testObject->fixtureDirectoryGet();
  }

  public static function dataProviderGetThrows(): array {
    return [
      'unset' => [NULL, 'The Mink "files_path" parameter is not configured.'],
      'missing directory' => ['{TMP}/missing', 'The Mink "files_path" parameter is invalid or not accessible.'],
    ];
  }

  #[DataProvider('dataProviderFindFile')]
  public function testFindFile(string $path, ?string $expected): void {
    $this->assertSame($expected === NULL ? NULL : $this->directory . '/' . $expected, $this->testObject->fixtureDirectoryFindFile($path));
  }

  public static function dataProviderFindFile(): array {
    return [
      'file' => ['document.txt', 'document.txt'],
      'file in a subdirectory' => ['sub/nested.txt', 'sub/nested.txt'],
      'leading separator' => ['/document.txt', 'document.txt'],
      'missing file' => ['missing.txt', NULL],
      'a directory' => ['sub', NULL],
      'outside the directory' => ['../outside.txt', NULL],
    ];
  }

  public function testFindFileWithoutDirectoryIsNull(): void {
    $this->testObject->filesPath = NULL;

    $this->assertNull($this->testObject->fixtureDirectoryFindFile('document.txt'));
  }

  #[DataProvider('dataProviderGetFile')]
  public function testGetFile(string $path, string $expected): void {
    $this->assertSame($this->directory . '/' . $expected, $this->testObject->fixtureDirectoryGetFile($path));
  }

  public static function dataProviderGetFile(): array {
    return [
      'file' => ['document.txt', 'document.txt'],
      'surrounding whitespace' => ['  document.txt ', 'document.txt'],
      'file in a subdirectory' => ['sub/nested.txt', 'sub/nested.txt'],
    ];
  }

  #[DataProvider('dataProviderGetFileThrows')]
  public function testGetFileThrows(?string $files_path, string $path, string $expected_message): void {
    $this->testObject->filesPath = $files_path === NULL ? NULL : str_replace('{TMP}', static::$tmp, $files_path);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage(str_replace('{TMP}', static::$tmp, $expected_message));

    $this->testObject->fixtureDirectoryGetFile($path);
  }

  public static function dataProviderGetFileThrows(): array {
    return [
      'empty path' => ['{TMP}/fixtures', '  ', 'A fixture file path cannot be empty.'],
      'unset directory' => [NULL, 'document.txt', 'The Mink "files_path" parameter is not configured.'],
      'missing directory' => ['{TMP}/missing', 'document.txt', 'The Mink "files_path" parameter is invalid or not accessible.'],
      'missing file' => ['{TMP}/fixtures', 'missing.txt', 'The fixture file "{TMP}/fixtures/missing.txt" does not exist.'],
      'outside the directory' => ['{TMP}/fixtures', '../outside.txt', 'The fixture file "../outside.txt" is outside the configured "files_path".'],
    ];
  }

  public function testReadFileReturnsTheContents(): void {
    $this->assertSame('Nested content', $this->testObject->fixtureDirectoryReadFile('sub/nested.txt'));
  }

  public function testReadFileThrowsForMissingFile(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('does not exist');

    $this->testObject->fixtureDirectoryReadFile('missing.txt');
  }

}

/**
 * Test implementation of FixtureDirectoryTrait.
 */
class FixtureDirectoryTraitTestImplementation extends RawMinkContext {

  use FixtureDirectoryTrait;

  /**
   * The value of the Mink `files_path` parameter.
   */
  public ?string $filesPath = NULL;

  public function getMinkParameter(mixed $name): mixed {
    return $name === 'files_path' ? $this->filesPath : NULL;
  }

}
