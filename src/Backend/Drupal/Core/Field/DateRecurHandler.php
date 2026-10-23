<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Drupal\Core\Field;

use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

/**
 * Field handler for 'date_recur' fields (date_recur contrib module).
 *
 * The base normalize() folds the bare-scalar 'value' shorthand and keyed
 * multi-column records ('value', 'end_value', 'rrule', 'timezone',
 * 'infinite').
 *
 * date_recur reads 'value' and 'end_value' as UTC and computes occurrences in
 * the record's 'timezone'. The handler relays both columns as given, so a
 * record holds the stored UTC values and names its 'timezone'.
 *
 * A record with a date and no 'timezone', or with a date that is not in the
 * storage format, is rejected, because date_recur would compute no
 * occurrences or read a different time.
 *
 * The field type's preSave() derives 'infinite' from the rrule, so 'rrule'
 * and 'infinite' are relayed unchanged.
 *
 * @see https://www.drupal.org/project/date_recur
 */
class DateRecurHandler extends AbstractHandler {

  /**
   * {@inheritdoc}
   */
  protected function doExpand(array $records): array {
    $format = $this->fieldInfo->getSetting('datetime_type') === DateTimeItem::DATETIME_TYPE_DATE ? DateTimeItemInterface::DATE_STORAGE_FORMAT : DateTimeItemInterface::DATETIME_STORAGE_FORMAT;
    $expanded = [];

    foreach ($records as $record) {
      // A record without dates keeps an empty 'timezone', so date_recur still
      // treats it as an empty item.
      if (in_array($record['value'] ?? NULL, [NULL, ''], TRUE) && in_array($record['end_value'] ?? NULL, [NULL, ''], TRUE)) {
        $expanded[] = $record;

        continue;
      }

      $this->assertRecordTimezone($record['timezone'] ?? NULL);

      foreach (['value', 'end_value'] as $column) {
        if (!array_key_exists($column, $record)) {
          continue;
        }

        if (in_array($record[$column], [NULL, ''], TRUE)) {
          $record[$column] = NULL;

          continue;
        }

        $this->assertStorageFormat($column, (string) $record[$column], $format);
      }

      $expanded[] = $record;
    }

    return $expanded;
  }

  /**
   * Asserts that a record with a date names a valid timezone.
   *
   * @param mixed $timezone
   *   The record's 'timezone' column.
   *
   * @throws \RuntimeException
   *   Thrown when the timezone is empty or PHP does not recognize it.
   */
  protected function assertRecordTimezone(mixed $timezone): void {
    if (in_array($timezone, [NULL, ''], TRUE)) {
      throw new \RuntimeException('Date recur field record has a date but no "timezone".');
    }

    try {
      new \DateTimeZone((string) $timezone);
    }
    catch (\Exception $exception) {
      throw new \RuntimeException(sprintf('Date recur field record has an invalid timezone "%s".', $timezone), 0, $exception);
    }
  }

  /**
   * Asserts that a date column holds a value in the storage format.
   *
   * @param string $column
   *   The column name: 'value' or 'end_value'.
   * @param string $value
   *   The column value.
   * @param string $format
   *   The storage format of the field's datetime type.
   *
   * @throws \RuntimeException
   *   Thrown when the value does not parse in the format or carries more
   *   than the format holds, such as a timezone offset.
   */
  protected function assertStorageFormat(string $column, string $value, string $format): void {
    $date = \DateTime::createFromFormat('!' . $format, $value, new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));

    if ($date === FALSE || $date->format($format) !== $value) {
      throw new \RuntimeException(sprintf('Date recur field record has the "%s" column "%s", which is not in the storage format "%s".', $column, $value, $format));
    }
  }

}
