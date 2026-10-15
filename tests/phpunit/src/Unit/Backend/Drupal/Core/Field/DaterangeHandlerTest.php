<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Drupal\Core\Field;

use DrevOps\BehatSteps\Backend\Drupal\Core\Field\DaterangeHandler;
use DrevOps\BehatSteps\Backend\Drupal\Core\Field\FieldHandlerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the DaterangeHandler field handler.
 */
#[CoversClass(DaterangeHandler::class)]
#[Group('fields')]
class DaterangeHandlerTest extends FieldHandlerUnitTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    if (!$this->loadDatetimeModuleClasses()) {
      $this->markTestSkipped('drupal/core datetime module classes are not available.');
    }

    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('timezone.default')->willReturn('UTC');

    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')->with('system.date')->willReturn($config);

    $container = new ContainerBuilder();
    $container->set('config.factory', $config_factory);
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
    $field_info = $this->createMock(FieldStorageDefinitionInterface::class);
    $field_info->method('getSetting')
      ->with('datetime_type')
      ->willReturn('datetime');

    $reflection = new \ReflectionClass(DaterangeHandler::class);
    $handler = $reflection->newInstanceWithoutConstructor();

    $info_property = new \ReflectionProperty(DaterangeHandler::class, 'fieldInfo');
    $info_property->setValue($handler, $field_info);

    return $handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function dataProviderExpand(): \Iterator {
    yield 'empty array returns empty list' => [
      [],
      [],
      NULL,
      NULL,
    ];
    yield 'NULL endpoints stay NULL' => [
      [['value' => NULL, 'end_value' => NULL]],
      [['value' => NULL, 'end_value' => NULL]],
      NULL,
      NULL,
    ];
    yield 'positional NULL pair stays NULL' => [
      [[NULL, NULL]],
      [['value' => NULL, 'end_value' => NULL]],
      NULL,
      NULL,
    ];
    yield 'single keyed record auto-wrapped' => [
      ['value' => NULL, 'end_value' => NULL],
      [['value' => NULL, 'end_value' => NULL]],
      NULL,
      NULL,
    ];
    yield 'multi-delta NULL endpoints' => [
      [
        ['value' => NULL, 'end_value' => NULL],
        ['value' => NULL, 'end_value' => NULL],
      ],
      [
        ['value' => NULL, 'end_value' => NULL],
        ['value' => NULL, 'end_value' => NULL],
      ],
      NULL,
      NULL,
    ];

    yield 'non-array element in list rejected' => [
      [
        ['value' => '2026-07-15T09:00:00', 'end_value' => '2026-07-15T17:00:00'],
        'not-a-record',
      ],
      NULL,
      \RuntimeException::class,
      'Daterange field record must be an array',
    ];
  }

}
