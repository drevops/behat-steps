<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Core\Field;

use DrevOps\BehatSteps\Backend\Core\Field\ImageHandler;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for ImageHandler's existing-managed-file reuse path.
 *
 * Complements ImageHandlerKernelTest (upload path): referencing a pre-created
 * image by URI or bare basename reuses its file id without uploading a new
 * copy.
 */
#[CoversClass(ImageHandler::class)]
#[Group('fields')]
#[RunTestsInSeparateProcesses]
class ImageHandlerReuseKernelTest extends FileBackedHandlerKernelTestBase {

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

  public function testReuseByFullUri(): void {
    $this->attachField('field_photo', 'image');

    $existing = $this->createManagedFileAt('public://existing-hero.jpg', 'fixture');

    $stub = new EntityStub(static::ENTITY_TYPE, static::BUNDLE, [
      'name' => 'reuse by uri',
      'field_photo' => [
        ['target_id' => 'public://existing-hero.jpg', 'alt' => 'Hero', 'title' => 'Hero title'],
      ],
    ]);

    $this->core->createEntity($stub);

    $stored = $this->loadFirstItem($stub->getValue('id'), 'field_photo');
    $this->assertSame((int) $existing->id(), (int) $stored->get('target_id')->getValue());
    $this->assertSame('Hero', $stored->get('alt')->getValue());
    $this->assertSame('Hero title', $stored->get('title')->getValue());
    $this->assertSame(1, $this->countFileEntities());
  }

  public function testReuseByBareBasename(): void {
    $this->attachField('field_photo', 'image');

    $existing = $this->createManagedFileAt('public://existing-logo.png', 'fixture');

    $stub = new EntityStub(static::ENTITY_TYPE, static::BUNDLE, [
      'name' => 'reuse by basename',
      'field_photo' => [
        ['target_id' => 'existing-logo.png'],
      ],
    ]);

    $this->core->createEntity($stub);

    $stored = $this->loadFirstItem($stub->getValue('id'), 'field_photo');
    $this->assertSame((int) $existing->id(), (int) $stored->get('target_id')->getValue());
    $this->assertSame(1, $this->countFileEntities());
  }

}
