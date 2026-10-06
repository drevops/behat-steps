<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Core\Field;

use DrevOps\BehatSteps\Backend\Core\Field\AbstractHandler;
use DrevOps\BehatSteps\Backend\Core\Field\FieldHandlerInterface;
use DrevOps\BehatSteps\Backend\Core\Field\FileHandler;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the FileHandler field handler.
 */
#[CoversClass(FileHandler::class)]
#[Group('fields')]
class FileHandlerTest extends FileBackedHandlerTestBase {

  /**
   * Absolute path to the bundled fixture file.
   */
  protected const FIXTURE_PATH = self::FIXTURES_PATH . 'fixture.bin';

  /**
   * File id 'file.repository::writeData()' returns from the upload-path stub.
   */
  protected const UPLOADED_FILE_ID = 42;

  /**
   * Storage stub maps these URIs to file ids for the reuse path.
   *
   * @var array<string, int>
   */
  protected const REGISTERED_FILES = [
    'public://logo.png' => 88,
    'public://hero.jpg' => 99,
    'private://secret.pdf' => 444,
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $this->createEntityTypeManager(static::REGISTERED_FILES));
    $container->set('file.repository', $this->createFileRepository(static::UPLOADED_FILE_ID));
    \Drupal::setContainer($container);
  }

  /**
   * {@inheritdoc}
   */
  protected function createHandler(): FieldHandlerInterface {
    $reflection = new \ReflectionClass(FileHandler::class);
    $handler = $reflection->newInstanceWithoutConstructor();

    $main_property = new \ReflectionProperty(AbstractHandler::class, 'mainProperty');
    $main_property->setValue($handler, 'target_id');

    return $handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function dataProviderExpand(): \Iterator {
    yield 'bare scalar path triggers upload' => [
      static::FIXTURE_PATH,
      [['target_id' => static::UPLOADED_FILE_ID, 'display' => 1, 'description' => '']],
      NULL,
      NULL,
    ];
    yield 'list of paths triggers upload' => [
      [static::FIXTURE_PATH, static::FIXTURE_PATH],
      [
        ['target_id' => static::UPLOADED_FILE_ID, 'display' => 1, 'description' => ''],
        ['target_id' => static::UPLOADED_FILE_ID, 'display' => 1, 'description' => ''],
      ],
      NULL,
      NULL,
    ];
    yield 'record with display and description preserved' => [
      [['target_id' => static::FIXTURE_PATH, 'display' => 0, 'description' => 'Spec sheet']],
      [['target_id' => static::UPLOADED_FILE_ID, 'display' => 0, 'description' => 'Spec sheet']],
      NULL,
      NULL,
    ];
    yield 'known public-scheme URI reuses managed file' => [
      ['public://logo.png'],
      [['target_id' => 88, 'display' => 1, 'description' => '']],
      NULL,
      NULL,
    ];
    yield 'bare basename resolves under public://' => [
      ['logo.png'],
      [['target_id' => 88, 'display' => 1, 'description' => '']],
      NULL,
      NULL,
    ];
    yield 'bare basename falls through to private://' => [
      ['secret.pdf'],
      [['target_id' => 444, 'display' => 1, 'description' => '']],
      NULL,
      NULL,
    ];
    yield 'record reusing managed file by URI' => [
      [['target_id' => 'public://logo.png', 'display' => 0, 'description' => 'Brand mark']],
      [['target_id' => 88, 'display' => 0, 'description' => 'Brand mark']],
      NULL,
      NULL,
    ];

    yield 'NULL target_id rejected by normalize' => [
      [['target_id' => NULL]],
      NULL,
      \RuntimeException::class,
      'File field "target_id" must not be NULL or empty.',
    ];
    yield 'empty target_id rejected by normalize' => [
      [['target_id' => '']],
      NULL,
      \RuntimeException::class,
      'File field "target_id" must not be NULL or empty.',
    ];
    yield 'unreadable path bubbles up as RuntimeException' => [
      ['/nonexistent/missing-file.bin'],
      NULL,
      \RuntimeException::class,
      'Error reading file /nonexistent/missing-file.bin.',
    ];
  }

}
