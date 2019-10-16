<?php

namespace IntegratedExperts\BehatSteps;

/**
 * Trait DateTrait.
 *
 * @package IntegratedExperts\BehatSteps
 */
trait DateTrait {

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
   * @note: Since return value can be a date string, it is possible that
   * asserted result may span across multiple days (i.e. if set as -14 hours).
   * To avoid this, default time is always rounded to midday and it is expected
   * that relative time within a day use max of 12 hours offset.
   */
  public static function dateProcess($value, $now = NULL) {
    // If `now` is not provided, round to the current hour to make sure that
    // assertions are running within the same timeframe (for long tests).
    $now = $now ? $now : strtotime(date('Y-m-d H:i:00', time()));

    return preg_replace_callback('/\[([^:]+):([^\]\[\#]+)(?:\#([^\]\[]+))?\]/', function ($matches) use ($now) {
      $timestamp = strtotime($matches[2], $now);
      if ($timestamp === FALSE) {
        throw new \RuntimeException(sprintf('The supplied relative date cannot be evaluated: "%s"', $matches[1]));
      }
      // Convert to date format, if provided.
      if (isset($matches[3])) {
        $timestamp = date($matches[3], $timestamp);
      }

      if ($timestamp === FALSE) {
        throw new \RuntimeException(sprintf('The supplied relative date cannot be evaluated: "%s"', $matches[1]));
      }

      return $timestamp;
    }, $value);
  }

}
