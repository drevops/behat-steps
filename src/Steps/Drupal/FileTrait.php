<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Behat\Hook\AfterScenario;
use Behat\Hook\BeforeScenario;
use Behat\Mink\Exception\ExpectationException;
use Behat\Step\Given;
use Behat\Step\Then;
use DrevOps\BehatSteps\Backend\Capability\ContentCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\FileCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\ModuleCapabilityInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;
use DrevOps\BehatSteps\Extension\Config\Option;
use DrevOps\BehatSteps\Extension\Prerequisite\Prerequisite;
use DrevOps\BehatSteps\Steps\Helper\Drupal\EntityLifecycleTrait;
use DrevOps\BehatSteps\Steps\Helper\Drupal\QueryTrait;
use DrevOps\BehatSteps\Steps\Helper\Web\FixtureDirectoryTrait;
use DrevOps\BehatSteps\Steps\Helper\Web\StringTrait;

/**
 * Manage Drupal file entities with upload and storage operations.
 *
 * - Create managed and unmanaged files with specific URIs and content.
 * - Verify file existence, content, and proper storage locations.
 * - Assert the rows, row count and columns of CSV files.
 * - Set up file system directories and clean up created files.
 *
 * A CSV file is read with a header row naming its columns, `,` as the
 * separator and `"` as the enclosure. Its URI may be a stream wrapper URI
 * such as `temporary://report.csv` or a path on the filesystem.
 *
 * Skip processing with tag: `@behat-steps-skip:FileTrait`.
 *
 * @title Files
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\WebRawContext
 */
trait FileTrait {

  use EntityLifecycleTrait;
  use FixtureDirectoryTrait;
  use QueryTrait;
  use StringTrait;

  /**
   * Unmanaged file URIs.
   *
   * @var array<int, string>
   */
  protected array $fileUnmanagedUris = [];

  /**
   * Ensure private and temp directories exist.
   */
  #[BeforeScenario]
  public function fileBeforeScenario(BeforeScenarioScope $scope): void {
    // A suite listing no backend that writes files has no directory to
    // prepare.
    if ($this->skipTag(__TRAIT__, $scope) || !$this->getBackendRegistry()->hasCapability(FileCapabilityInterface::class)) {
      return;
    }

    $backend = $this->backendFor(FileCapabilityInterface::class);

    // A stream wrapper the site has not configured cannot be prepared and
    // needs no directory, so the result is not checked.
    $backend->filePrepareDirectory('private://');
    $backend->filePrepareDirectory('temporary://');
  }

  /**
   * Remove unmanaged files created during the scenario.
   *
   * Managed file entities are removed by the shared entity registry cleanup.
   */
  #[AfterScenario]
  public function fileAfterScenario(AfterScenarioScope $scope): void {
    if ($this->skipTag(__TRAIT__, $scope) || $this->fileUnmanagedUris === []) {
      return;
    }

    $backend = $this->backendFor(FileCapabilityInterface::class);

    foreach ($this->fileUnmanagedUris as $uri) {
      $backend->fileDeleteUnmanaged($uri);
    }

    $this->fileUnmanagedUris = [];
  }

  /**
   * Create managed files with properties provided in the table.
   *
   * @code
   * Given the following managed files exist:
   *   | path         | uri                    | status |
   *   | document.pdf | public://document.pdf  | 1      |
   *   | image.jpg    | public://images/pic.jpg| 1      |
   * @endcode
   *
   * @actor:system
   */
  #[Given('the following managed files exist:')]
  public function fileCreateManagedMultiple(TableNode $table): void {
    foreach ($table->getHash() as $hash) {
      if (empty($hash['path'])) {
        throw new \RuntimeException('Missing required column "path".');
      }

      $this->fileCreateManaged($hash['path'], $this->fileBuildManagedStub($hash), $hash['uri'] ?? NULL);
    }
  }

  /**
   * Delete managed files defined by provided properties/fields.
   *
   * The column header names a file entity property, such as filename, uri,
   * status or uid.
   *
   * @see Drupal\file\Entity\File
   *
   * @code
   * Given the following managed files do not exist:
   *   | filename      |
   *   | myfile.jpg    |
   *   | otherfile.jpg |
   * @endcode
   *
   * @code
   * Given the following managed files do not exist:
   *   | uri                    |
   *   | public://myfile.jpg    |
   *   | public://otherfile.jpg |
   * @endcode
   *
   * @actor:system
   */
  #[Given('the following managed files do not exist:')]
  public function fileDeleteManagedMultiple(TableNode $table): void {
    $this->backendFor(ContentCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    $field_name = $table->getRow(0)[0];

    if (is_numeric($field_name)) {
      throw new \RuntimeException('The first column should be the field name.');
    }

    foreach (array_slice($table->getColumn(0), 1) as $field_value) {
      $this->fileDeleteManaged([$field_name => (string) $field_value]);
    }
  }

  /**
   * Create an unmanaged file.
   *
   * @code
   * Given the unmanaged file at the URI "public://sample.txt" exists
   * @endcode
   *
   * @actor:system
   */
  #[Given('the unmanaged file at the URI :uri exists')]
  public function fileCreateUnmanaged(string $uri): void {
    $this->fileWriteUnmanaged($uri, 'test');
  }

  /**
   * Create an unmanaged file with specified content.
   *
   * @code
   * Given the unmanaged file at the URI "public://data.txt" exists with the content "Sample content"
   * @endcode
   *
   * @actor:system
   */
  #[Given('the unmanaged file at the URI :uri exists with the content :content')]
  public function fileCreateUnmanagedWithContent(string $uri, string $content): void {
    $this->fileWriteUnmanaged($uri, $content);
  }

  /**
   * Assert that an unmanaged file with specified URI exists.
   *
   * @code
   * Then the unmanaged file at the URI "public://sample.txt" should exist
   * @endcode
   *
   * @actor:system
   */
  #[Then('the unmanaged file at the URI :uri should exist')]
  public function fileAssertUnmanagedExists(string $uri): void {
    if (!$this->backendFor(FileCapabilityInterface::class)->fileExistsUnmanaged($uri)) {
      throw new ExpectationException(sprintf('The file "%s" does not exist.', $uri), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that an unmanaged file with specified URI does not exist.
   *
   * @code
   * Then the unmanaged file at the URI "public://temp.txt" should not exist
   * @endcode
   *
   * @actor:system
   */
  #[Then('the unmanaged file at the URI :uri should not exist')]
  public function fileAssertUnmanagedNotExists(string $uri): void {
    if ($this->backendFor(FileCapabilityInterface::class)->fileExistsUnmanaged($uri)) {
      throw new ExpectationException(sprintf('The file "%s" exists, but it should not.', $uri), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that an unmanaged file exists and has specified content.
   *
   * @code
   * Then the unmanaged file at the URI "public://config.txt" should contain "debug=true"
   * @endcode
   *
   * @actor:system
   */
  #[Then('the unmanaged file at the URI :uri should contain :content')]
  public function fileAssertUnmanagedContains(string $uri, string $content): void {
    $file_content = $this->fileGetUnmanagedContent($uri);

    if (!str_contains($file_content, $content)) {
      throw new ExpectationException(sprintf('The file content "%s" does not contain "%s".', $file_content, $content), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that an unmanaged file exists and does not have specified content.
   *
   * @code
   * Then the unmanaged file at the URI "public://config.txt" should not contain "debug=false"
   * @endcode
   *
   * @actor:system
   */
  #[Then('the unmanaged file at the URI :uri should not contain :content')]
  public function fileAssertUnmanagedNotContains(string $uri, string $content): void {
    $file_content = $this->fileGetUnmanagedContent($uri);

    if (str_contains($file_content, $content)) {
      throw new ExpectationException(sprintf('The file content "%s" contains "%s", but it should not.', $file_content, $content), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that a CSV file contains a row with the given values.
   *
   * The table lists a column name and its value on each line. A data row
   * matches when it holds every listed value; the columns not listed may
   * hold anything.
   *
   * @code
   * Then the CSV file at the URI "temporary://report.csv" should contain the following row:
   *   | clip_id | clip-1  |
   *   | result  | updated |
   * @endcode
   *
   * @actor:system
   */
  #[Then('the CSV file at the URI :uri should contain the following row:')]
  public function fileAssertCsvRowExists(string $uri, TableNode $table): void {
    $values = $this->fileParseCsvRowTable($table);

    if ($this->fileFindCsvRow($uri, $values) === NULL) {
      throw new ExpectationException(sprintf('The CSV file "%s" has no row where %s.', $uri, $this->fileFormatCsvValues($values)), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that a CSV file does not contain a row with the given values.
   *
   * @code
   * Then the CSV file at the URI "temporary://report.csv" should not contain the following row:
   *   | clip_id | clip-1 |
   *   | result  | failed |
   * @endcode
   *
   * @actor:system
   */
  #[Then('the CSV file at the URI :uri should not contain the following row:')]
  public function fileAssertCsvRowNotExists(string $uri, TableNode $table): void {
    $values = $this->fileParseCsvRowTable($table);

    if ($this->fileFindCsvRow($uri, $values) !== NULL) {
      throw new ExpectationException(sprintf('The CSV file "%s" has a row where %s, but it should not.', $uri, $this->fileFormatCsvValues($values)), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that a CSV file has a number of data rows.
   *
   * The header row is not counted.
   *
   * @code
   * Then the CSV file at the URI "temporary://report.csv" should have 3 rows
   * @endcode
   *
   * @actor:system
   */
  #[Then('the CSV file at the URI :uri should have :count row(s)')]
  public function fileAssertCsvRowCount(string $uri, string $count): void {
    $count = $this->stringParseInteger($count, 'count', 0);

    $actual = count($this->fileGetCsvRows($uri));

    if ($actual !== $count) {
      throw new ExpectationException(sprintf('The CSV file "%s" has %d data row(s), but it should have %d.', $uri, $actual, $count), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that a CSV file has exactly the given columns, in order.
   *
   * @code
   * Then the CSV file at the URI "temporary://report.csv" should have the following columns:
   *   | clip_id | title | result |
   * @endcode
   *
   * @actor:system
   */
  #[Then('the CSV file at the URI :uri should have the following columns:')]
  public function fileAssertCsvHasColumns(string $uri, TableNode $table): void {
    $expected = $this->fileParseCsvColumnTable($table);

    $actual = $this->fileGetCsvHeader($uri);

    if ($actual !== $expected) {
      throw new ExpectationException(sprintf('The CSV file "%s" has the columns %s, but it should have the columns %s.', $uri, $this->fileFormatCsvColumns($actual), $this->fileFormatCsvColumns($expected)), $this->getSession()->getDriver());
    }
  }

  /**
   * Write an unmanaged file, creating its directory when it is missing.
   *
   * The file is deleted after the scenario.
   *
   * @param string $uri
   *   The file URI.
   * @param string $content
   *   The file content.
   *
   * @throws \RuntimeException
   *   When the directory cannot be created or the file cannot be written.
   */
  public function fileWriteUnmanaged(string $uri, string $content): void {
    $this->backendFor(FileCapabilityInterface::class)->fileWriteUnmanaged($uri, $content);

    $this->fileUnmanagedUris[] = $uri;
  }

  /**
   * Get the content of an unmanaged file.
   *
   * @param string $uri
   *   The file URI.
   *
   * @return string
   *   The file content.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   When the file does not exist.
   * @throws \RuntimeException
   *   When the file cannot be read.
   */
  public function fileGetUnmanagedContent(string $uri): string {
    $backend = $this->backendFor(FileCapabilityInterface::class);

    if (!$backend->fileExistsUnmanaged($uri)) {
      throw new ExpectationException(sprintf('The file "%s" does not exist.', $uri), $this->getSession()->getDriver());
    }

    return $backend->fileReadUnmanaged($uri);
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
  public function fileParseCsv(string $content, string $separator = ',', string $enclosure = '"'): array {
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
   * @param string $uri
   *   The file URI or path.
   *
   * @return array<int, string>
   *   The column names, in file order.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   When the file does not exist or has no header row.
   */
  public function fileGetCsvHeader(string $uri): array {
    return $this->fileGetCsvRecords($uri)[0];
  }

  /**
   * Get the data rows of a CSV file, keyed by column name.
   *
   * @param string $uri
   *   The file URI or path.
   *
   * @return array<int, array<string, string>>
   *   The data rows in file order, each keyed by the header's column names,
   *   or an empty array when the file holds only a header row.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   When the file does not exist, has no header row, names a column twice
   *   or has a data row with a different number of values than the header.
   */
  public function fileGetCsvRows(string $uri): array {
    return $this->fileBuildCsvRows($uri, $this->fileGetCsvRecords($uri));
  }

  /**
   * Find the first data row of a CSV file holding the given values.
   *
   * @param string $uri
   *   The file URI or path.
   * @param array<string, string> $values
   *   The values the row must hold, keyed by column name. A column not listed
   *   may hold anything.
   *
   * @return array<string, string>|null
   *   The matching row keyed by column name, or NULL when no row matches.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   When the file cannot be read as rows, or a listed column is not in the
   *   header.
   */
  public function fileFindCsvRow(string $uri, array $values): ?array {
    $records = $this->fileGetCsvRecords($uri);

    foreach (array_keys($values) as $column) {
      if (!in_array((string) $column, $records[0], TRUE)) {
        throw new ExpectationException(sprintf('The CSV file "%s" has no column "%s".', $uri, $column), $this->getSession()->getDriver());
      }
    }

    foreach ($this->fileBuildCsvRows($uri, $records) as $row) {
      if (array_diff_assoc($values, $row) === []) {
        return $row;
      }
    }

    return NULL;
  }

  /**
   * Get the records of a CSV file, the header row first.
   *
   * @param string $uri
   *   The file URI or path.
   *
   * @return non-empty-array<int, array<int, string>>
   *   The records in file order.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   When the file does not exist or has no header row.
   */
  protected function fileGetCsvRecords(string $uri): array {
    $records = $this->fileParseCsv($this->fileGetUnmanagedContent($uri));

    if ($records === []) {
      throw new ExpectationException(sprintf('The CSV file "%s" has no header row.', $uri), $this->getSession()->getDriver());
    }

    return $records;
  }

  /**
   * Key the data records of a CSV file by the column names of its header.
   *
   * @param string $uri
   *   The file URI or path, for the failure messages.
   * @param non-empty-array<int, array<int, string>> $records
   *   The records, the header row first.
   *
   * @return array<int, array<string, string>>
   *   The data rows keyed by column name.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   When the header names a column twice, or a data row has a different
   *   number of values than the header.
   */
  protected function fileBuildCsvRows(string $uri, array $records): array {
    $header = array_shift($records);
    $duplicates = array_values(array_diff_assoc($header, array_unique($header)));

    if ($duplicates !== []) {
      throw new ExpectationException(sprintf('The CSV file "%s" has the column "%s" more than once.', $uri, $duplicates[0]), $this->getSession()->getDriver());
    }

    $rows = [];

    foreach (array_values($records) as $index => $record) {
      if (count($record) !== count($header)) {
        throw new ExpectationException(sprintf('The data row %d of the CSV file "%s" has %d value(s), but the header has %d column(s).', $index + 1, $uri, count($record), count($header)), $this->getSession()->getDriver());
      }

      $rows[] = array_combine($header, $record);
    }

    return $rows;
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
  protected function fileParseCsvRowTable(TableNode $table): array {
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
  protected function fileParseCsvColumnTable(TableNode $table): array {
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
  protected function fileFormatCsvValues(array $values): string {
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
  protected function fileFormatCsvColumns(array $columns): string {
    return '"' . implode('", "', $columns) . '"';
  }

  /**
   * Build a managed file stub from a set of values.
   *
   * @param array<string, string> $values
   *   The values. The "path" and "uri" values locate the file rather than
   *   describe it, so the stub leaves them out.
   *
   * @return \DrevOps\BehatSteps\Backend\Entity\EntityStub
   *   The file stub.
   */
  protected function fileBuildManagedStub(array $values): EntityStub {
    return new EntityStub('file', NULL, array_diff_key($values, ['path' => TRUE, 'uri' => TRUE]));
  }

  /**
   * Delete the managed files that match conditions.
   *
   * @param array<string, string> $conditions
   *   Conditions keyed by field names.
   */
  public function fileDeleteManaged(array $conditions): void {
    $backend = $this->backendFor(ContentCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    foreach ($this->queryEntityIds('file', $conditions) as $fid) {
      $backend->deleteEntity(new EntityStub('file', NULL, ['fid' => $fid]));
    }
  }

  /**
   * Create a single managed file.
   *
   * @param string $path
   *   The source file path relative to 'files_path'.
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   Entity fields stub (must not contain 'path' or 'uri').
   * @param string|null $uri
   *   Optional destination URI. Defaults to 'public://filename'.
   *
   * @return \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface
   *   The stub of the created file, carrying its 'fid'.
   */
  public function fileCreateManaged(string $path, EntityStubInterface $stub, ?string $uri = NULL): EntityStubInterface {
    $this->backendFor(FileCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    $this->entityLifecycleParseFields($stub);

    $created = $this->fileCreateEntity($path, $stub, $uri);

    $this->entityLifecycleRegister($created, 'fid');

    return $created;
  }

  /**
   * Create file entity.
   *
   * When a value fails to resolve, the written file is deleted again, so a
   * failed create leaves no file behind.
   *
   * @param string $path
   *   The source file path relative to 'files_path'.
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   Entity fields stub. Its values are set on the file once it is written.
   * @param string|null $uri
   *   Optional destination URI. Defaults to 'public://filename'.
   *
   * @return \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface
   *   The stub of the created file, carrying its 'fid'.
   */
  public function fileCreateEntity(string $path, EntityStubInterface $stub, ?string $uri = NULL): EntityStubInterface {
    $backend = $this->backendFor(FileCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    $path = ltrim($path, '/');
    $path = $this->fixtureDirectoryFindFile($path) ?? $path;

    if (!is_readable($path)) {
      throw new \RuntimeException(sprintf('Unable to find file "%s".', $path));
    }

    $content = file_get_contents($path);

    // @codeCoverageIgnoreStart
    if ($content === FALSE) {
      throw new \RuntimeException(sprintf('Unable to read file "%s".', $path));
    }

    // @codeCoverageIgnoreEnd
    $destination = $uri === NULL || $uri === '' ? 'public://' . basename($path) : $uri;
    $written = $backend->fileWriteManaged($destination, $content, new EntityStub('file'));
    $created = new EntityStub('file', NULL, ['fid' => $this->queryGetEntityId($written, 'fid')]);

    try {
      $this->fileUpdate($created, $stub->getValues());
    }
    catch (\Exception $exception) {
      // The caller gets no stub when a value fails to resolve, so the written
      // file is deleted here.
      $this->backendFor(ContentCapabilityInterface::class)->deleteEntity($created);

      throw $exception;
    }

    return $created;
  }

  /**
   * Update the fields of a file.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $file
   *   The file stub, carrying its 'fid'.
   * @param array<string, mixed> $values
   *   The field values, keyed by field name. An empty array saves nothing.
   */
  protected function fileUpdate(EntityStubInterface $file, array $values): void {
    if ($values === []) {
      return;
    }

    $this->backendFor(ContentCapabilityInterface::class)->updateEntity(new EntityStub('file', NULL, ['fid' => $this->queryGetEntityId($file, 'fid')] + $values));
  }

  /**
   * Load multiple files with specified conditions.
   *
   * @param array<string, string> $conditions
   *   Conditions keyed by field names.
   *
   * @return array<int|string, \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface>
   *   A stub per matching file carrying its 'fid', keyed by ID, or an empty
   *   array when none match.
   */
  public function fileLoadMultiple(array $conditions = []): array {
    $this->assertPrerequisites(__TRAIT__);

    return $this->queryEntityStubs('file', 'fid', $conditions);
  }

  /**
   * Declares the options this trait reads.
   *
   * @return array<int, \DrevOps\BehatSteps\Extension\Config\Option>
   *   The options this trait declares.
   */
  protected function fileConfigSchema(): array {
    return [
      new Option('enabled', default: TRUE, description: 'If `TRUE`, creates the `private://` and `temporary://` directories before each scenario, and deletes the unmanaged files the file steps wrote after it finishes. If `FALSE`, the steps still work and their files stay.'),
    ];
  }

  /**
   * Declares the prerequisites this trait asserts.
   *
   * @return array<int, \DrevOps\BehatSteps\Extension\Prerequisite\Prerequisite>
   *   The prerequisites this trait declares.
   */
  protected function filePrerequisites(): array {
    return [
      Prerequisite::capability(FileCapabilityInterface::class),
      Prerequisite::check(static fn(ModuleCapabilityInterface $backend): bool => $backend->moduleIsEnabled('file'), 'the core "file" module is enabled, for the managed file steps'),
    ];
  }

}
