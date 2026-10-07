<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Core\Field;

use DrevOps\BehatSteps\Backend\Core\Field\FileHandler;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
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
class FileHandlerReuseKernelTest extends FileBackedHandlerKernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = [
    ...self::BASE_MODULES,
    'file',
  ];

  public function testReuseByFullUri(): void {
    $this->attachField('field_attachment', 'file');

    $existing = $this->createManagedFileAt('public://preexisting-uri.txt', 'hello uri');

    $stub = new EntityStub(static::ENTITY_TYPE, static::BUNDLE, [
      'name' => 'with existing file',
      'field_attachment' => ['public://preexisting-uri.txt'],
    ]);

    $this->core->createEntity($stub);

    $stored = $this->loadFirstItem($stub->getValue('id'), 'field_attachment');
    $this->assertSame((int) $existing->id(), (int) $stored->get('target_id')->getValue());
    $this->assertSame(1, $this->countFileEntities(), 'A second managed file was created instead of reusing the existing one.');
  }

  public function testReuseByBareBasenamePublic(): void {
    $this->attachField('field_attachment', 'file');

    $existing = $this->createManagedFileAt('public://preexisting-basename.txt', 'hello basename');

    $stub = new EntityStub(static::ENTITY_TYPE, static::BUNDLE, [
      'name' => 'with existing file by basename',
      'field_attachment' => ['preexisting-basename.txt'],
    ]);

    $this->core->createEntity($stub);

    $stored = $this->loadFirstItem($stub->getValue('id'), 'field_attachment');
    $this->assertSame((int) $existing->id(), (int) $stored->get('target_id')->getValue());
    $this->assertSame(1, $this->countFileEntities());
  }

}
