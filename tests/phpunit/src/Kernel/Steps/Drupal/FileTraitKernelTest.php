<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Steps\Drupal;

use DrevOps\BehatSteps\Steps\Drupal\FileTrait;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for loading managed files through 'FileTrait'.
 */
#[CoversTrait(FileTrait::class)]
#[Group('behat')]
#[RunTestsInSeparateProcesses]
class FileTraitKernelTest extends StepTraitKernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = ['system', 'user', 'file'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
  }

  /**
   * Tests that the matching files are loaded, keyed by ID.
   */
  public function testLoadMultipleLoadsTheMatchingFiles(): void {
    $first = $this->createFile('public://first/shared.txt');
    $second = $this->createFile('public://second/shared.txt');
    $this->createFile('public://other.txt');

    $files = $this->context->fileLoadMultiple(['filename' => 'shared.txt']);

    $this->assertLoadedSet([$first, $second], $files, FileInterface::class);
  }

  /**
   * Tests that an empty array is returned when no file matches.
   */
  public function testLoadMultipleReturnsAnEmptyArrayWhenNothingMatches(): void {
    $this->createFile('public://other.txt');

    $this->assertSame([], $this->context->fileLoadMultiple(['filename' => 'missing.txt']));
  }

  /**
   * Creates and saves a managed file record, without writing the file.
   */
  protected function createFile(string $uri): FileInterface {
    $file = File::create(['uri' => $uri, 'filename' => basename($uri)]);
    $file->save();

    return $file;
  }

}
