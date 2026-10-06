<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Core\Field;

use DrevOps\BehatSteps\Backend\Core\Field\SupportedImageHandler;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use Drupal\filter\Entity\FilterFormat;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for SupportedImageHandler's existing-managed-file reuse path.
 *
 * Complements SupportedImageHandlerKernelTest (upload path): referencing a
 * pre-created image by URI or bare basename reuses its file id without
 * uploading a new copy, and stores the caption and attribution on the item.
 */
#[CoversClass(SupportedImageHandler::class)]
#[Group('fields')]
#[RunTestsInSeparateProcesses]
class SupportedImageHandlerReuseKernelTest extends FileBackedHandlerKernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = [
    ...self::BASE_MODULES,
    'file',
    'image',
    'filter',
    'text',
    'supported_image',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    FilterFormat::create(['format' => 'plain_text', 'name' => 'Plain text'])->save();
  }

  /**
   * Tests that a value naming a managed file reuses that file's id.
   *
   * @param string $value
   *   The field value naming the managed file.
   */
  #[DataProvider('dataProviderReuse')]
  public function testReuse(string $value): void {
    $this->attachField('field_hero', 'supported_image');

    $existing = $this->createManagedFileAt('public://existing-hero.jpg', 'fixture');

    $stub = new EntityStub(static::ENTITY_TYPE, static::BUNDLE, [
      'name' => 'reuse',
      'field_hero' => [
        [
          'target_id' => $value,
          'alt' => 'Hero alt.',
          'title' => 'Hero title.',
          'caption_value' => 'A caption body.',
          'caption_format' => 'plain_text',
          'attribution_value' => 'Photo credit.',
          'attribution_format' => 'plain_text',
        ],
      ],
    ]);

    $this->core->createEntity($stub);

    $stored = $this->loadFirstItem($stub->getValue('id'), 'field_hero');
    $this->assertSame((int) $existing->id(), (int) $stored->get('target_id')->getValue());
    $this->assertSame('Hero alt.', $stored->get('alt')->getValue());
    $this->assertSame('Hero title.', $stored->get('title')->getValue());
    $this->assertSame('A caption body.', $stored->get('caption_value')->getValue());
    $this->assertSame('plain_text', $stored->get('caption_format')->getValue());
    $this->assertSame('Photo credit.', $stored->get('attribution_value')->getValue());
    $this->assertSame('plain_text', $stored->get('attribution_format')->getValue());
    $this->assertSame(1, $this->countFileEntities(), 'A second managed file was created instead of reusing the existing one.');
  }

  public static function dataProviderReuse(): \Iterator {
    yield 'full URI' => ['public://existing-hero.jpg'];
    yield 'bare basename' => ['existing-hero.jpg'];
  }

}
