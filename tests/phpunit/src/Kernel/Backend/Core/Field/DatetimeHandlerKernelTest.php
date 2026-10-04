<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Core\Field;

use DrevOps\BehatSteps\Backend\Core\Field\DatetimeHandler;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel round-trip test for datetime fields via the Core backend.
 *
 * Core::entityCreate resolves DatetimeHandler through its lookup chain, real
 * datetime field storage accepts the handler's output, and the stored value
 * round-trips unchanged.
 */
#[CoversClass(DatetimeHandler::class)]
#[Group('fields')]
#[RunTestsInSeparateProcesses]
class DatetimeHandlerKernelTest extends FieldHandlerKernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = [
    ...self::BASE_MODULES,
    'datetime',
  ];

  /**
   * Tests round-trip for a datetime field (with time component).
   *
   * Input matches the canonical handler contract: a list of records keyed
   * by datetime column name ('value').
   */
  public function testDatetimeRoundTrip(): void {
    $this->attachField('field_event_date', 'datetime', [
      'datetime_type' => DateTimeItem::DATETIME_TYPE_DATETIME,
    ]);
    $this->assertFieldRoundTripViaBackend('field_event_date', [
      ['value' => '2026-07-15T10:00:00'],
    ]);
  }

  public function testDateOnlyRoundTrip(): void {
    $this->attachField('field_birthday', 'datetime', [
      'datetime_type' => DateTimeItem::DATETIME_TYPE_DATE,
    ]);
    $this->assertFieldRoundTripViaBackend('field_birthday', [
      ['value' => '2026-07-15'],
    ]);
  }

  /**
   * Tests the 'relative:' prefix shorthand resolves to a concrete timestamp.
   */
  public function testRelativePrefixIsResolved(): void {
    $this->attachField('field_seen', 'datetime', [
      'datetime_type' => DateTimeItem::DATETIME_TYPE_DATETIME,
    ]);

    $stub = new EntityStub(self::ENTITY_TYPE, self::BUNDLE, [
      'name' => 'relative-date',
      'field_seen' => [['value' => 'relative:2026-01-02 03:04:05']],
    ]);
    $this->core->entityCreate($stub);

    // The 'relative:' prefix is stripped before parsing, so the stored value
    // equals the one a plain timestamp produces.
    $this->assertSame([['value' => '2026-01-02T03:04:05']], $stub->getValue('field_seen'));
  }

}
