<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Drupal\Core;

use DrevOps\BehatSteps\Backend\Alias\PreCreateAliasInterface;
use DrevOps\BehatSteps\Backend\Drupal\Core\Core;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;
use Drupal\file\FileInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for the file capability methods on Core.
 *
 * The kernel test site registers the 'public' and 'temporary' stream
 * wrappers and leaves 'private' unregistered.
 */
#[CoversClass(Core::class)]
#[Group('core')]
#[RunTestsInSeparateProcesses]
class CoreFileMethodsKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = ['system', 'user', 'file'];

  /**
   * The Core backend under test.
   */
  protected Core $core;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installConfig(['system']);

    $this->core = new Core($this->root);
  }

  public function testUnmanagedFileLifecycle(): void {
    $uri = 'public://behat/nested/sample.txt';

    $this->assertFalse($this->core->fileExistsUnmanaged($uri));

    $this->core->fileWriteUnmanaged($uri, 'content');

    $this->assertTrue($this->core->fileExistsUnmanaged($uri));
    $this->assertSame('content', $this->core->fileReadUnmanaged($uri));

    $this->core->fileDeleteUnmanaged($uri);
    $this->core->fileDeleteUnmanaged($uri);

    $this->assertFalse($this->core->fileExistsUnmanaged($uri));
    $this->assertTrue(is_dir('public://behat/nested'), 'The directory the write created stays in place.');
  }

  public function testReadUnmanagedRejectsMissingFile(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The file "public://missing.txt" does not exist.');

    $this->core->fileReadUnmanaged('public://missing.txt');
  }

  public function testExistsUnmanagedIsFalseForDirectory(): void {
    $this->assertTrue($this->core->filePrepareDirectory('public://directory'));

    $this->assertFalse($this->core->fileExistsUnmanaged('public://directory'));
  }

  public function testWriteUnmanagedRejectsDirectoryAsTheFile(): void {
    $this->core->filePrepareDirectory('public://directory');

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Unable to write the file "public://directory".');

    $this->core->fileWriteUnmanaged('public://directory', 'content');
  }

  public function testWriteUnmanagedRejectsDirectoryItCannotCreate(): void {
    $this->core->fileWriteUnmanaged('public://file.txt', 'content');

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Unable to prepare the directory "public://file.txt/nested".');

    $this->core->fileWriteUnmanaged('public://file.txt/nested/sample.txt', 'content');
  }

  public function testPrepareDirectoryReportsAnUnregisteredScheme(): void {
    $this->assertFalse($this->core->filePrepareDirectory('private://'));
    $this->assertTrue($this->core->filePrepareDirectory('temporary://'));
    $this->assertTrue($this->core->filePrepareDirectory('public://'));
  }

  public function testWriteManagedSavesTheFileWithTheStubValues(): void {
    $stub = new EntityStub('file', NULL, ['status' => 1]);

    $created = $this->core->fileWriteManaged('public://managed/document.txt', 'data', $stub);

    $this->assertSame($stub, $created);
    $this->assertTrue($created->isSaved());
    $file = $created->getSavedEntity();
    $this->assertInstanceOf(FileInterface::class, $file);
    $this->assertSame($file->id(), $created->getValue('fid'));
    $this->assertSame('public://managed/document.txt', $file->getFileUri());
    $this->assertSame('document.txt', $file->getFilename());
    $this->assertTrue($file->isPermanent());
    $this->assertSame('data', file_get_contents('public://managed/document.txt'));
  }

  public function testWriteManagedRunsTheRegisteredAliases(): void {
    $this->core->registerCreationAlias(new class implements PreCreateAliasInterface {

      /**
       * {@inheritdoc}
       */
      public function getName(): string {
        return 'label';
      }

      /**
       * {@inheritdoc}
       */
      public function getEntityType(): string {
        return 'file';
      }

      /**
       * {@inheritdoc}
       */
      public function getDescription(): string {
        return "Sets the file's 'filename' to the value supplied via 'label'.";
      }

      /**
       * {@inheritdoc}
       */
      public function applyToStub(EntityStubInterface $stub): void {
        $stub->setValue('filename', $stub->getValue('label'));
        $stub->removeValue('label');
      }

    });

    $created = $this->core->fileWriteManaged('public://labelled.txt', 'data', new EntityStub('file', NULL, ['label' => 'Annual report.txt']));

    $file = $created->getSavedEntity();
    $this->assertInstanceOf(FileInterface::class, $file);
    $this->assertSame('Annual report.txt', $file->getFilename());
    $this->assertFalse($created->hasValue('label'));
  }

  public function testWriteManagedReplacesTheFileAtTheUri(): void {
    $first = $this->core->fileWriteManaged('public://document.txt', 'first', new EntityStub('file'));
    $second = $this->core->fileWriteManaged('public://document.txt', 'second', new EntityStub('file'));

    $this->assertSame('second', file_get_contents('public://document.txt'));
    $this->assertSame($first->getValue('fid'), $second->getValue('fid'), 'The managed file at the URI is updated rather than duplicated.');
  }

  public function testWriteManagedRejectsDirectoryItCannotCreate(): void {
    $this->core->fileWriteUnmanaged('public://file.txt', 'content');

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Unable to prepare the directory "public://file.txt/nested".');

    $this->core->fileWriteManaged('public://file.txt/nested/document.txt', 'data', new EntityStub('file'));
  }

}
