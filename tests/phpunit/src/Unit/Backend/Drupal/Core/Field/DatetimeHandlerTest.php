<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Drupal\Core\Field;

use DrevOps\BehatSteps\Backend\Drupal\Core\Field\AbstractHandler;
use DrevOps\BehatSteps\Backend\Drupal\Core\Field\DatetimeHandler;
use DrevOps\BehatSteps\Backend\Drupal\Core\Field\FieldHandlerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the DatetimeHandler field handler.
 *
 * Full date parsing exercises 'DrupalDateTime', which needs the
 * 'language_manager' service and a real Drupal container. Only the
 * empty/NULL early-return cases and the shape rejections 'normalize()'
 * raises before any date handling are asserted here.
 */
#[CoversClass(DatetimeHandler::class)]
#[Group('fields')]
class DatetimeHandlerTest extends FieldHandlerUnitTestBase {

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

    $reflection = new \ReflectionClass(DatetimeHandler::class);
    $handler = $reflection->newInstanceWithoutConstructor();

    $info_property = new \ReflectionProperty(DatetimeHandler::class, 'fieldInfo');
    $info_property->setValue($handler, $field_info);

    $main_property = new \ReflectionProperty(AbstractHandler::class, 'mainProperty');
    $main_property->setValue($handler, 'value');

    return $handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function dataProviderExpand(): \Iterator {
    yield 'bare empty string becomes NULL' => [
      '',
      [['value' => NULL]],
      NULL,
      NULL,
    ];
    yield 'bare NULL becomes NULL' => [
      NULL,
      [['value' => NULL]],
      NULL,
      NULL,
    ];
    yield 'list of empty scalars' => [
      ['', NULL],
      [['value' => NULL], ['value' => NULL]],
      NULL,
      NULL,
    ];
    yield 'single record with empty value' => [
      ['value' => ''],
      [['value' => NULL]],
      NULL,
      NULL,
    ];
    yield 'list of records with empty values' => [
      [['value' => ''], ['value' => NULL]],
      [['value' => NULL], ['value' => NULL]],
      NULL,
      NULL,
    ];

    yield 'mixed positional and named keys rejected' => [
      ['2026-07-15T09:00:00', 'extra' => 'oops'],
      NULL,
      \RuntimeException::class,
      'Field value cannot mix positional and named keys',
    ];
    yield 'record missing main property rejected' => [
      ['format' => 'datetime'],
      NULL,
      \RuntimeException::class,
      'Field record must include the main property "value"',
    ];
  }

}
