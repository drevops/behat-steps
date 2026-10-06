<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Core\Field;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\file\Entity\File;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Base kernel test for handlers that store a reference to a managed file.
 *
 * Installs the file schema and points public:// at a writable directory
 * under the test site. Subclasses enable the 'file' module.
 */
#[RunTestsInSeparateProcesses]
abstract class FileBackedHandlerKernelTestBase extends FieldHandlerKernelTestBase {

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

  /**
   * Returns the highest file id currently in storage.
   *
   * A handler that uploads writes a new File, and the test asserts against the
   * most recent one rather than an id fixed in advance.
   */
  protected function getLatestFileId(): int {
    $ids = \Drupal::entityTypeManager()
      ->getStorage('file')
      ->getQuery()
      ->accessCheck(FALSE)
      ->sort('fid', 'DESC')
      ->range(0, 1)
      ->execute();

    return (int) reset($ids);
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

}
