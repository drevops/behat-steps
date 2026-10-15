<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Drupal\Core\Field;

use DrevOps\BehatSteps\Backend\Drupal\Core\Field\AbstractHandler;
use DrevOps\BehatSteps\Backend\Drupal\Core\Field\FieldHandlerInterface;
use DrevOps\BehatSteps\Backend\Drupal\Core\Field\TimestampHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the TimestampHandler field handler.
 */
#[CoversClass(TimestampHandler::class)]
#[Group('fields')]
class TimestampHandlerTest extends FieldHandlerUnitTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createHandler(): FieldHandlerInterface {
    $reflection = new \ReflectionClass(TimestampHandler::class);
    $handler = $reflection->newInstanceWithoutConstructor();

    $property = new \ReflectionProperty(AbstractHandler::class, 'mainProperty');
    $property->setValue($handler, 'value');

    return $handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function dataProviderExpand(): \Iterator {
    yield 'numeric integer passes through' => [[1732319174], [1732319174], NULL, NULL];
    yield 'numeric string passes through' => [['1732319174'], ['1732319174'], NULL, NULL];
    yield 'bare numeric string' => ['1732319174', ['1732319174'], NULL, NULL];
    yield 'empty string passes through' => [[''], [''], NULL, NULL];
    yield 'null passes through' => [[['value' => NULL]], [NULL], NULL, NULL];
    yield 'a date' => [['1 January 2025 UTC'], [strtotime('1 January 2025 UTC')], NULL, NULL];
    yield 'a date and time' => [['2014-10-17 8:00am'], [strtotime('2014-10-17 8:00am')], NULL, NULL];
    yield 'a keyed record' => [['value' => '1 January 2025 UTC'], [strtotime('1 January 2025 UTC')], NULL, NULL];
    yield 'a mixed list of a timestamp and a date' => [[1732319174, '1 January 2025 UTC'], [1732319174, strtotime('1 January 2025 UTC')], NULL, NULL];
    yield 'mixed positional and named keys rejected' => [[1732319174, 'extra' => 'unexpected'], NULL, \RuntimeException::class, 'Field value cannot mix positional and named keys'];
    yield 'record missing main property rejected' => [['unexpected' => 'oops'], NULL, \RuntimeException::class, 'Field record must include the main property "value"'];
    yield 'unparseable string rejected' => [['not a date at all'], NULL, \RuntimeException::class, 'Timestamp field value "not a date at all" is not parseable.'];
    yield 'boolean rejected' => [[FALSE], NULL, \RuntimeException::class, 'Timestamp field value of type "bool" is not parseable.'];
    yield 'nested array rejected' => [[['value' => ['nested']]], NULL, \RuntimeException::class, 'Timestamp field value of type "array" is not parseable.'];
  }

}
