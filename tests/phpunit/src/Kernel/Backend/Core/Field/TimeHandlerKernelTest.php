<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Core\Field;

use DrevOps\BehatSteps\Backend\Core\Field\TimeHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel round-trip test for TimeHandler via the Core backend.
 *
 * TimeHandler accepts a numeric seconds-past-midnight value or a parseable
 * time string (e.g. "9:30 AM") and emits the storage integer.
 * The 'time' field type is provided by drupal/time_field.
 */
#[CoversClass(TimeHandler::class)]
#[Group('fields')]
#[RunTestsInSeparateProcesses]
class TimeHandlerKernelTest extends FieldHandlerKernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = [
    ...self::BASE_MODULES,
    'time_field',
  ];

  public function testTimeNumericRoundTrip(): void {
    $this->attachField('field_start', 'time');

    // 9:30 AM = 9 * 3600 + 30 * 60 = 34200 seconds past midnight.
    $this->assertFieldRoundTripViaBackend('field_start', [34200]);
  }

}
