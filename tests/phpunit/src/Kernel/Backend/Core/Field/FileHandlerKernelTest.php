<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Core\Field;

use DrevOps\BehatSteps\Backend\Core\Field\FileHandler;
use Drupal\file\Entity\File;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel round-trip test for FileHandler via the Core backend.
 *
 * FileHandler reads a source file from disk and writes it into public:// via
 * the file.repository service. It then saves a managed File entity and emits
 * a reference payload (target_id, display, description).
 */
#[CoversClass(FileHandler::class)]
#[Group('fields')]
#[RunTestsInSeparateProcesses]
class FileHandlerKernelTest extends FileBackedHandlerKernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = [
    ...self::BASE_MODULES,
    'file',
  ];

  public function testFileRoundTrip(): void {
    $this->attachField('field_attachment', 'file');

    $fixture = static::FIXTURES_PATH . 'sample.txt';

    $this->assertFieldRoundTripViaBackend('field_attachment', [$fixture]);

    $file_id = $this->getLatestFileId();
    $this->assertInstanceOf(File::class, File::load($file_id));
  }

}
