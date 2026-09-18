<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Driver\Core\Field;

use DrevOps\BehatSteps\Driver\Core\Field\AbstractHandler;
use DrevOps\BehatSteps\Driver\Core\Field\FieldHandlerInterface;
use DrevOps\BehatSteps\Driver\Core\Field\SupportedImageHandler;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the SupportedImageHandler field handler.
 *
 * @group fields
 */
#[CoversClass(SupportedImageHandler::class)]
#[Group('fields')]
class SupportedImageHandlerTest extends FieldHandlerUnitTestBase {

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
    $container->set('file.repository', $this->createFileRepository(self::UPLOADED_FILE_ID));
    \Drupal::setContainer($container);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    \Drupal::unsetContainer();
    parent::tearDown();
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
      self::FIXTURE_PATH,
      [[
        'target_id' => self::UPLOADED_FILE_ID,
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
        'target_id' => self::FIXTURE_PATH,
        'alt' => 'Alt',
        'title' => 'Title',
        'caption_value' => 'Caption body',
        'caption_format' => 'basic_html',
        'attribution_value' => 'Photographer',
        'attribution_format' => 'plain_text',
      ],
      ],
      [[
        'target_id' => self::UPLOADED_FILE_ID,
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
      \InvalidArgumentException::class,
      'Supported image field "target_id" must not be NULL or empty.',
    ];
    yield 'unreadable path bubbles up as Exception' => [
      '/nonexistent/missing-supported-image.jpg',
      NULL,
      \Exception::class,
      'Error reading file /nonexistent/missing-supported-image.jpg.',
    ];
  }

  /**
   * Builds a fake File entity exposing 'id()'.
   */
  protected static function createFakeFile(int $id): object {
    return new readonly class($id) {

      public function __construct(protected int $id) {}

      /**
       * Returns the configured file entity id.
       */
      public function id(): int {
        return $this->id;
      }

      /**
       * Saves the file entity (no-op in the test double).
       */
      public function save(): void {
      }

    };
  }

  /**
   * Builds a file.repository stub returning a fresh File on writeData().
   */
  protected function createFileRepository(int $upload_id): object {
    $file = self::createFakeFile($upload_id);

    return new readonly class($file) {

      public function __construct(protected object $file) {}

      /**
       * Returns the configured file entity for any write.
       */
      public function writeData(string $data, string $destination): object {
        return $this->file;
      }

    };
  }

}
