<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Driver\Core\Field;

use DrevOps\BehatSteps\Driver\Core\Field\AbstractHandler;
use DrevOps\BehatSteps\Driver\Core\Field\FieldHandlerInterface;
use DrevOps\BehatSteps\Driver\Core\Field\ListStringHandler;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the ListStringHandler field handler.
 *
 * @group fields
 */
#[CoversClass(ListStringHandler::class)]
#[Group('fields')]
class ListStringHandlerTest extends FieldHandlerUnitTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createHandler(): FieldHandlerInterface {
    $field_info = $this->createMock(FieldStorageDefinitionInterface::class);
    $field_info->method('getSetting')
      ->with('allowed_values')
      ->willReturn([
        'red' => 'Red',
        'green' => 'Green',
        'blue' => 'Blue',
      ]);

    $reflection = new \ReflectionClass(ListStringHandler::class);
    $handler = $reflection->newInstanceWithoutConstructor();

    $info_property = new \ReflectionProperty(ListStringHandler::class, 'fieldInfo');
    $info_property->setValue($handler, $field_info);

    $main_property = new \ReflectionProperty(AbstractHandler::class, 'mainProperty');
    $main_property->setValue($handler, 'value');

    return $handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function dataProviderExpand(): \Iterator {
    yield 'list of labels mapped to keys' => [
      ['Green', 'Blue'],
      ['green', 'blue'],
      NULL,
      NULL,
    ];
    yield 'unmatched value passes through' => [
      ['Unknown'],
      ['Unknown'],
      NULL,
      NULL,
    ];
    yield 'scalar label resolves to key' => [
      'Red',
      ['red'],
      NULL,
      NULL,
    ];

    yield 'mixed positional and named keys rejected' => [
      ['Red', 'extra' => 'oops'],
      NULL,
      \InvalidArgumentException::class,
      'Field value cannot mix positional and named keys',
    ];
    yield 'record missing main property rejected' => [
      ['unexpected' => 'oops'],
      NULL,
      \InvalidArgumentException::class,
      'Field record must include the main property "value"',
    ];
  }

}
