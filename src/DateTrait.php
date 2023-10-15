<?php

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
   */
  public function dateRelativeTransformValue($value) {
    return static::dateRelativeProcessValue($value);
  }

  /**
   * Transform a tabular value.
   *
   * @Transform table:*
   */
  public function dateRelativeTransformTable(TableNode $table) {
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

    $new_table = new TableNode($rows);

    return $new_table;
  }

  /**
   * Check if sting has a token.
   */
  protected static function dateRelativeStringHasToken($string) {
    return strpos($string, '[relative:') !== FALSE;
  }

  /**
   * Process date values to convert relative timestamps to actual values.
   *
   * Possible formats:
   * [relative:OFFSET]
   * [relative:OFFSET#FORMAT]
   * - OFFSET: any format that can be parsed by strtotime()
   * - FORMAT: date() format for additional processing.
   *
   * Examples:
   * [relative:-1 day] would be converted to 1893456000
   * [relative:-1 day#Y-m-d] would be converted to 2017-11-5
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
  public static function dateRelativeProcessValue($value, $now = NULL) {
    // Inexpensive token detection and early exit.
    if (!static::dateRelativeStringHasToken($value)) {
      return $value;
    }

    // If `now` is not provided, round to the current hour to make sure that
    // assertions are running within the same timeframe (for long tests).
    $now = $now ?: strtotime(date('Y-m-d H:i:00', time()));

    return preg_replace_callback('/\[([relative:]+):([^]\[#]+)(?:#([^]\[]+))?]/', function ($matches) use ($now) {
      $timestamp = strtotime($matches[2], $now);
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
