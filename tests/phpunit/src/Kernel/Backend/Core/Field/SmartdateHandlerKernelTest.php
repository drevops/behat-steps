<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Core\Field;

use DrevOps\BehatSteps\Backend\Core\Field\SmartdateHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel round-trip test for SmartdateHandler via the Core backend.
 *
 * SmartdateHandler emits a six-column payload ('value', 'end_value',
 * 'duration', 'rrule', 'rrule_index', 'timezone'). This test proves the
 * backend resolves SmartdateHandler for type 'smartdate' and that the
 * multi-column storage accepts what the handler emits. The 'smartdate' field
 * type is provided by drupal/smart_date.
 */
#[CoversClass(SmartdateHandler::class)]
#[Group('fields')]
#[RunTestsInSeparateProcesses]
class SmartdateHandlerKernelTest extends FieldHandlerKernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = [
    ...self::BASE_MODULES,
    'datetime',
    'options',
    'smart_date',
  ];

  /**
   * Tests round-trip for a smartdate field with numeric Unix timestamps.
   */
  public function testSmartdateRoundTrip(): void {
    $this->attachField('field_event', 'smartdate');

    // 2026-07-15T09:00:00 UTC = 1784106000.
    // 2026-07-15T17:00:00 UTC = 1784134800.
    // Duration: (1784134800 - 1784106000) / 60 = 480 minutes.
    $this->assertFieldRoundTripViaBackend('field_event', [
      [
        'value' => 1784106000,
        'end_value' => 1784134800,
        'duration' => 480,
        'timezone' => 'UTC',
      ],
    ]);
  }

}
