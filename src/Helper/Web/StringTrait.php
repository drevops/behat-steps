<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Helper\Web;

/**
 * String shaping and step argument parsing shared across the step vocabulary.
 */
trait StringTrait {

  /**
   * Unescape quoted strings in step arguments.
   *
   * @param string $argument
   *   The step argument to process.
   *
   * @return string
   *   The unescaped argument.
   */
  protected function stringFixStepArgument(string $argument): string {
    return str_replace('\\"', '"', $argument);
  }

  /**
   * Parse a step argument as an integer.
   *
   * Accepts what `FILTER_VALIDATE_INT` accepts, so a decimal, an exponent, a
   * leading zero and a value outside the integer range are rejected.
   *
   * @param string $value
   *   The step argument.
   * @param string $name
   *   The name of the argument, for the failure message.
   * @param int|null $min
   *   The smallest accepted value, or NULL for no lower bound.
   *
   * @return int
   *   The parsed integer.
   *
   * @throws \RuntimeException
   *   When the value is not an integer or is below the minimum.
   */
  protected function stringParseInteger(string $value, string $name, ?int $min = NULL): int {
    $integer = filter_var($value, FILTER_VALIDATE_INT);

    if ($integer === FALSE) {
      throw new \RuntimeException(sprintf('The %s must be an integer, but "%s" was given.', $name, $value));
    }

    if ($min !== NULL && $integer < $min) {
      throw new \RuntimeException(sprintf('The %s must be %d or greater, but "%s" was given.', $name, $min, $value));
    }

    return $integer;
  }

  /**
   * Parse a step argument as a number.
   *
   * Accepts what `FILTER_VALIDATE_FLOAT` accepts, so a decimal and an exponent
   * are read, and infinity, NaN and a value outside the float range are
   * rejected.
   *
   * @param string $value
   *   The step argument.
   * @param string $name
   *   The name of the argument, for the failure message.
   * @param float|null $min
   *   The smallest accepted value, or NULL for no lower bound.
   *
   * @return float
   *   The parsed number.
   *
   * @throws \RuntimeException
   *   When the value is not a number or is below the minimum.
   */
  protected function stringParseNumber(string $value, string $name, ?float $min = NULL): float {
    $number = filter_var($value, FILTER_VALIDATE_FLOAT);

    if ($number === FALSE) {
      throw new \RuntimeException(sprintf('The %s must be a number, but "%s" was given.', $name, $value));
    }

    if ($min !== NULL && $number < $min) {
      throw new \RuntimeException(sprintf('The %s must be %s or greater, but "%s" was given.', $name, $min, $value));
    }

    return $number;
  }

  /**
   * Cast a step argument into the typed value it spells.
   *
   * @param string $value
   *   The raw value captured from the step or table cell.
   *
   * @return mixed
   *   The cast value: a decoded JSON array for array or object input, an
   *   integer or a float for numeric input, a boolean for "true" or "false",
   *   NULL for "null", or the original string otherwise.
   */
  protected function stringNormalizeValue(string $value): mixed {
    $trimmed = trim($value);

    if ($trimmed === '') {
      return $value;
    }

    $lower = strtolower($trimmed);

    if ($lower === 'true') {
      return TRUE;
    }

    if ($lower === 'false') {
      return FALSE;
    }

    if ($lower === 'null') {
      return NULL;
    }

    if ($trimmed[0] === '{' || $trimmed[0] === '[') {
      $decoded = json_decode($trimmed, TRUE);

      if (json_last_error() === JSON_ERROR_NONE) {
        return $decoded;
      }
    }

    if (is_numeric($trimmed)) {
      return str_contains($trimmed, '.') ? (float) $trimmed : (int) $trimmed;
    }

    return $value;
  }

  /**
   * Format a value as text for a comparison or a failure message.
   *
   * @param mixed $value
   *   The value to format.
   *
   * @return string
   *   "NULL" for NULL, "true" or "false" for a boolean, the value itself for
   *   any other scalar, and JSON for anything else.
   */
  protected function stringFormatValue(mixed $value): string {
    if ($value === NULL) {
      return 'NULL';
    }

    if (is_bool($value)) {
      return $value ? 'true' : 'false';
    }

    if (is_scalar($value)) {
      return (string) $value;
    }

    return (string) json_encode($value);
  }

  /**
   * Normalize whitespace in text for comparison.
   *
   * @param string $text
   *   The text to normalize.
   *
   * @return string
   *   The normalized text.
   */
  protected function stringNormalizeWhitespace(string $text): string {
    return trim((string) preg_replace('/\s+/', ' ', $text));
  }

  /**
   * Split comma-separated string and trim values.
   *
   * @param string $text
   *   The comma-separated string.
   *
   * @return non-empty-list<string>
   *   Array of trimmed values.
   */
  protected function stringSplitCommaSeparated(string $text): array {
    return array_map(trim(...), explode(',', $text));
  }

  /**
   * Convert an arbitrary string into a filesystem-safe slug.
   *
   * @param string $value
   *   The string to slugify.
   *
   * @return string
   *   The slugified string.
   */
  protected function stringSlug(string $value): string {
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

    return trim($value, '-') ?: 'untitled';
  }

}
