<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Core\Field;

use DrevOps\BehatSteps\Backend\Core\Field\FieldHandlerInterface;
use DrevOps\BehatSteps\Backend\Core\Field\NameHandler;
use Drupal\Core\Field\FieldDefinitionInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the NameHandler field handler.
 */
#[CoversClass(NameHandler::class)]
#[Group('fields')]
class NameHandlerTest extends FieldHandlerUnitTestBase {

  /**
   * All 6 name components, all enabled.
   *
   * @var array<string, bool>
   */
  protected const ALL_ENABLED = [
    NameHandler::COMPONENT_TITLE => TRUE,
    NameHandler::COMPONENT_GIVEN => TRUE,
    NameHandler::COMPONENT_MIDDLE => TRUE,
    NameHandler::COMPONENT_FAMILY => TRUE,
    NameHandler::COMPONENT_GENERATIONAL => TRUE,
    NameHandler::COMPONENT_CREDENTIALS => TRUE,
  ];

  /**
   * {@inheritdoc}
   */
  protected function createHandler(): FieldHandlerInterface {
    return $this->createHandlerWithComponents(static::ALL_ENABLED);
  }

  /**
   * {@inheritdoc}
   */
  public static function dataProviderExpand(): \Iterator {
    yield 'family-only shorthand string' => [
      'Doe',
      [['family' => 'Doe', 'given' => NULL]],
      NULL,
      NULL,
    ];
    yield 'family-given shorthand string' => [
      'Doe, John',
      [['family' => 'Doe', 'given' => 'John']],
      NULL,
      NULL,
    ];
    yield 'list with shorthand string' => [
      ['Doe, John'],
      [['family' => 'Doe', 'given' => 'John']],
      NULL,
      NULL,
    ];
    yield 'list with single keyed record' => [
      [['given' => 'John', 'family' => 'Doe', 'middle' => 'Q']],
      [['given' => 'John', 'family' => 'Doe', 'middle' => 'Q']],
      NULL,
      NULL,
    ];
    yield 'list with positional array' => [
      [['Dr', 'John', 'Quincy', 'Doe']],
      [['title' => 'Dr', 'given' => 'John', 'middle' => 'Quincy', 'family' => 'Doe']],
      NULL,
      NULL,
    ];
    yield 'multi-delta mixed shorthand and keyed' => [
      [
        'Doe, John',
        ['given' => 'Jane', 'family' => 'Smith'],
      ],
      [
        ['family' => 'Doe', 'given' => 'John'],
        ['given' => 'Jane', 'family' => 'Smith'],
      ],
      NULL,
      NULL,
    ];
    yield 'keyed record auto-wrapped' => [
      ['given' => 'Ada', 'family' => 'Lovelace'],
      [['given' => 'Ada', 'family' => 'Lovelace']],
      NULL,
      NULL,
    ];

    yield 'mixed positional and named keys rejected' => [
      [['John', 'family' => 'Smith']],
      NULL,
      \RuntimeException::class,
      'Cannot mix positional and named keys in the same name value',
    ];
    yield 'unknown sub-field key rejected' => [
      [['nickname' => 'Johnny']],
      NULL,
      \RuntimeException::class,
      'Invalid name sub-field key: nickname.',
    ];
    yield 'non-string non-array value rejected' => [
      42,
      NULL,
      \RuntimeException::class,
      'Name field value must be a string or an array. Got int.',
    ];
    yield 'non-string non-array delta rejected' => [
      ['Doe, John', 42],
      NULL,
      \RuntimeException::class,
      'Name field delta 1 must be a string or an array. Got int.',
    ];
  }

  public function testNumericIndicesSkipDisabledComponents(): void {
    $handler = $this->createHandlerWithComponents([
      NameHandler::COMPONENT_TITLE => TRUE,
      NameHandler::COMPONENT_GIVEN => TRUE,
      NameHandler::COMPONENT_MIDDLE => FALSE,
      NameHandler::COMPONENT_FAMILY => TRUE,
      NameHandler::COMPONENT_GENERATIONAL => FALSE,
      NameHandler::COMPONENT_CREDENTIALS => FALSE,
    ]);

    $this->assertSame(
      [['title' => 'Dr', 'given' => 'John', 'family' => 'Doe']],
      $handler->expand([['Dr', 'John', 'Doe']]),
    );
  }

  public function testTooManyNumericIndicesThrows(): void {
    $handler = $this->createHandlerWithComponents([
      NameHandler::COMPONENT_TITLE => FALSE,
      NameHandler::COMPONENT_GIVEN => TRUE,
      NameHandler::COMPONENT_MIDDLE => FALSE,
      NameHandler::COMPONENT_FAMILY => TRUE,
      NameHandler::COMPONENT_GENERATIONAL => FALSE,
      NameHandler::COMPONENT_CREDENTIALS => FALSE,
    ]);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Too many name sub-field values supplied; only 2 enabled components available.');

    $handler->expand([['John', 'Doe', 'Extra']]);
  }

  public function testNamedKeyForDisabledComponentThrows(): void {
    $handler = $this->createHandlerWithComponents([
      NameHandler::COMPONENT_TITLE => FALSE,
      NameHandler::COMPONENT_GIVEN => TRUE,
      NameHandler::COMPONENT_MIDDLE => FALSE,
      NameHandler::COMPONENT_FAMILY => TRUE,
      NameHandler::COMPONENT_GENERATIONAL => FALSE,
      NameHandler::COMPONENT_CREDENTIALS => FALSE,
    ]);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Cannot set the "middle" name component because it is disabled on this field.');

    $handler->expand([['given' => 'John', 'middle' => 'Q', 'family' => 'Doe']]);
  }

  public function testShorthandThrowsWhenFamilyDisabled(): void {
    $handler = $this->createHandlerWithComponents([
      NameHandler::COMPONENT_TITLE => TRUE,
      NameHandler::COMPONENT_GIVEN => TRUE,
      NameHandler::COMPONENT_MIDDLE => FALSE,
      NameHandler::COMPONENT_FAMILY => FALSE,
      NameHandler::COMPONENT_GENERATIONAL => FALSE,
      NameHandler::COMPONENT_CREDENTIALS => FALSE,
    ]);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Cannot use the "Family, Given" shorthand because the "family" component is disabled on this field.');

    $handler->expand('Doe, John');
  }

  public function testShorthandThrowsWhenGivenPartSuppliedButDisabled(): void {
    $handler = $this->createHandlerWithComponents([
      NameHandler::COMPONENT_TITLE => FALSE,
      NameHandler::COMPONENT_GIVEN => FALSE,
      NameHandler::COMPONENT_MIDDLE => FALSE,
      NameHandler::COMPONENT_FAMILY => TRUE,
      NameHandler::COMPONENT_GENERATIONAL => FALSE,
      NameHandler::COMPONENT_CREDENTIALS => FALSE,
    ]);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Cannot use the "Family, Given" shorthand because the "given" component is disabled on this field.');

    $handler->expand('Doe, John');
  }

  public function testShorthandFamilyOnlyWhenGivenDisabled(): void {
    $handler = $this->createHandlerWithComponents([
      NameHandler::COMPONENT_TITLE => FALSE,
      NameHandler::COMPONENT_GIVEN => FALSE,
      NameHandler::COMPONENT_MIDDLE => FALSE,
      NameHandler::COMPONENT_FAMILY => TRUE,
      NameHandler::COMPONENT_GENERATIONAL => FALSE,
      NameHandler::COMPONENT_CREDENTIALS => FALSE,
    ]);

    $this->assertSame([['family' => 'Doe']], $handler->expand('Doe'));
  }

  /**
   * Creates a NameHandler with the given components map injected.
   *
   * @param array<string, bool> $components
   *   Map of component name to enabled flag.
   */
  protected function createHandlerWithComponents(array $components): NameHandler {
    $field_config = $this->createMock(FieldDefinitionInterface::class);
    $field_config->method('getSettings')->willReturn(['components' => $components]);

    $reflection = new \ReflectionClass(NameHandler::class);
    $handler = $reflection->newInstanceWithoutConstructor();

    $property = new \ReflectionProperty(NameHandler::class, 'fieldConfig');
    $property->setValue($handler, $field_config);

    return $handler;
  }

}
