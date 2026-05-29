<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Mink\Exception\UnsupportedDriverActionException;
use Behat\Gherkin\Node\TableNode;
use Drupal\Driver\DrupalDriverInterface;
use Drupal\Driver\Entity\EntityStubInterface;

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

  /**
   * Convert an arbitrary string into a filesystem-safe slug.
   *
   * Lowercases the input, collapses any run of non-alphanumeric characters
   * to a single hyphen, trims leading and trailing hyphens, and falls back
   * to `untitled` when the result would otherwise be empty.
   *
   * @param string $value
   *   The string to slugify.
   *
   * @return string
   *   The slugified string.
   */
  protected function helperSlug(string $value): string {
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

    return trim($value, '-') ?: 'untitled';
  }

  /**
   * Check if JavaScript is supported by the current driver.
   *
   * Ensures the driver is started before checking JavaScript capability.
   *
   * @return bool
   *   TRUE if JavaScript is supported, FALSE otherwise.
   */
  protected function helperIsJavascriptSupported(): bool {
    try {
      $driver = $this->getSession()->getDriver();
      // Ensure driver is started before checking JS capability.
      if (!$driver->isStarted()) {
        $driver->start();
      }
      $driver->evaluateScript('true');
      return TRUE;
    }
    catch (UnsupportedDriverActionException | \Exception) {
      return FALSE;
    }
  }

  /**
   * Expand fixture file paths for file/image fields on an entity stub.
   *
   * Rewrites bare fixture filenames (e.g. 'document.pdf') on 'file' and
   * 'image' field types to absolute paths under the Mink 'files_path' so
   * drupal-driver's FileHandler can read and upload them during entity
   * creation. Skips expansion when a managed file with the same basename
   * already exists in public:// or private://, so existing files take
   * precedence and behaviour stays backward compatible.
   *
   * Requires a Drupal context: the consumer must expose 'getMinkParameter()'
   * and 'getDriver()' (e.g. via MinkContext / RawDrupalContext) and Drupal
   * must be bootstrapped at call time.
   *
   * @param string $entity_type
   *   The entity type machine name (e.g. 'node', 'media').
   * @param \Drupal\Driver\Entity\EntityStubInterface $stub
   *   The entity stub mutated in place.
   */
  protected function helperExpandEntityFieldsFixtures(string $entity_type, EntityStubInterface $stub): void {
    $files_path = $this->getMinkParameter('files_path');

    if (empty($files_path)) {
      return;
    }

    $resolved_files_path = realpath((string) $files_path);

    if ($resolved_files_path === FALSE || !is_dir($resolved_files_path)) {
      return;
    }

    $fixture_path = rtrim($resolved_files_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    $driver = $this->getDriver();

    if (!$driver instanceof DrupalDriverInterface) {
      return;
    }

    $field_types = $driver->getCore()->getEntityFieldTypes($entity_type);

    foreach ($stub->getValues() as $name => $value) {
      if (empty($field_types[$name]) || ($field_types[$name] !== 'image' && $field_types[$name] !== 'file')) {
        continue;
      }

      // Raw compound string (e.g. 'target_id:"foo.jpg", alt:"A"') as written
      // in the Behat table. Hooks fired by 'RawDrupalContext::nodeCreate()'
      // run before 'parseEntityFields()', so on the node path the helper sees
      // the unparsed cell. Rewrite the basename inside the 'target_id:"..."'
      // segment in place and leave the rest of the cell to the parser.
      if (is_string($value) && $this->helperLooksLikeCompoundCell($value)) {
        $rewritten = $this->helperExpandCompoundCellFixtures($value, $fixture_path);

        if ($rewritten !== $value) {
          $stub->setValue($name, $rewritten);
        }

        continue;
      }

      // Parsed shapes produced by 'EntityFieldParser' or the legacy parser:
      // - scalar: 'foo.jpg' (treated as single-value)
      // - scalar list: ['foo.jpg', 'bar.jpg'] (multi-value)
      // - keyed record: ['target_id' => 'foo.jpg', 'alt' => 'A'] (single compound)
      // - list of records: [['target_id' => 'foo.jpg', 'alt' => 'A'], ...] (multi-value compound)
      //
      // Numerically-indexed arrays (lists) are iterated element-by-element so
      // every delta gets resolved. Keyed records and bare scalars are wrapped
      // in a single-element list, processed once, and unwrapped on the way
      // back into the stub.
      $is_list = is_array($value) && array_is_list($value);
      $records = $is_list ? $value : [$value];
      $mutated = FALSE;

      foreach ($records as $index => $record) {
        $basename = is_array($record) ? $record['target_id'] ?? $record[0] ?? NULL : $record;

        if (!is_string($basename) || $basename === '') {
          continue;
        }

        if (str_contains($basename, '/') || str_contains($basename, '\\') || $basename !== basename($basename)) {
          continue;
        }

        if ($this->helperManagedFileExists($basename)) {
          continue;
        }

        if (!is_file($fixture_path . $basename)) {
          continue;
        }

        if (is_array($record)) {
          if (array_key_exists('target_id', $record)) {
            $records[$index]['target_id'] = $fixture_path . $basename;
          }
          else {
            $records[$index][0] = $fixture_path . $basename;
          }
        }
        else {
          $records[$index] = $fixture_path . $basename;
        }

        $mutated = TRUE;
      }

      if (!$mutated) {
        continue;
      }

      $stub->setValue($name, $is_list ? $records : $records[0]);
    }
  }

  /**
   * Detect a raw compound cell string of the shape 'key:"..."' or 'key:[...]'.
   *
   * Mirrors the top-level pattern 'EntityFieldParser' uses to enter compound
   * mode: an identifier, optional whitespace, ':', optional whitespace, then
   * a '"' or '['. Used to distinguish a compound cell that needs in-string
   * basename rewriting from a bare scalar basename like 'document.pdf'.
   */
  protected function helperLooksLikeCompoundCell(string $value): bool {
    return preg_match('/^\s*[a-z_][a-z0-9_]*\s*:\s*[\"\[]/i', $value) === 1;
  }

  /**
   * Rewrite each 'target_id:"basename"' segment to embed the fixture path.
   *
   * Only the 'target_id' key is touched and only when the quoted value is a
   * pure basename (no separators), is not backed by an existing managed file
   * and resolves to a real file under the fixtures dir. Other compound
   * columns (e.g. 'alt', 'description') are left untouched so the parser can
   * still process them.
   */
  protected function helperExpandCompoundCellFixtures(string $value, string $fixture_path): string {
    $callback = function (array $matches) use ($fixture_path): string {
      $basename = $matches[2];

      if ($basename === '' || str_contains($basename, '/') || str_contains($basename, '\\')) {
        return $matches[0];
      }

      if ($basename !== basename($basename)) {
        return $matches[0];
      }

      if ($this->helperManagedFileExists($basename)) {
        return $matches[0];
      }

      if (!is_file($fixture_path . $basename)) {
        return $matches[0];
      }

      return $matches[1] . $fixture_path . $basename . $matches[3];
    };

    return (string) preg_replace_callback('/(target_id\s*:\s*")([^"\\\\]+)(")/i', $callback, $value);
  }

  /**
   * Check whether a managed file with the given basename already exists.
   *
   * Mirrors drupal-driver FileHandler::resolveExistingFile() for bare
   * basenames so callers do not pre-empt the driver's own lookup.
   *
   * @param string $basename
   *   Candidate basename (no path separators).
   *
   * @return bool
   *   TRUE when a managed file exists at public://basename or
   *   private://basename.
   */
  protected function helperManagedFileExists(string $basename): bool {
    if (str_contains($basename, '/') || str_contains($basename, '\\')) {
      return FALSE;
    }

    $storage = \Drupal::entityTypeManager()->getStorage('file');

    foreach (['public', 'private'] as $scheme) {
      if ($storage->loadByProperties(['uri' => $scheme . '://' . $basename])) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
