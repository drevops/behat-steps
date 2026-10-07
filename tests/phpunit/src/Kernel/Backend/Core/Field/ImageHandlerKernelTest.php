<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Core\Field;

use DrevOps\BehatSteps\Backend\Core\Field\ImageHandler;
use Drupal\file\Entity\File;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel round-trip test for ImageHandler via the Core backend.
 *
 * ImageHandler reads an image file, writes it to public:// via the
 * file.repository service, and emits a single-delta shorthand
 * ['target_id' => X, 'alt' => Y, 'title' => Z]. The base helper's
 * normalization handles that shape, so the assertion is identical to the
 * other multi-property handlers.
 */
#[CoversClass(ImageHandler::class)]
#[Group('fields')]
#[RunTestsInSeparateProcesses]
class ImageHandlerKernelTest extends FileBackedHandlerKernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = [
    ...self::BASE_MODULES,
    'file',
    'image',
  ];

  /**
   * Tests round-trip for an image field with a source JPEG from disk.
   *
   * Input matches the canonical handler contract: a list of records keyed
   * by image column name ('target_id', 'alt', 'title').
   */
  public function testImageRoundTrip(): void {
    $this->attachField('field_photo', 'image');

    $fixture = static::FIXTURES_PATH . 'sample.jpg';

    $this->assertFieldRoundTripViaBackend('field_photo', [
      [
        'target_id' => $fixture,
        'alt' => 'A red pixel.',
        'title' => 'Sample photo.',
      ],
    ]);

    $this->assertInstanceOf(File::class, File::load($this->getLatestFileId()));
  }

}
