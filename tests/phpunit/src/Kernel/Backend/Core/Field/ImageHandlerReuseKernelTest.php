<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Core\Field;

use DrevOps\BehatSteps\Backend\Core\Field\ImageHandler;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\file\Entity\File;
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
class ImageHandlerReuseKernelTest extends FieldHandlerKernelTestBase {

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);

    $public_path = $this->siteDirectory . '/files';
    if (!is_dir($public_path)) {
      mkdir($public_path, 0777, TRUE);
    }
    $this->setSetting('file_public_path', $public_path);
  }

  /**
   * Tests that referencing an image by URI reuses the same file id.
   */
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

  /**
   * Tests that a bare basename resolves against public:// and reuses the id.
   */
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

  /**
   * Loads the first field-item of the named field on the given entity id.
   */
  protected function loadFirstItem(int|string $entity_id, string $field_name): FieldItemInterface {
    $entity = \Drupal::entityTypeManager()
      ->getStorage(static::ENTITY_TYPE)
      ->loadUnchanged($entity_id);
    $this->assertInstanceOf(ContentEntityInterface::class, $entity);

    $item = $entity->get($field_name)->first();
    $this->assertInstanceOf(FieldItemInterface::class, $item);

    return $item;
  }

  /**
   * Creates a managed File at the given URI with the given contents.
   */
  protected function createManagedFileAt(string $uri, string $contents): File {
    file_put_contents($uri, $contents);

    $file = File::create([
      'uri' => $uri,
      'filename' => basename($uri),
      'status' => 1,
    ]);
    $file->save();

    return $file;
  }

  /**
   * Returns the total number of managed File entities currently in storage.
   */
  protected function countFileEntities(): int {
    return (int) \Drupal::entityTypeManager()
      ->getStorage('file')
      ->getQuery()
      ->accessCheck(FALSE)
      ->count()
      ->execute();
  }

}
