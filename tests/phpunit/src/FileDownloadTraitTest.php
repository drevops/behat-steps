<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use DrevOps\BehatSteps\FileDownloadTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for FileDownloadTrait.
 */
#[CoversClass(FileDownloadTrait::class)]
class FileDownloadTraitTest extends UnitTestCase {

  /**
   * A test implementation of FileDownloadTrait.
   *
   * @var \DrevOps\BehatSteps\Tests\FileDownloadTraitTestImplementation
   */
  protected $testObject;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->testObject = new FileDownloadTraitTestImplementation();
  }

  #[DataProvider('dataProviderIsRegex')]
  public function testIsRegex(string $input, bool $expected): void {
    $result = $this->testObject->fileDownloadIsRegex($input);
    $this->assertEquals($expected, $result);
  }

  public static function dataProviderIsRegex(): array {
    return [
      ['/pattern/i', TRUE],
      ['/pattern/m', TRUE],
      ['/pattern/s', TRUE],
      ['/pattern/x', TRUE],
      ['/pattern/u', TRUE],
      ['/pattern/imsxu', TRUE],
      ['/pattern/', TRUE],
      ['/[a-z]+\d{3}/i', TRUE],
      ['/path\/to\/file/i', TRUE],
      ['/pattern/A', TRUE],
      ['/pattern/D', TRUE],
      ['simple text', FALSE],
      ['path/to/file', FALSE],
      ['/not a regex', FALSE],
      ['not a regex/', FALSE],
      ['', FALSE],
      ['/', FALSE],
      ['//', FALSE],
      ['/pattern/g', FALSE],
      ['/pattern/igm', FALSE],
      ['/pattern/I', FALSE],
      ['/pattern/123', FALSE],
      ['/pattern/ i', FALSE],
      ['path/to/some/file.txt', FALSE],
    ];
  }

}

/**
 * Test implementation of FileDownloadTrait.
 */
class FileDownloadTraitTestImplementation {

  use FileDownloadTrait {
    fileDownloadIsRegex as public;
  }

  public function getMinkParameter(string $name): mixed {
    return '';
  }

}
