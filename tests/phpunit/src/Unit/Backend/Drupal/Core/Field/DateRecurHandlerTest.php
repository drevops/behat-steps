<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Drupal\Core\Field;

use DrevOps\BehatSteps\Backend\Drupal\Core\Field\AbstractHandler;
use DrevOps\BehatSteps\Backend\Drupal\Core\Field\DateRecurHandler;
use DrevOps\BehatSteps\Backend\Drupal\Core\Field\FieldHandlerInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the DateRecurHandler field handler.
 */
#[CoversClass(DateRecurHandler::class)]
#[Group('fields')]
class DateRecurHandlerTest extends FieldHandlerUnitTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    if (!$this->loadDatetimeModuleClasses()) {
      $this->markTestSkipped('drupal/core datetime module classes are not available.');
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function createHandler(): FieldHandlerInterface {
    return $this->createDateRecurHandler('datetime');
  }

  /**
   * {@inheritdoc}
   */
  public static function dataProviderExpand(): \Iterator {
    yield 'full record is relayed as stored' => [
      [
        'value' => '2025-01-01T09:00:00',
        'end_value' => '2025-01-01T17:00:00',
        'rrule' => 'FREQ=DAILY;COUNT=5',
        'timezone' => 'Australia/Sydney',
        'infinite' => 0,
      ],
      [
        [
          'value' => '2025-01-01T09:00:00',
          'end_value' => '2025-01-01T17:00:00',
          'rrule' => 'FREQ=DAILY;COUNT=5',
          'timezone' => 'Australia/Sydney',
          'infinite' => 0,
        ],
      ],
      NULL,
      NULL,
    ];
    yield 'list of records is relayed as stored' => [
      [
        ['value' => '2025-01-01T09:00:00', 'timezone' => 'UTC'],
        ['value' => '2025-06-15T14:30:00', 'timezone' => 'Europe/London'],
      ],
      [
        ['value' => '2025-01-01T09:00:00', 'timezone' => 'UTC'],
        ['value' => '2025-06-15T14:30:00', 'timezone' => 'Europe/London'],
      ],
      NULL,
      NULL,
    ];
    yield 'empty end value becomes NULL' => [
      ['value' => '2025-01-01T09:00:00', 'end_value' => '', 'timezone' => 'UTC'],
      [['value' => '2025-01-01T09:00:00', 'end_value' => NULL, 'timezone' => 'UTC']],
      NULL,
      NULL,
    ];
    yield 'end value alone is relayed as stored' => [
      ['value' => '', 'end_value' => '2025-01-01T17:00:00', 'timezone' => 'Australia/Sydney'],
      [['value' => NULL, 'end_value' => '2025-01-01T17:00:00', 'timezone' => 'Australia/Sydney']],
      NULL,
      NULL,
    ];
    yield 'bare empty value is relayed without a timezone' => [
      '',
      [['value' => '']],
      NULL,
      NULL,
    ];
    yield 'record without dates is relayed unchanged' => [
      ['value' => NULL, 'end_value' => '', 'rrule' => 'FREQ=DAILY;COUNT=5'],
      [['value' => NULL, 'end_value' => '', 'rrule' => 'FREQ=DAILY;COUNT=5']],
      NULL,
      NULL,
    ];

    yield 'bare scalar without a timezone rejected' => [
      '2025-01-01T09:00:00',
      NULL,
      \RuntimeException::class,
      'Date recur field record has a date but no "timezone".',
    ];
    yield 'empty timezone rejected' => [
      ['value' => '2025-01-01T09:00:00', 'timezone' => ''],
      NULL,
      \RuntimeException::class,
      'Date recur field record has a date but no "timezone".',
    ];
    yield 'end value without a timezone rejected' => [
      ['value' => '', 'end_value' => '2025-01-01T17:00:00'],
      NULL,
      \RuntimeException::class,
      'Date recur field record has a date but no "timezone".',
    ];
    yield 'invalid timezone rejected' => [
      ['value' => '2025-01-01T09:00:00', 'timezone' => 'Mars/Olympus'],
      NULL,
      \RuntimeException::class,
      'Date recur field record has an invalid timezone "Mars/Olympus".',
    ];
    yield 'value in UTC by its suffix rejected' => [
      ['value' => '2025-01-01T09:00:00Z', 'timezone' => 'Australia/Sydney'],
      NULL,
      \RuntimeException::class,
      'Date recur field record has the "value" column "2025-01-01T09:00:00Z", which is not in the storage format "Y-m-d\TH:i:s".',
    ];
    yield 'value with an offset rejected' => [
      ['value' => '2025-01-01T09:00:00+02:00', 'timezone' => 'Australia/Sydney'],
      NULL,
      \RuntimeException::class,
      'Date recur field record has the "value" column "2025-01-01T09:00:00+02:00", which is not in the storage format "Y-m-d\TH:i:s".',
    ];
    yield 'value with a space separator rejected' => [
      ['value' => '2025-01-01 09:00:00', 'timezone' => 'UTC'],
      NULL,
      \RuntimeException::class,
      'Date recur field record has the "value" column "2025-01-01 09:00:00", which is not in the storage format "Y-m-d\TH:i:s".',
    ];
    yield 'relative value rejected' => [
      ['value' => 'relative:+1 day', 'timezone' => 'UTC'],
      NULL,
      \RuntimeException::class,
      'Date recur field record has the "value" column "relative:+1 day", which is not in the storage format "Y-m-d\TH:i:s".',
    ];
    yield 'value out of range rejected' => [
      ['value' => '2025-02-30T09:00:00', 'timezone' => 'UTC'],
      NULL,
      \RuntimeException::class,
      'Date recur field record has the "value" column "2025-02-30T09:00:00", which is not in the storage format "Y-m-d\TH:i:s".',
    ];
    yield 'end value not in the storage format rejected' => [
      ['value' => '2025-01-01T09:00:00', 'end_value' => '2025-01-01', 'timezone' => 'UTC'],
      NULL,
      \RuntimeException::class,
      'Date recur field record has the "end_value" column "2025-01-01", which is not in the storage format "Y-m-d\TH:i:s".',
    ];
    yield 'mixed positional and named keys rejected' => [
      ['2025-01-01T09:00:00', 'timezone' => 'UTC'],
      NULL,
      \RuntimeException::class,
      'Field value cannot mix positional and named keys',
    ];
    yield 'record missing main property rejected' => [
      ['timezone' => 'UTC', 'rrule' => 'FREQ=DAILY;COUNT=5'],
      NULL,
      \RuntimeException::class,
      'Field record must include the main property "value"',
    ];
  }

  /**
   * Tests that a date-only field relays dates in the date storage format.
   *
   * @param mixed $input
   *   The loose input fed to 'expand()'.
   * @param array<int, array<string, mixed>>|null $expected
   *   The expected storage-shape output, or NULL when an exception is
   *   expected.
   * @param string|null $expected_message
   *   The expected exception message, or NULL when none is expected.
   */
  #[DataProvider('dataProviderExpandDateOnly')]
  public function testExpandDateOnly(mixed $input, ?array $expected, ?string $expected_message): void {
    if ($expected_message !== NULL) {
      $this->expectException(\RuntimeException::class);
      $this->expectExceptionMessage($expected_message);
    }

    $this->assertSame($expected, $this->createDateRecurHandler('date')->expand($input));
  }

  /**
   * Data provider for 'testExpandDateOnly()'.
   *
   * @return \Iterator<string, array{mixed, array<int, array<string, mixed>>|null, string|null}>
   *   Rows of: input, expected storage shape, expected exception message.
   */
  public static function dataProviderExpandDateOnly(): \Iterator {
    yield 'date is relayed as stored' => [
      ['value' => '2025-01-01', 'end_value' => '2025-01-02', 'timezone' => 'Australia/Sydney'],
      [['value' => '2025-01-01', 'end_value' => '2025-01-02', 'timezone' => 'Australia/Sydney']],
      NULL,
    ];
    yield 'date and time rejected' => [
      ['value' => '2025-01-01T09:00:00', 'timezone' => 'Australia/Sydney'],
      NULL,
      'Date recur field record has the "value" column "2025-01-01T09:00:00", which is not in the storage format "Y-m-d".',
    ];
  }

  /**
   * Creates the handler for a field of the given datetime type.
   *
   * @param string $datetime_type
   *   The field storage's 'datetime_type' setting: 'datetime' or 'date'.
   */
  protected function createDateRecurHandler(string $datetime_type): FieldHandlerInterface {
    $field_info = $this->createMock(FieldStorageDefinitionInterface::class);
    $field_info->method('getSetting')->with('datetime_type')->willReturn($datetime_type);

    $reflection = new \ReflectionClass(DateRecurHandler::class);
    $handler = $reflection->newInstanceWithoutConstructor();

    $info_property = new \ReflectionProperty(AbstractHandler::class, 'fieldInfo');
    $info_property->setValue($handler, $field_info);

    $main_property = new \ReflectionProperty(AbstractHandler::class, 'mainProperty');
    $main_property->setValue($handler, 'value');

    return $handler;
  }

}
