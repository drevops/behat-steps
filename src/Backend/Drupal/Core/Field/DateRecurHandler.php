<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Drupal\Core\Field;

use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

/**
 * Field handler for 'date_recur' fields (date_recur contrib module).
 *
 * The base normalize() folds the bare-scalar 'value' shorthand and keyed
 * multi-column records ('value', 'end_value', 'rrule', 'timezone',
 * 'infinite').
 *
 * date_recur stores 'value' and 'end_value' in UTC and computes occurrences
 * in the record's 'timezone'. Both columns are read in that timezone, so a
 * record's times are local times.
 *
 * A record with a date but no 'timezone' takes the site timezone, because
 * date_recur computes no occurrences without one.
 *
 * The field type's preSave() derives 'infinite' from the rrule, so 'rrule'
 * and 'infinite' are relayed unchanged.
 *
 * @see https://www.drupal.org/project/date_recur
 */
class DateRecurHandler extends DatetimeHandler {

  /**
   * {@inheritdoc}
   */
  protected function doExpand(array $records): array {
    $site_timezone = $this->getSiteTimezone()->getName();
    $storage_timezone = new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);
    $expanded = [];

    foreach ($records as $record) {
      $value = $record['value'] ?? NULL;
      $end_value = $record['end_value'] ?? NULL;

      // A record without dates keeps an empty 'timezone', so date_recur still
      // treats it as an empty item.
      if (in_array($value, [NULL, ''], TRUE) && in_array($end_value, [NULL, ''], TRUE)) {
        $expanded[] = $record;

        continue;
      }

      if (in_array($record['timezone'] ?? NULL, [NULL, ''], TRUE)) {
        $record['timezone'] = $site_timezone;
      }

      $timezone = $this->createRecordTimezone((string) $record['timezone']);
      $record['value'] = $this->formatDateValue($value, $timezone, $storage_timezone);

      if (array_key_exists('end_value', $record)) {
        $record['end_value'] = $this->formatDateValue($end_value, $timezone, $storage_timezone);
      }

      $expanded[] = $record;
    }

    return $expanded;
  }

  /**
   * Creates the timezone a record's dates are written in.
   *
   * @param string $name
   *   The timezone the record names.
   *
   * @return \DateTimeZone
   *   The timezone.
   *
   * @throws \RuntimeException
   *   Thrown when PHP does not recognize the name as a timezone.
   */
  protected function createRecordTimezone(string $name): \DateTimeZone {
    try {
      return new \DateTimeZone($name);
    }
    catch (\Exception $exception) {
      throw new \RuntimeException(sprintf('Date recur field record has an invalid timezone "%s".', $name), 0, $exception);
    }
  }

}
