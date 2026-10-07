<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Steps\Drupal;

use DrevOps\BehatSteps\Steps\Drupal\MediaTrait;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for loading media through 'MediaTrait'.
 *
 * The media types use the 'test' source from 'media_test_source', which saves
 * a media item without a source file.
 */
#[CoversTrait(MediaTrait::class)]
#[Group('behat')]
#[RunTestsInSeparateProcesses]
class MediaTraitKernelTest extends StepTraitKernelTestBase {

  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = ['system', 'user', 'field', 'file', 'image', 'media', 'media_test_source'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    $this->installEntitySchema('media');
    $this->installConfig(['field', 'system', 'image', 'file', 'media']);

    $this->createMediaType('test', ['id' => 'document']);
    $this->createMediaType('test', ['id' => 'image']);
  }

  public function testLoadMultipleLoadsTheMatchingMedia(): void {
    $first = $this->createMedia('document', 'Shared');
    $second = $this->createMedia('document', 'Shared');
    $this->createMedia('document', 'Other');
    $this->createMedia('image', 'Shared');

    $media = $this->context->mediaLoadMultiple('document', ['name' => 'Shared']);

    $this->assertLoadedSet([$first, $second], $media, MediaInterface::class);
  }

  public function testLoadMultipleReturnsAnEmptyArrayWhenNothingMatches(): void {
    $this->createMedia('image', 'Shared');

    $this->assertSame([], $this->context->mediaLoadMultiple('document', ['name' => 'Shared']));
  }

  protected function createMedia(string $media_type, string $name): MediaInterface {
    $media = Media::create(['bundle' => $media_type, 'name' => $name]);
    $media->save();

    return $media;
  }

}
