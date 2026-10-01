<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Core\Field;

use DrevOps\BehatSteps\Backend\Core\Field\DaterangeHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel round-trip test for DaterangeHandler via the Core backend.
 *
 * DaterangeHandler extends DatetimeHandler and emits a multi-property payload
 * ({value, end_value}). This test proves the backend resolves DaterangeHandler
 * for type 'daterange' and that the dual-column storage accepts what the
 * handler emits.
 */
#[CoversClass(DaterangeHandler::class)]
#[Group('fields')]
#[RunTestsInSeparateProcesses]
class DaterangeHandlerKernelTest extends FieldHandlerKernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = [
    ...self::BASE_MODULES,
    'datetime',
    'datetime_range',
  ];

  /**
   * Tests round-trip for a daterange field with start and end datetimes.
   */
  public function testDaterangeRoundTrip(): void {
    $this->attachField('field_event_window', 'daterange', [
      'datetime_type' => 'datetime',
    ]);

    $this->assertFieldRoundTripViaBackend('field_event_window', [
      [
        'value' => '2026-07-15T09:00:00',
        'end_value' => '2026-07-15T17:00:00',
      ],
    ]);
  }

}
