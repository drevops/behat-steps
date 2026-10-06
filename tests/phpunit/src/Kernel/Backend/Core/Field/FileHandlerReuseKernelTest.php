<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Core\Field;

use DrevOps\BehatSteps\Backend\Core\Field\FileHandler;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\file\Entity\File;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for FileHandler's existing-managed-file reuse path.
 *
 * Complements FileHandlerKernelTest (upload path): referencing a pre-created
 * managed file by URI or by bare basename reuses that file's id without
 * re-uploading the contents.
 */
#[CoversClass(FileHandler::class)]
#[Group('fields')]
#[RunTestsInSeparateProcesses]
class FileHandlerReuseKernelTest extends FieldHandlerKernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = [
    ...self::BASE_MODULES,
    'file',
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
   * Tests that referencing a managed file by URI reuses the same file id.
   */
  public function testReuseByFullUri(): void {
    $this->attachField('field_attachment', 'file');

    $existing = $this->createManagedFileAt('public://preexisting-uri.txt', 'hello uri');

    $stub = new EntityStub(static::ENTITY_TYPE, static::BUNDLE, [
      'name' => 'with existing file',
      'field_attachment' => ['public://preexisting-uri.txt'],
    ]);

    $this->core->createEntity($stub);

    $this->assertSame((int) $existing->id(), (int) $this->loadFieldTargetId($stub->getValue('id'), 'field_attachment'));
    $this->assertSame(1, $this->countFileEntities(), 'A second managed file was created instead of reusing the existing one.');
  }

  /**
   * Tests that a bare basename resolves against public:// and reuses the id.
   */
  public function testReuseByBareBasenamePublic(): void {
    $this->attachField('field_attachment', 'file');

    $existing = $this->createManagedFileAt('public://preexisting-basename.txt', 'hello basename');

    $stub = new EntityStub(static::ENTITY_TYPE, static::BUNDLE, [
      'name' => 'with existing file by basename',
      'field_attachment' => ['preexisting-basename.txt'],
    ]);

    $this->core->createEntity($stub);

    $this->assertSame((int) $existing->id(), (int) $this->loadFieldTargetId($stub->getValue('id'), 'field_attachment'));
    $this->assertSame(1, $this->countFileEntities());
  }

  /**
   * Returns the target_id stored on the first delta of the named field.
   */
  protected function loadFieldTargetId(int|string $entity_id, string $field_name): int|string {
    $entity = \Drupal::entityTypeManager()
      ->getStorage(static::ENTITY_TYPE)
      ->loadUnchanged($entity_id);
    $this->assertInstanceOf(ContentEntityInterface::class, $entity);

    return $entity->get($field_name)->first()->get('target_id')->getValue();
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
