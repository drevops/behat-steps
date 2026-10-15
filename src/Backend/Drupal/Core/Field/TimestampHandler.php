<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Drupal\Core\Field;

/**
 * Field handler for 'timestamp' fields.
 *
 * A date 'strtotime()' reads, such as '2014-10-17 8:00am' or '-1 hour',
 * becomes a Unix timestamp. A numeric, empty or NULL value is stored as given.
 */
class TimestampHandler extends AbstractHandler {

  /**
   * {@inheritdoc}
   */
  protected function doExpand(array $records): array {
    $timestamps = [];

    foreach ($records as $record) {
      $value = $record['value'];

      if ($value === NULL || $value === '' || is_numeric($value)) {
        $timestamps[] = $value;
        continue;
      }

      if (!is_string($value)) {
        throw new \RuntimeException(sprintf('Timestamp field value of type "%s" is not parseable.', get_debug_type($value)));
      }

      $timestamp = strtotime($value);

      if ($timestamp === FALSE) {
        throw new \RuntimeException(sprintf('Timestamp field value "%s" is not parseable.', $value));
      }

      $timestamps[] = $timestamp;
    }

    return $timestamps;
  }

}
