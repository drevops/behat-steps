<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Driver\Core\Field;

use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

/**
 * Field handler for 'daterange' fields.
 */
class DaterangeHandler extends DatetimeHandler {

  /**
   * {@inheritdoc}
   */
  protected function normalize(mixed $values): array {
    if (!is_array($values) || $values === []) {
      return [];
    }

    if (!$this->isListOfRecords($values)) {
      $values = [$values];
    }

    $records = [];

    foreach ($values as $value) {
      if (!is_array($value)) {
        throw new \RuntimeException(sprintf(
          'Daterange field record must be an array (positional [start, end] or keyed value/end_value). Got %s.',
          get_debug_type($value),
        ));
      }

      $records[] = [
        'value' => $value['value'] ?? $value[0] ?? NULL,
        'end_value' => $value['end_value'] ?? $value[1] ?? NULL,
      ];
    }

    return $records;
  }

  /**
   * {@inheritdoc}
   */
  protected function doExpand(array $records): array {
    $site_timezone = new \DateTimeZone(\Drupal::config('system.date')->get('timezone.default') ?: 'UTC');
    $storage_timezone = new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);
    $result = [];

    foreach ($records as $record) {
      $start = $record['value'];
      $end = $record['end_value'];

      $result[] = [
        'value' => $start ? $this->formatDateValue($start, $site_timezone, $storage_timezone) : NULL,
        'end_value' => $end ? $this->formatDateValue($end, $site_timezone, $storage_timezone) : NULL,
      ];
    }

    return $result;
  }

}
