<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Drupal\Core\Field;

use DrevOps\BehatSteps\Backend\Drupal\Core\Field\DateRecurHandler;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\date_recur\Plugin\Field\FieldType\DateRecurItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel round-trip test for DateRecurHandler via the Core backend.
 *
 * The 'date_recur' field type (provided by drupal/date_recur) stores 5
 * columns ('value', 'end_value', 'rrule', 'timezone', 'infinite'), which
 * DefaultHandler cannot marshal. This test proves the backend resolves
 * DateRecurHandler for type 'date_recur', that the multi-column storage
 * accepts what the handler emits, and that date_recur computes occurrences
 * at the local times a record names.
 *
 * The 'infinite' column is derived from the rrule by the field type's
 * preSave(), so it is left out of the asserted round-trip.
 */
#[CoversClass(DateRecurHandler::class)]
#[Group('fields')]
#[RunTestsInSeparateProcesses]
class DateRecurHandlerKernelTest extends FieldHandlerKernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = [
    ...self::BASE_MODULES,
    'datetime',
    'datetime_range',
    'date_recur',
  ];

  public function testDateRecurRoundTrip(): void {
    $this->attachField('field_schedule', 'date_recur');

    $this->assertFieldRoundTripViaBackend('field_schedule', [
      [
        'value' => '2025-01-01T09:00:00',
        'end_value' => '2025-01-01T17:00:00',
        'rrule' => 'FREQ=DAILY;COUNT=5',
        'timezone' => 'Australia/Sydney',
      ],
    ]);
  }

  public function testOccurrencesAreAtTheRecordLocalTimes(): void {
    $this->attachField('field_schedule', 'date_recur');

    $item = $this->createScheduleItem([
      'value' => '2025-01-01T09:00:00',
      'end_value' => '2025-01-01T17:00:00',
      'rrule' => 'FREQ=DAILY;COUNT=2',
      'timezone' => 'Australia/Sydney',
    ]);

    $this->assertSame('2024-12-31T22:00:00', $item->get('value')->getValue());

    $occurrences = $item->getHelper()->getOccurrences();
    $this->assertCount(2, $occurrences);
    $this->assertSame('2025-01-01 09:00', $occurrences[0]->getStart()->format('Y-m-d H:i'));
    $this->assertSame('2025-01-02 17:00', $occurrences[1]->getEnd()->format('Y-m-d H:i'));
  }

  public function testMissingTimezoneTakesSiteTimezone(): void {
    $this->config('system.date')->set('timezone.default', 'America/New_York')->save();
    $this->attachField('field_schedule', 'date_recur');

    $item = $this->createScheduleItem([
      'value' => '2025-01-01T09:00:00',
      'end_value' => '2025-01-01T17:00:00',
    ]);

    $this->assertSame('America/New_York', $item->get('timezone')->getValue());
    $this->assertSame('2025-01-01T14:00:00', $item->get('value')->getValue());
    $this->assertSame('2025-01-01 09:00', $item->getHelper()->getOccurrences()[0]->getStart()->format('Y-m-d H:i'));
  }

  public function testDateOnlyRoundTrip(): void {
    $this->attachField('field_schedule', 'date_recur', ['datetime_type' => 'date']);

    $this->assertFieldRoundTripViaBackend('field_schedule', [
      [
        'value' => '2025-01-01',
        'end_value' => '2025-01-01',
        'rrule' => 'FREQ=WEEKLY;COUNT=2',
        'timezone' => 'Australia/Sydney',
      ],
    ]);
  }

  /**
   * Creates an entity through the backend and returns its reloaded item.
   *
   * @param array<string, mixed> $record
   *   The 'field_schedule' record.
   */
  protected function createScheduleItem(array $record): DateRecurItem {
    $stub = new EntityStub(static::ENTITY_TYPE, static::BUNDLE, [
      'name' => 'schedule',
      'field_schedule' => [$record],
    ]);
    $this->core->createEntity($stub);

    $entity = \Drupal::entityTypeManager()->getStorage(static::ENTITY_TYPE)->loadUnchanged($stub->getValue('id'));
    $this->assertInstanceOf(ContentEntityInterface::class, $entity);

    $item = $entity->get('field_schedule')->first();
    $this->assertInstanceOf(DateRecurItem::class, $item);

    return $item;
  }

}
