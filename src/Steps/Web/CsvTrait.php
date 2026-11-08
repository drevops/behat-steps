<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Web;

use Behat\Gherkin\Node\TableNode;
use Behat\Hook\BeforeSuite;
use Behat\Step\Then;
use Behat\Testwork\Hook\Scope\BeforeSuiteScope;
use DrevOps\BehatSteps\Steps\Exception\AssertionException;
use DrevOps\BehatSteps\Steps\Helper\Web\StringTrait;

/**
 * Assert the columns, rows and values of CSV files.
 *
 * - Assert the columns the header row names.
 * - Assert the number of data rows.
 * - Assert that rows holding given values exist or do not exist.
 * - Assert every data row of a file, in order.
 * - Assert that a value appears in a field, or in none.
 *
 * A CSV file is read with a header row naming its columns, `,` as the
 * separator and `"` as the enclosure. A row step compares values exactly,
 * and a column it does not list may hold anything.
 *
 * A relative path is resolved against the directory the run was launched
 * from. A path with a scheme, such as `temporary://report.csv`, is read
 * through the stream wrapper PHP has registered for that scheme.
 *
 * @title CSV files
 */
trait CsvTrait {

  use StringTrait;

  /**
   * Working directory captured before a backend bootstrap can change it.
   *
   * A relative path is resolved against this value rather than a live
   * `getcwd()` call, because a backend that boots a site in this process can
   * move the working directory to the site's root.
   */
  protected static ?string $csvBaseDir = NULL;

  /**
   * Capture the working directory the run was launched from.
   */
  #[BeforeSuite]
  public static function csvBeforeSuite(BeforeSuiteScope $scope): void {
    static::$csvBaseDir ??= getcwd() ?: NULL;
  }

  /**
   * Assert that a CSV file has exactly the given columns, in order.
   *
   * @code
   * Then the CSV file "report.csv" should have the following columns:
   *   | clip_id | title | result |
   * @endcode
   *
   * @actor:system
   */
  #[Then('the CSV file :path should have the following columns:')]
  public function csvAssertHasColumns(string $path, TableNode $table): void {
    $expected = $this->csvParseColumnsTable($table);

    $actual = $this->csvGetHeader($path);

    if ($actual !== $expected) {
      throw new AssertionException(sprintf('The CSV file "%s" has the columns %s, but it should have the columns %s.', $path, $this->csvFormatColumns($actual), $this->csvFormatColumns($expected)));
    }
  }

  /**
   * Assert that a CSV file has a column.
   *
   * @code
   * Then the CSV file "report.csv" should have the column "result"
   * @endcode
   *
   * @actor:system
   */
  #[Then('the CSV file :path should have the column :name')]
  public function csvAssertHasColumn(string $path, string $name): void {
    if (!in_array($name, $this->csvGetHeader($path), TRUE)) {
      throw new AssertionException(sprintf('The CSV file "%s" has no column "%s".', $path, $name));
    }
  }

  /**
   * Assert that a CSV file does not have a column.
   *
   * @code
   * Then the CSV file "report.csv" should not have the column "password"
   * @endcode
   *
   * @actor:system
   */
  #[Then('the CSV file :path should not have the column :name')]
  public function csvAssertNotHasColumn(string $path, string $name): void {
    if (in_array($name, $this->csvGetHeader($path), TRUE)) {
      throw new AssertionException(sprintf('The CSV file "%s" has the column "%s", but it should not.', $path, $name));
    }
  }

  /**
   * Assert that a CSV file has a number of data rows.
   *
   * The header row is not counted.
   *
   * @code
   * Then the CSV file "report.csv" should have 3 rows
   * Then the CSV file "empty-report.csv" should have 0 rows
   * @endcode
   *
   * @actor:system
   */
  #[Then('the CSV file :path should have :count row(s)')]
  public function csvAssertRowCount(string $path, string $count): void {
    $count = $this->stringParseInteger($count, 'count', 0);

    $actual = count($this->csvGetRows($path));

    if ($actual !== $count) {
      throw new AssertionException(sprintf('The CSV file "%s" has %d data row(s), but it should have %d.', $path, $actual, $count));
    }
  }

  /**
   * Assert that a CSV file contains a row holding the given values.
   *
   * The table lists a column name and its value on each line.
   *
   * @code
   * Then the CSV file "report.csv" should contain the following row:
   *   | clip_id | clip-1  |
   *   | result  | updated |
   * @endcode
   *
   * @actor:system
   */
  #[Then('the CSV file :path should contain the following row:')]
  public function csvAssertRowExists(string $path, TableNode $table): void {
    $this->csvAssertRowExistsWithValues($path, $this->csvParseRowTable($table));
  }

  /**
   * Assert that a CSV file does not contain a row holding the given values.
   *
   * The table lists a column name and its value on each line.
   *
   * @code
   * Then the CSV file "report.csv" should not contain the following row:
   *   | clip_id | clip-1 |
   *   | result  | failed |
   * @endcode
   *
   * @actor:system
   */
  #[Then('the CSV file :path should not contain the following row:')]
  public function csvAssertRowNotExists(string $path, TableNode $table): void {
    $this->csvAssertRowNotExistsWithValues($path, $this->csvParseRowTable($table));
  }

  /**
   * Assert that a CSV file contains a row for each row of the table.
   *
   * The table's first row names the columns to compare. The rows may appear
   * in the file in any order, among rows the table does not list.
   *
   * @code
   * Then the CSV file "report.csv" should contain the following rows:
   *   | clip_id | result  |
   *   | clip-1  | updated |
   *   | clip-2  | created |
   * @endcode
   *
   * @actor:system
   */
  #[Then('the CSV file :path should contain the following rows:')]
  public function csvAssertRowsExist(string $path, TableNode $table): void {
    foreach ($this->csvParseRowsTable($table) as $values) {
      $this->csvAssertRowExistsWithValues($path, $values);
    }
  }

  /**
   * Assert that a CSV file contains no row matching any row of the table.
   *
   * The table's first row names the columns to compare.
   *
   * @code
   * Then the CSV file "report.csv" should not contain the following rows:
   *   | clip_id | result |
   *   | clip-1  | failed |
   *   | clip-2  | failed |
   * @endcode
   *
   * @actor:system
   */
  #[Then('the CSV file :path should not contain the following rows:')]
  public function csvAssertRowsNotExist(string $path, TableNode $table): void {
    foreach ($this->csvParseRowsTable($table) as $values) {
      $this->csvAssertRowNotExistsWithValues($path, $values);
    }
  }

  /**
   * Assert that the data rows of a CSV file are exactly the table's rows.
   *
   * The table's first row names the columns to compare, and the file must
   * have as many data rows as the table, in the same order. A column the
   * table does not list may hold anything.
   *
   * @code
   * Then the CSV file "report.csv" should have the following rows:
   *   | clip_id | result  |
   *   | clip-1  | updated |
   *   | clip-2  | created |
   * @endcode
   *
   * @actor:system
   */
  #[Then('the CSV file :path should have the following rows:')]
  public function csvAssertHasRows(string $path, TableNode $table): void {
    $this->csvAssertRowsEqual($path, $this->csvParseRowsTable($table));
  }

  /**
   * Assert that a value appears in a field of a CSV file.
   *
   * Every field is searched, the header row included, and a field matches
   * when it contains the value.
   *
   * @code
   * Then the CSV file "report.csv" should contain "clip-1"
   * @endcode
   *
   * @actor:system
   */
  #[Then('the CSV file :path should contain :value')]
  public function csvAssertContains(string $path, string $value): void {
    if (!$this->csvHasValue($path, $value)) {
      throw new AssertionException(sprintf('The CSV file "%s" does not contain "%s".', $path, $value));
    }
  }

  /**
   * Assert that a value appears in no field of a CSV file.
   *
   * @code
   * Then the CSV file "report.csv" should not contain "error"
   * @endcode
   *
   * @actor:system
   */
  #[Then('the CSV file :path should not contain :value')]
  public function csvAssertNotContains(string $path, string $value): void {
    if ($this->csvHasValue($path, $value)) {
      throw new AssertionException(sprintf('The CSV file "%s" contains "%s", but it should not.', $path, $value));
    }
  }

  /**
   * Read the content of a CSV file.
   *
   * @param string $path
   *   The file path. A relative path is resolved against the directory the
   *   run was launched from.
   *
   * @return string
   *   The file content.
   *
   * @throws \DrevOps\BehatSteps\Steps\Exception\AssertionException
   *   When the file does not exist.
   * @throws \RuntimeException
   *   When the file cannot be read.
   */
  public function csvReadFile(string $path): string {
    $file = $this->csvResolvePath($path);

    if (!@is_file($file)) {
      throw new AssertionException(sprintf('The CSV file "%s" does not exist.', $path));
    }

    $content = @file_get_contents($file);

    // @codeCoverageIgnoreStart
    if ($content === FALSE) {
      throw new \RuntimeException(sprintf('Unable to read the CSV file "%s".', $path));
    }

    // @codeCoverageIgnoreEnd
    return $content;
  }

  /**
   * Parse CSV content into records.
   *
   * A leading UTF-8 byte order mark is dropped and blank lines are skipped.
   * A quoted value may span lines. The escape character is disabled, so a
   * quote inside a quoted value is written twice, as RFC 4180 defines.
   *
   * @param string $content
   *   The CSV content.
   * @param string $separator
   *   The field separator, 1 character.
   * @param string $enclosure
   *   The field enclosure, 1 character.
   *
   * @return array<int, array<int, string>>
   *   The records in file order, the header row included, or an empty array
   *   for content with no records.
   */
  public function csvParse(string $content, string $separator = ',', string $enclosure = '"'): array {
    // Spreadsheet applications write a byte order mark, which would otherwise
    // be read into the first column name.
    if (str_starts_with($content, "\xEF\xBB\xBF")) {
      $content = substr($content, 3);
    }

    $handle = fopen('php://memory', 'r+');

    // @codeCoverageIgnoreStart
    if ($handle === FALSE) {
      throw new \RuntimeException('Unable to open a memory stream to parse the CSV content.');
    }

    // @codeCoverageIgnoreEnd
    fwrite($handle, $content);
    rewind($handle);

    $records = [];

    while (($record = fgetcsv($handle, NULL, $separator, $enclosure, '')) !== FALSE) {
      // A blank line is read as a record holding 1 NULL field.
      if ($record === [NULL]) {
        continue;
      }

      $records[] = array_map(strval(...), $record);
    }

    fclose($handle);

    return $records;
  }

  /**
   * Get the columns a CSV file names in its header row.
   *
   * @param string $path
   *   The file path.
   *
   * @return array<int, string>
   *   The column names, in file order.
   *
   * @throws \DrevOps\BehatSteps\Steps\Exception\AssertionException
   *   When the file does not exist or has no header row.
   */
  public function csvGetHeader(string $path): array {
    return $this->csvGetRecords($path)[0];
  }

  /**
   * Get the data rows of a CSV file, keyed by column name.
   *
   * @param string $path
   *   The file path.
   *
   * @return array<int, array<string, string>>
   *   The data rows in file order, each keyed by the header's column names,
   *   or an empty array when the file holds only a header row.
   *
   * @throws \DrevOps\BehatSteps\Steps\Exception\AssertionException
   *   When the file does not exist, has no header row, names a column twice
   *   or has a data row with a different number of values than the header.
   */
  public function csvGetRows(string $path): array {
    return $this->csvBuildRows($path, $this->csvGetRecords($path));
  }

  /**
   * Find the first data row of a CSV file holding the given values.
   *
   * @param string $path
   *   The file path.
   * @param array<string, string> $values
   *   The values the row must hold, keyed by column name. A column not listed
   *   may hold anything.
   *
   * @return array<string, string>|null
   *   The matching row keyed by column name, or NULL when no row matches.
   *
   * @throws \DrevOps\BehatSteps\Steps\Exception\AssertionException
   *   When the file cannot be read as rows, or a listed column is not in the
   *   header.
   */
  public function csvFindRow(string $path, array $values): ?array {
    $records = $this->csvGetRecords($path);

    $this->csvAssertColumnsExist($path, $records[0], array_keys($values));

    foreach ($this->csvBuildRows($path, $records) as $row) {
      if (array_diff_assoc($values, $row) === []) {
        return $row;
      }
    }

    return NULL;
  }

  /**
   * Whether a value appears in any field of a CSV file.
   *
   * @param string $path
   *   The file path.
   * @param string $value
   *   The value a field must contain.
   *
   * @return bool
   *   TRUE when a field, the header row included, contains the value.
   *
   * @throws \DrevOps\BehatSteps\Steps\Exception\AssertionException
   *   When the file does not exist or has no header row.
   */
  public function csvHasValue(string $path, string $value): bool {
    foreach ($this->csvGetRecords($path) as $record) {
      foreach ($record as $field) {
        if (str_contains($field, $value)) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

  /**
   * Assert that a CSV file contains a row holding the given values.
   *
   * @param string $path
   *   The file path.
   * @param array<string, string> $values
   *   The values the row must hold, keyed by column name.
   *
   * @throws \DrevOps\BehatSteps\Steps\Exception\AssertionException
   *   When no row holds the values, or the file cannot be read as rows.
   */
  public function csvAssertRowExistsWithValues(string $path, array $values): void {
    if ($this->csvFindRow($path, $values) === NULL) {
      throw new AssertionException(sprintf('The CSV file "%s" has no row where %s.', $path, $this->csvFormatValues($values)));
    }
  }

  /**
   * Assert that a CSV file contains no row holding the given values.
   *
   * @param string $path
   *   The file path.
   * @param array<string, string> $values
   *   The values the row must not hold together, keyed by column name.
   *
   * @throws \DrevOps\BehatSteps\Steps\Exception\AssertionException
   *   When a row holds the values, or the file cannot be read as rows.
   */
  public function csvAssertRowNotExistsWithValues(string $path, array $values): void {
    if ($this->csvFindRow($path, $values) !== NULL) {
      throw new AssertionException(sprintf('The CSV file "%s" has a row where %s, but it should not.', $path, $this->csvFormatValues($values)));
    }
  }

  /**
   * Assert that the data rows of a CSV file equal the expected rows, in order.
   *
   * @param string $path
   *   The file path.
   * @param array<int, array<string, string>> $expected
   *   The expected rows, each keyed by column name. Only the listed columns
   *   are compared.
   *
   * @throws \DrevOps\BehatSteps\Steps\Exception\AssertionException
   *   When the row count or a listed value differs, or the file cannot be
   *   read as rows.
   */
  public function csvAssertRowsEqual(string $path, array $expected): void {
    $records = $this->csvGetRecords($path);

    $this->csvAssertColumnsExist($path, $records[0], array_keys(array_values($expected)[0] ?? []));

    $rows = $this->csvBuildRows($path, $records);

    if (count($rows) !== count($expected)) {
      throw new AssertionException(sprintf('The CSV file "%s" has %d data row(s), but it should have %d.', $path, count($rows), count($expected)));
    }

    foreach (array_values($expected) as $index => $values) {
      foreach ($values as $column => $value) {
        if ($rows[$index][$column] !== $value) {
          throw new AssertionException(sprintf('The data row %d of the CSV file "%s" has the value "%s" in the column "%s", but it should have the value "%s".', $index + 1, $path, $rows[$index][$column], $column, $value));
        }
      }
    }
  }

  /**
   * Resolve a CSV file path against the directory the run was launched from.
   *
   * @param string $path
   *   The file path.
   *
   * @return string
   *   The path unchanged when it is absolute or has a scheme, otherwise the
   *   path under the captured directory.
   */
  protected function csvResolvePath(string $path): string {
    if (str_starts_with($path, '/') || str_contains($path, '://')) {
      return $path;
    }

    $base = static::$csvBaseDir ?? (getcwd() ?: '.');

    return $base . '/' . $path;
  }

  /**
   * Get the records of a CSV file, the header row first.
   *
   * @param string $path
   *   The file path.
   *
   * @return non-empty-array<int, array<int, string>>
   *   The records in file order.
   *
   * @throws \DrevOps\BehatSteps\Steps\Exception\AssertionException
   *   When the file does not exist or has no header row.
   */
  protected function csvGetRecords(string $path): array {
    $records = $this->csvParse($this->csvReadFile($path));

    if ($records === []) {
      throw new AssertionException(sprintf('The CSV file "%s" has no header row.', $path));
    }

    return $records;
  }

  /**
   * Key the data records of a CSV file by the column names of its header.
   *
   * @param string $path
   *   The file path, for the failure messages.
   * @param non-empty-array<int, array<int, string>> $records
   *   The records, the header row first.
   *
   * @return array<int, array<string, string>>
   *   The data rows keyed by column name.
   *
   * @throws \DrevOps\BehatSteps\Steps\Exception\AssertionException
   *   When the header names a column twice, or a data row has a different
   *   number of values than the header.
   */
  protected function csvBuildRows(string $path, array $records): array {
    $header = array_shift($records);
    $duplicates = array_values(array_diff_assoc($header, array_unique($header)));

    if ($duplicates !== []) {
      throw new AssertionException(sprintf('The CSV file "%s" has the column "%s" more than once.', $path, $duplicates[0]));
    }

    $rows = [];

    foreach (array_values($records) as $index => $record) {
      if (count($record) !== count($header)) {
        throw new AssertionException(sprintf('The data row %d of the CSV file "%s" has %d value(s), but the header has %d column(s).', $index + 1, $path, count($record), count($header)));
      }

      $rows[] = array_combine($header, $record);
    }

    return $rows;
  }

  /**
   * Assert that every named column is in the header of a CSV file.
   *
   * @param string $path
   *   The file path, for the failure message.
   * @param array<int, string> $header
   *   The column names the header holds.
   * @param array<int, int|string> $columns
   *   The column names to look for.
   *
   * @throws \DrevOps\BehatSteps\Steps\Exception\AssertionException
   *   When a column is not in the header.
   */
  protected function csvAssertColumnsExist(string $path, array $header, array $columns): void {
    foreach ($columns as $column) {
      if (!in_array((string) $column, $header, TRUE)) {
        throw new AssertionException(sprintf('The CSV file "%s" has no column "%s".', $path, $column));
      }
    }
  }

  /**
   * Read the column names and values of a 2-column row table.
   *
   * @param \Behat\Gherkin\Node\TableNode $table
   *   The table, a column name and its value on each line.
   *
   * @return array<string, string>
   *   The values keyed by column name.
   *
   * @throws \RuntimeException
   *   When the table does not have 2 columns or names a column twice.
   */
  protected function csvParseRowTable(TableNode $table): array {
    $values = [];

    foreach ($table->getRows() as $row) {
      if (count($row) !== 2) {
        throw new \RuntimeException('The row table must have 2 columns: the column name and the value.');
      }

      [$column, $value] = $row;

      if (array_key_exists($column, $values)) {
        throw new \RuntimeException(sprintf('The row table names the column "%s" more than once.', $column));
      }

      $values[$column] = $value;
    }

    return $values;
  }

  /**
   * Read the rows of a table whose first row names the columns.
   *
   * @param \Behat\Gherkin\Node\TableNode $table
   *   The table, its first row naming the columns.
   *
   * @return array<int, array<string, string>>
   *   The rows below the first, each keyed by column name.
   *
   * @throws \RuntimeException
   *   When the first row names a column twice, or no row follows it.
   */
  protected function csvParseRowsTable(TableNode $table): array {
    $header = $table->getRow(0);
    $duplicates = array_values(array_diff_assoc($header, array_unique($header)));

    if ($duplicates !== []) {
      throw new \RuntimeException(sprintf('The rows table names the column "%s" more than once.', $duplicates[0]));
    }

    $rows = $table->getHash();

    if ($rows === []) {
      throw new \RuntimeException('The rows table must list at least 1 row below the column names.');
    }

    return $rows;
  }

  /**
   * Read the column names of a 1-row columns table.
   *
   * @param \Behat\Gherkin\Node\TableNode $table
   *   The table, listing the column names in 1 row.
   *
   * @return array<int, string>
   *   The column names, in table order.
   *
   * @throws \RuntimeException
   *   When the table has more than 1 row.
   */
  protected function csvParseColumnsTable(TableNode $table): array {
    $rows = $table->getRows();

    if (count($rows) !== 1) {
      throw new \RuntimeException('The columns table must list the column names in 1 row.');
    }

    return array_map(strval(...), $rows[0]);
  }

  /**
   * Format CSV values for a failure message.
   *
   * @param array<string, string> $values
   *   The values keyed by column name.
   *
   * @return string
   *   The values as a clause, such as 'the column "a" has the value "1"'.
   */
  protected function csvFormatValues(array $values): string {
    $clauses = [];

    foreach ($values as $column => $value) {
      $clauses[] = sprintf('the column "%s" has the value "%s"', $column, $value);
    }

    return implode(' and ', $clauses);
  }

  /**
   * Format CSV column names for a failure message.
   *
   * @param array<int, string> $columns
   *   The column names.
   *
   * @return string
   *   The quoted column names, separated by commas.
   */
  protected function csvFormatColumns(array $columns): string {
    return '"' . implode('", "', $columns) . '"';
  }

}
