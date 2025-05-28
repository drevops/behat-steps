<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Gherkin\Node\TableNode;

/**
 * Convert relative date expressions into timestamps or formatted dates.
 *
 * Supports values and tables.
 *
 * Possible formats:
 * - `[relative:OFFSET]`
 * - `[relative:OFFSET#FORMAT]`
 *
 * with:
 * - `OFFSET`: any format that can be parsed by `strtotime()`.
 * - `FORMAT`: `date()` format for additional processing.
 *
 * Examples:
 * - `[relative:-1 day]` converted to `1893456000`
 * - `[relative:-1 day#Y-m-d]` converted to `2017-11-5`
 */
trait DateTrait {

  /**
   * Transform a scalar value.
   *
   * @Transform :datetime
   * @Transform :value
   * @Transform :expectedValue
   */
  public function dateRelativeTransformValue(string $value): string {
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
      foreach ($hash as $cell) {
        $row[] = self::dateRelativeProcessValue($cell);
      }
      $rows[] = $row;
    }

    return new TableNode($rows);
  }

  /**
   * Assert that string has a token.
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
  public static function dateRelativeProcessValue(string $value, ?int $now = NULL): string {
    // Inexpensive token detection and early exit.
    if (!static::dateRelativeStringHasToken($value)) {
      return $value;
    }

    // If `now` is not provided, round to the current hour to make sure that
    // assertions are running within the same timeframe (for long tests).
    $now = $now ?: strtotime(date('Y-m-d H:i:00', self::dateNow()));
    $now = $now ?: NULL;

    return (string) preg_replace_callback('/\[([relative:]+):([^]\[#]+)(?:#([^]\[]+))?]/', function (array $matches) use ($now): string {
      $timestamp = strtotime($matches[2], $now);
      if ($timestamp === FALSE) {
        throw new \RuntimeException(sprintf('The supplied relative date cannot be evaluated: "%s"', $matches[1]));
      }

      // Convert to date format, if provided.
      if (isset($matches[3])) {
        $timestamp = date($matches[3], $timestamp);
      }

      if (empty(trim(strval($timestamp)))) {
        throw new \RuntimeException(sprintf('The supplied relative date cannot be evaluated: "%s"', $matches[1]));
      }

      return (string) $timestamp;
    }, $value);
  }

  /**
   * Get the current timestamp.
   */
  protected static function dateNow(): int {
    // @codeCoverageIgnoreStart
    return time();
    // @codeCoverageIgnoreEnd
  }

}
