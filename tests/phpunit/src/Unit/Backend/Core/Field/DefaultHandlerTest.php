<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Core\Field;

use DrevOps\BehatSteps\Backend\Core\Field\AbstractHandler;
use DrevOps\BehatSteps\Backend\Core\Field\DefaultHandler;
use DrevOps\BehatSteps\Backend\Core\Field\FieldHandlerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the DefaultHandler field handler.
 *
 * DefaultHandler is a pure pass-through: it relays the normalized records to
 * storage unchanged. 'Core' rejects fields the default cannot marshal before it
 * resolves this handler, so that classification is exercised in FieldClassifier
 * and Core, not here.
 */
#[CoversClass(DefaultHandler::class)]
#[Group('fields')]
class DefaultHandlerTest extends FieldHandlerUnitTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createHandler(): FieldHandlerInterface {
    return $this->createHandlerWithMainProperty('value');
  }

  /**
   * {@inheritdoc}
   */
  public static function dataProviderExpand(): \Iterator {
    yield 'bare scalar' => [
      'hello',
      [['value' => 'hello']],
      NULL,
      NULL,
    ];
    yield 'list of scalars' => [
      ['one', 'two'],
      [['value' => 'one'], ['value' => 'two']],
      NULL,
      NULL,
    ];
    yield 'records pass through unchanged' => [
      [['value' => 'one'], ['value' => 'two']],
      [['value' => 'one'], ['value' => 'two']],
      NULL,
      NULL,
    ];
    yield 'multi-column scalar record passes through unchanged' => [
      [['value' => 'label', 'format' => 'plain_text']],
      [['value' => 'label', 'format' => 'plain_text']],
      NULL,
      NULL,
    ];
    yield 'integer scalar' => [
      42,
      [['value' => 42]],
      NULL,
      NULL,
    ];

    yield 'mixed positional and named keys rejected' => [
      ['hello', 'extra' => 'unexpected'],
      NULL,
      \RuntimeException::class,
      'Field value cannot mix positional and named keys',
    ];
    yield 'record missing main property rejected' => [
      ['unexpected' => 'oops'],
      NULL,
      \RuntimeException::class,
      'Field record must include the main property "value"',
    ];
  }

  /**
   * Builds a DefaultHandler with only its main property set.
   *
   * DefaultHandler's pass-through 'doExpand()' touches no field metadata, so
   * the handler needs only the main property the base 'normalize()' reads.
   */
  protected function createHandlerWithMainProperty(string $main_property): DefaultHandler {
    $handler = (new \ReflectionClass(DefaultHandler::class))->newInstanceWithoutConstructor();

    $property = new \ReflectionProperty(AbstractHandler::class, 'mainProperty');
    $property->setValue($handler, $main_property);

    return $handler;
  }

}
