<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Helper;

use Behat\Gherkin\Node\TableNode;

/**
 * Converts a vertical Behat table into the horizontal shape a driver reads.
 *
 * This is an internal trait and should not be used directly in step
 * definitions.
 */
trait TableTransposeTrait {

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
  protected function tableTransposeVertical(TableNode $table): array {
    $rows = $table->getRows();

    $first_row = $rows[0];
    if (count($first_row) < 2) {
      throw new \RuntimeException('Vertical table must have at least 2 columns (field name and value).');
    }

    $field_names = array_column($rows, 0);
    $duplicate_fields = array_filter(array_count_values($field_names), fn(int $count): bool => $count > 1);

    if (!empty($duplicate_fields)) {
      throw new \RuntimeException(sprintf('Duplicate field names found: %s.', implode(', ', array_keys($duplicate_fields))));
    }

    foreach ($field_names as $field_name) {
      if (trim((string) $field_name) === '') {
        throw new \RuntimeException('Field names cannot be empty.');
      }
    }

    $num_entities = count($first_row) - 1;

    $entities = array_fill(0, $num_entities, []);

    foreach ($rows as $row) {
      $field_name = array_shift($row);

      foreach ($row as $index => $value) {
        $entities[$index][$field_name] = $value;
      }
    }

    return $entities;
  }

  /**
   * Convert vertical format entities to horizontal TableNode.
   *
   * @param array<int, array<string, string>> $entities
   *   Array of entity data arrays from tableTransposeVertical().
   *
   * @return \Behat\Gherkin\Node\TableNode
   *   TableNode in horizontal format (first row is headers, subsequent rows
   *   are values). Returns empty TableNode if input is empty.
   */
  protected function tableTransposeHorizontal(array $entities): TableNode {
    // @codeCoverageIgnoreStart
    if (empty($entities)) {
      return new TableNode([]);
    }
    // @codeCoverageIgnoreEnd
    $field_names = array_keys($entities[0]);
    $rows = [$field_names];

    foreach ($entities as $entity) {
      $rows[] = array_values($entity);
    }

    return new TableNode($rows);
  }

}
