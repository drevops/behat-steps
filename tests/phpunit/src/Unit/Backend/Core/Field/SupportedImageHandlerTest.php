<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Core\Field;

use DrevOps\BehatSteps\Backend\Core\Field\AbstractHandler;
use DrevOps\BehatSteps\Backend\Core\Field\FieldHandlerInterface;
use DrevOps\BehatSteps\Backend\Core\Field\SupportedImageHandler;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the SupportedImageHandler field handler.
 */
#[CoversClass(SupportedImageHandler::class)]
#[Group('fields')]
class SupportedImageHandlerTest extends FileBackedHandlerTestBase {

  /**
   * Absolute path to the bundled fixture file.
   */
  protected const FIXTURE_PATH = self::FIXTURES_PATH . 'fixture.bin';

  /**
   * File id 'file.repository::writeData()' returns from the upload-path stub.
   */
  protected const UPLOADED_FILE_ID = 3;

  /**
   * Storage stub maps these URIs to file ids for the reuse path.
   *
   * @var array<string, int>
   */
  protected const REGISTERED_FILES = ['public://hero.jpg' => 55, 'private://portrait.jpg' => 77];

  /**
   * Every property a record can set besides the file reference.
   *
   * @var array<string, string>
   */
  protected const METADATA = [
    'alt' => 'Alt',
    'title' => 'Title',
    'caption_value' => 'Caption body',
    'caption_format' => 'basic_html',
    'attribution_value' => 'Photographer',
    'attribution_format' => 'plain_text',
  ];

  /**
   * The properties the handler emits for a record that sets none of them.
   *
   * @var array<string, null>
   */
  protected const NO_METADATA = [
    'alt' => NULL,
    'title' => NULL,
    'caption_value' => NULL,
    'caption_format' => NULL,
    'attribution_value' => NULL,
    'attribution_format' => NULL,
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
    $reflection = new \ReflectionClass(SupportedImageHandler::class);
    $handler = $reflection->newInstanceWithoutConstructor();

    $main_property = new \ReflectionProperty(AbstractHandler::class, 'mainProperty');
    $main_property->setValue($handler, 'target_id');

    return $handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function dataProviderExpand(): \Iterator {
    yield 'bare scalar path produces full record' => [
      static::FIXTURE_PATH,
      [['target_id' => static::UPLOADED_FILE_ID] + static::NO_METADATA],
      NULL,
      NULL,
    ];
    yield 'list of paths triggers upload' => [
      [static::FIXTURE_PATH, static::FIXTURE_PATH],
      [['target_id' => static::UPLOADED_FILE_ID] + static::NO_METADATA, ['target_id' => static::UPLOADED_FILE_ID] + static::NO_METADATA],
      NULL,
      NULL,
    ];
    yield 'record preserves caption and attribution metadata' => [
      [['target_id' => static::FIXTURE_PATH] + static::METADATA],
      [['target_id' => static::UPLOADED_FILE_ID] + static::METADATA],
      NULL,
      NULL,
    ];
    yield 'known URI reuses managed file' => [
      ['public://hero.jpg'],
      [['target_id' => 55] + static::NO_METADATA],
      NULL,
      NULL,
    ];
    yield 'bare basename resolves under public scheme' => [
      ['hero.jpg'],
      [['target_id' => 55] + static::NO_METADATA],
      NULL,
      NULL,
    ];
    yield 'bare basename falls through to private scheme' => [
      ['portrait.jpg'],
      [['target_id' => 77] + static::NO_METADATA],
      NULL,
      NULL,
    ];
    yield 'record reusing managed file keeps caption and attribution' => [
      [['target_id' => 'public://hero.jpg'] + static::METADATA],
      [['target_id' => 55] + static::METADATA],
      NULL,
      NULL,
    ];

    yield 'NULL target_id rejected' => [
      [['target_id' => NULL]],
      NULL,
      \RuntimeException::class,
      'Supported image field "target_id" must not be NULL or empty.',
    ];
    yield 'empty target_id rejected' => [
      [['target_id' => '']],
      NULL,
      \RuntimeException::class,
      'Supported image field "target_id" must not be NULL or empty.',
    ];
    yield 'unreadable path bubbles up as RuntimeException' => [
      '/nonexistent/missing-supported-image.jpg',
      NULL,
      \RuntimeException::class,
      'Error reading file /nonexistent/missing-supported-image.jpg.',
    ];
    yield 'bare basename without managed file falls back to upload' => [
      ['unmanaged-supported-image.jpg'],
      NULL,
      \RuntimeException::class,
      'Error reading file unmanaged-supported-image.jpg.',
    ];
  }

}
