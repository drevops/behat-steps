<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Drupal\Core\Field;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

/**
 * Field handler for 'datetime' fields.
 */
class DatetimeHandler extends AbstractHandler {

  /**
   * {@inheritdoc}
   */
  protected function doExpand(array $records): array {
    $site_timezone = $this->getSiteTimezone();
    $storage_timezone = new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);
    $formatted = [];

    foreach ($records as $record) {
      $formatted[] = [
        'value' => $this->formatDateValue($record['value'] ?? NULL, $site_timezone, $storage_timezone),
      ];
    }

    return $formatted;
  }

  /**
   * Returns the site's default timezone, or UTC when none is configured.
   */
  protected function getSiteTimezone(): \DateTimeZone {
    return new \DateTimeZone(\Drupal::config('system.date')->get('timezone.default') ?: 'UTC');
  }

  /**
   * Formats a date value for storage based on the field's datetime_type.
   *
   * @param string|null $value
   *   The raw date value, optionally prefixed with "relative:".
   * @param \DateTimeZone $timezone
   *   The timezone the value is written in.
   * @param \DateTimeZone $storage_timezone
   *   The storage timezone.
   *
   * @return string|null
   *   The formatted date string, or null for empty values.
   */
  protected function formatDateValue(?string $value, \DateTimeZone $timezone, \DateTimeZone $storage_timezone): ?string {
    if ($value === NULL || $value === '') {
      return NULL;
    }

    if (str_contains($value, 'relative:')) {
      $value = trim(str_replace('relative:', '', $value));
    }

    $is_date_only = $this->fieldInfo->getSetting('datetime_type') === DateTimeItem::DATETIME_TYPE_DATE;

    $date = new DrupalDateTime($value, $timezone);

    if ($is_date_only) {
      return $date->format(DateTimeItemInterface::DATE_STORAGE_FORMAT);
    }

    $date->setTimezone($storage_timezone);

    return $date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
  }

}
