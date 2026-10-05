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
   * File id 'file.repository::writeData()' returns from the stub.
   */
  protected const UPLOADED_FILE_ID = 3;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $container = new ContainerBuilder();
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
      [[
        'target_id' => static::UPLOADED_FILE_ID,
        'alt' => NULL,
        'title' => NULL,
        'caption_value' => NULL,
        'caption_format' => NULL,
        'attribution_value' => NULL,
        'attribution_format' => NULL,
      ],
      ],
      NULL,
      NULL,
    ];
    yield 'record preserves caption and attribution metadata' => [
      [[
        'target_id' => static::FIXTURE_PATH,
        'alt' => 'Alt',
        'title' => 'Title',
        'caption_value' => 'Caption body',
        'caption_format' => 'basic_html',
        'attribution_value' => 'Photographer',
        'attribution_format' => 'plain_text',
      ],
      ],
      [[
        'target_id' => static::UPLOADED_FILE_ID,
        'alt' => 'Alt',
        'title' => 'Title',
        'caption_value' => 'Caption body',
        'caption_format' => 'basic_html',
        'attribution_value' => 'Photographer',
        'attribution_format' => 'plain_text',
      ],
      ],
      NULL,
      NULL,
    ];

    yield 'NULL target_id rejected' => [
      [['target_id' => NULL]],
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
  }

}
