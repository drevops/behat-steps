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

}
