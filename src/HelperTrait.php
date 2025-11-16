<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Gherkin\Node\TableNode;

/**
 * Internal helper methods for Behat step definitions.
 *
 * This trait provides common helper methods that can be used across multiple
 * Behat step definition traits. Include this trait in any trait that needs
 * access to shared helper functionality.
 *
 * This is an internal trait and should not be used directly in step definitions.
 */
trait HelperTrait {

  /**
   * Transpose a vertical table format (field/value columns) to entity arrays.
   *
   * Supports both single and multiple entity creation:
   *
   * Single entity (2 columns):
   *   | name  | John  |
   *   | age   | 30    |
   *
   * Multiple entities (3+ columns):
   *   | name  | John      | Jane      |
   *   | age   | 30        | 25        |
   *
   * Returns:
   *   Single entity: [['name' => 'John', 'age' => '30']]
   *   Multiple entities: [['name' => 'John', 'age' => '30'], ['name' => 'Jane', 'age' => '25']]
   *
   * @param \Behat\Gherkin\Node\TableNode $table
   *   The vertical format table.
   *
   * @return array<int, array<string, string>>
   *   Array of entity data arrays. Each entity is an associative array.
   *
   * @throws \RuntimeException
   *   If table doesn't have at least 2 columns or has no rows.
   */
  protected function helperTransposeVerticalTable(TableNode $table): array {
    $rows = $table->getRows();

    // Validate table has at least 2 columns (field + at least one value column).
    $first_row = $rows[0];
    if (count($first_row) < 2) {
      throw new \RuntimeException('Vertical table must have at least 2 columns (field name and value).');
    }

    // Validate no duplicate field names.
    $field_names = array_column($rows, 0);
    $duplicate_fields = array_filter(array_count_values($field_names), fn(int $count): bool => $count > 1);

    if (!empty($duplicate_fields)) {
      throw new \RuntimeException(sprintf('Duplicate field names found: %s', implode(', ', array_keys($duplicate_fields))));
    }

    // Validate all field names are non-empty.
    foreach ($field_names as $field_name) {
      if (trim((string) $field_name) === '') {
        throw new \RuntimeException('Field names cannot be empty.');
      }
    }

    // Determine number of entities based on number of columns.
    $num_entities = count($first_row) - 1;

    // Initialize result array for each entity.
    $entities = array_fill(0, $num_entities, []);

    // Transpose the data.
    foreach ($rows as $row) {
      $field_name = array_shift($row);

      // Assign each value to corresponding entity.
      // Note: Gherkin's TableNode automatically pads missing cells with empty
      // strings, so we don't need to validate row length.
      foreach ($row as $index => $value) {
        $entities[$index][$field_name] = $value;
      }
    }

    return $entities;
  }

  /**
   * Convert vertical format entities to horizontal TableNode.
   *
   * Converts an array of entities (from helperTransposeVerticalTable) back
   * to horizontal format TableNode expected by DrupalExtension methods.
   *
   * @param array<int, array<string, string>> $entities
   *   Array of entity data arrays from helperTransposeVerticalTable().
   *
   * @return \Behat\Gherkin\Node\TableNode
   *   TableNode in horizontal format (first row is headers, subsequent rows
   *   are values). Returns empty TableNode if input is empty.
   */
  protected function helperBuildHorizontalTable(array $entities): TableNode {
    // @codeCoverageIgnoreStart
    if (empty($entities)) {
      return new TableNode([]);
    }
    // @codeCoverageIgnoreEnd
    // Get field names from first entity.
    $field_names = array_keys($entities[0]);
    $rows = [$field_names];

    // Add each entity as a row.
    foreach ($entities as $entity) {
      $rows[] = array_values($entity);
    }

    return new TableNode($rows);
  }

  /**
   * Unescape quoted strings in step arguments.
   *
   * Converts `\"` back to `"` in Behat step arguments.
   *
   * @param string $argument
   *   The step argument to process.
   *
   * @return string
   *   The unescaped argument.
   */
  protected function helperFixStepArgument(string $argument): string {
    return str_replace('\\"', '"', $argument);
  }

  /**
   * Normalize whitespace in text for comparison.
   *
   * Collapses multiple whitespace characters (spaces, tabs, newlines) into
   * single spaces and trims leading/trailing whitespace.
   *
   * @param string $text
   *   The text to normalize.
   *
   * @return string
   *   The normalized text.
   */
  protected function helperNormalizeWhitespace(string $text): string {
    return trim((string) preg_replace('/\s+/', ' ', $text));
  }

  /**
   * Split comma-separated string and trim values.
   *
   * Splits a comma-separated string into an array and trims whitespace
   * from each value.
   *
   * @param string $text
   *   The comma-separated string.
   *
   * @return array<int, string>
   *   Array of trimmed values.
   */
  protected function helperSplitCommaSeparated(string $text): array {
    return array_map(trim(...), explode(',', $text));
  }

}
