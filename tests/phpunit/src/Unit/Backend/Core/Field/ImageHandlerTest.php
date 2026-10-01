<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Core\Field;

use DrevOps\BehatSteps\Backend\Core\Field\AbstractHandler;
use DrevOps\BehatSteps\Backend\Core\Field\FieldHandlerInterface;
use DrevOps\BehatSteps\Backend\Core\Field\ImageHandler;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the ImageHandler field handler.
 */
#[CoversClass(ImageHandler::class)]
#[Group('fields')]
class ImageHandlerTest extends FileBackedHandlerTestBase {

  /**
   * Absolute path to the bundled fixture file.
   */
  protected const FIXTURE_PATH = self::FIXTURES_PATH . 'fixture.bin';

  /**
   * File id 'file.repository::writeData()' returns from the upload-path stub.
   */
  protected const UPLOADED_FILE_ID = 7;

  /**
   * Storage stub maps these URIs to file ids for the reuse path.
   *
   * @var array<string, int>
   */
  protected const REGISTERED_FILES = [
    'public://hero.jpg' => 55,
    'public://logo.png' => 66,
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $this->createEntityTypeManager(self::REGISTERED_FILES));
    $container->set('file.repository', $this->createFileRepository(self::UPLOADED_FILE_ID));
    \Drupal::setContainer($container);
  }

  /**
   * {@inheritdoc}
   */
  protected function createHandler(): FieldHandlerInterface {
    $reflection = new \ReflectionClass(ImageHandler::class);
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
      self::FIXTURE_PATH,
      [['target_id' => self::UPLOADED_FILE_ID, 'alt' => NULL, 'title' => NULL]],
      NULL,
      NULL,
    ];
    yield 'list of paths triggers upload' => [
      [self::FIXTURE_PATH, self::FIXTURE_PATH],
      [
        ['target_id' => self::UPLOADED_FILE_ID, 'alt' => NULL, 'title' => NULL],
        ['target_id' => self::UPLOADED_FILE_ID, 'alt' => NULL, 'title' => NULL],
      ],
      NULL,
      NULL,
    ];
    yield 'record with alt and title preserved' => [
      [['target_id' => self::FIXTURE_PATH, 'alt' => 'An image', 'title' => 'A title']],
      [['target_id' => self::UPLOADED_FILE_ID, 'alt' => 'An image', 'title' => 'A title']],
      NULL,
      NULL,
    ];
    yield 'known URI reuses managed file' => [
      ['public://hero.jpg'],
      [['target_id' => 55, 'alt' => NULL, 'title' => NULL]],
      NULL,
      NULL,
    ];
    yield 'bare basename resolves under public scheme' => [
      ['logo.png'],
      [['target_id' => 66, 'alt' => NULL, 'title' => NULL]],
      NULL,
      NULL,
    ];
    yield 'record reusing managed file by URI with extras' => [
      [['target_id' => 'public://hero.jpg', 'alt' => 'Hero', 'title' => 'Hero title']],
      [['target_id' => 55, 'alt' => 'Hero', 'title' => 'Hero title']],
      NULL,
      NULL,
    ];

    yield 'NULL target_id rejected' => [
      [['target_id' => NULL, 'alt' => 'A']],
      NULL,
      \RuntimeException::class,
      'Image field "target_id" must not be NULL or empty.',
    ];
    yield 'empty target_id rejected' => [
      [['target_id' => '', 'alt' => 'A']],
      NULL,
      \RuntimeException::class,
      'Image field "target_id" must not be NULL or empty.',
    ];
    yield 'unreadable path bubbles up as RuntimeException' => [
      ['/nonexistent/missing-image.jpg'],
      NULL,
      \RuntimeException::class,
      'Error reading file /nonexistent/missing-image.jpg.',
    ];
  }

}
