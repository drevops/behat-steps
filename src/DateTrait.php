<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Gherkin\Node\TableNode;

/**
 * Trait DateTrait.
 *
 * Date-related steps.
 *
 * @package DrevOps\BehatSteps
 */
trait DateTrait {

  /**
   * Transform a scalar value.
   *
   * @Transform :value
   * @Transform :expectedValue
   */
  public function dateRelativeTransformValue(string $value): string|array|null {
    return static::dateRelativeProcessValue($value);
  }

  /**
   * Transform a tabular value.
   *
   * @Transform table:*
   */
  public function dateRelativeTransformTable(TableNode $table): TableNode {
    // Inexpensive token detection and early exit.
    if (!static::dateRelativeStringHasToken($table->getTableAsString())) {
      return $table;
    }

    $rows = [];
    foreach ($table->getRows() as $hash) {
      $row = [];
      foreach ($hash as $column => $cell) {
        $row[$column] = self::dateRelativeProcessValue($cell);
      }
      $rows[] = $row;
    }

    return new TableNode($rows);
  }

  /**
   * Check if sting has a token.
   */
  protected static function dateRelativeStringHasToken(string $string): bool {
    return str_contains($string, '[relative:');
  }

  /**
   * Process date values to convert relative timestamps to actual values.
   *
   * Possible formats:
   * [relative:OFFSET]
   * [relative:OFFSET#FORMAT]
   * - OFFSET|BASETIMESTAMP: any format & base timestamp
   * that can be passed into strtotime().
   * - FORMAT: date() format for additional processing.
   *
   * Examples:
   * [relative:-1 day] would be converted to 1893456000
   * [relative:-1 day#Y-m-d] would be converted to 2017-11-5
   * [relative:-1 day|1720410603#Y-m-d] would be converted to 2024-07-07
   * specific base timestamp.
   *
   * @code
   * Give content "article" exists:
   * | title        | created           |
   * | test article | [relative:-1 day] |
   * @endcode
   *
   * @note Since return value can be a date string, it is possible that
   * asserted result may span across multiple days (i.e. if set as -14 hours).
   * To avoid this, default time is always rounded to midday and it is expected
   * that relative time within a day use max of 12 hours offset.
   */
  public static function dateRelativeProcessValue(string $value, ?int $now = NULL): string|array|null {
    // Inexpensive token detection and early exit.
    if (!static::dateRelativeStringHasToken($value)) {
      return $value;
    }

    // If `now` is not provided, round to the current hour to make sure that
    // assertions are running within the same timeframe (for long tests).
    $now = $now ?: strtotime(date('Y-m-d H:i:s', time()));

    return preg_replace_callback('/\[([relative:]+):([^]\[#]+)(?:#([^]\[]+))?]/', function (array $matches) use ($now) {
      $relative_time_parts = explode('|', $matches[2]);
      $relative_time = $relative_time_parts[0];
      $base_timestamp = $relative_time_parts[1] ?? $now;
      $timestamp = strtotime($relative_time, (int) $base_timestamp);
      if ($timestamp === FALSE) {
        throw new \RuntimeException(sprintf('The supplied relative date cannot be evaluated: "%s"', $matches[1]));
      }
      // Convert to date format, if provided.
      if (isset($matches[3])) {
        $timestamp = date($matches[3], $timestamp);
      }

      if (empty($timestamp)) {
        throw new \RuntimeException(sprintf('The supplied relative date cannot be evaluated: "%s"', $matches[1]));
      }

      return $timestamp;
    }, $value);
  }

}
