<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Drupal\Core\Field;

use DrevOps\BehatSteps\Backend\Drupal\Core\Field\TimestampHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel round-trip test for TimestampHandler via the Core backend.
 *
 * TimestampHandler reads a date 'strtotime()' accepts into a Unix timestamp
 * and stores a numeric value as given.
 */
#[CoversClass(TimestampHandler::class)]
#[Group('fields')]
#[RunTestsInSeparateProcesses]
class TimestampHandlerKernelTest extends FieldHandlerKernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = [
    ...self::BASE_MODULES,
  ];

  public function testTimestampRoundTrip(): void {
    $this->attachField('field_when', 'timestamp');

    $this->assertFieldRoundTripViaBackend('field_when', ['2014-10-17 8:00am', 1732319174]);
  }

}
