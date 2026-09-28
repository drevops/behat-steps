<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Helper\Web;

/**
 * String shaping shared across the step vocabulary.
 *
 * This is an internal trait and should not be used directly in step
 * definitions.
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
   * @return array<int, string>
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
