<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Steps\Web;

use Behat\Gherkin\Node\TableNode;
use DrevOps\BehatSteps\Steps\Exception\AssertionException;
use DrevOps\BehatSteps\Steps\Web\CsvTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use DrevOps\BehatSteps\WebRawContext;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for CsvTrait.
 */
#[CoversTrait(CsvTrait::class)]
class CsvTraitTest extends UnitTestCase {

  /**
   * The CSV content most tests read.
   */
  protected const string REPORT = "clip_id,title,result\nclip-1,\"First, clip\",updated\nclip-2,Second,created\n";

  /**
   * A test implementation of CsvTrait.
   */
  protected CsvTraitTestImplementation $testObject;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->testObject = new CsvTraitTestImplementation();
    $this->testObject->testSetBaseDir(static::$tmp);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $this->testObject->testSetBaseDir(NULL);

    parent::tearDown();
  }

  /**
   * Tests parsing CSV content into records.
   *
   * @param string $content
   *   The CSV content.
   * @param array<int, string> $args
   *   The separator and enclosure to pass, or none for the defaults.
   * @param array<int, array<int, string>> $expected
   *   The expected records.
   */
  #[DataProvider('dataProviderParse')]
  public function testParse(string $content, array $args, array $expected): void {
    $this->assertSame($expected, $this->testObject->csvParse($content, ...$args));
  }

  public static function dataProviderParse(): \Iterator {
    yield 'empty content' => ['', [], []];
    yield 'only blank lines' => ["\n\n", [], []];
    yield 'header only' => ["a,b\n", [], [['a', 'b']]];
    yield 'no trailing newline' => ["a,b\n1,2", [], [['a', 'b'], ['1', '2']]];
    yield 'CRLF line endings' => ["a,b\r\n1,2\r\n", [], [['a', 'b'], ['1', '2']]];
    yield 'blank lines skipped' => ["a,b\n\n1,2\n\n", [], [['a', 'b'], ['1', '2']]];
    yield 'byte order mark dropped' => ["\xEF\xBB\xBFa,b\n1,2\n", [], [['a', 'b'], ['1', '2']]];
    yield 'quoted separator' => ["a,b\n\"1,5\",2\n", [], [['a', 'b'], ['1,5', '2']]];
    yield 'doubled quote' => ["a\n\"say \"\"hi\"\"\"\n", [], [['a'], ['say "hi"']]];
    yield 'backslash is not an escape' => ["a,b\n\"c:\\\",2\n", [], [['a', 'b'], ['c:\\', '2']]];
    yield 'quoted newline' => ["a,b\n\"line 1\nline 2\",2\n", [], [['a', 'b'], ["line 1\nline 2", '2']]];
    yield 'empty values' => ["a,b,c\n,,\n", [], [['a', 'b', 'c'], ['', '', '']]];
    yield 'custom separator and enclosure' => ["a;b\n'1;5';2\n", [';', "'"], [['a', 'b'], ['1;5', '2']]];
  }

  public function testBeforeSuiteCapturesWorkingDirectoryOnce(): void {
    $this->testObject->testSetBaseDir(NULL);

    CsvTraitTestImplementation::csvBeforeSuite($this->createBeforeSuiteScope());
    $this->assertSame(getcwd(), $this->testObject->testGetBaseDir());

    $this->testObject->testSetBaseDir(static::$tmp);

    CsvTraitTestImplementation::csvBeforeSuite($this->createBeforeSuiteScope());
    $this->assertSame(static::$tmp, $this->testObject->testGetBaseDir());
  }

  /**
   * Tests that every path form reaches the file.
   *
   * @param string $path
   *   The path to read, with '{tmp}' standing for the test workspace.
   */
  #[DataProvider('dataProviderReadFileResolvesPath')]
  public function testReadFileResolvesPath(string $path): void {
    $this->writeFixture('exports/report.csv', static::REPORT);

    $this->assertSame(static::REPORT, $this->testObject->csvReadFile(str_replace('{tmp}', static::$tmp, $path)));
  }

  public static function dataProviderReadFileResolvesPath(): \Iterator {
    yield 'relative to the captured directory' => ['exports/report.csv'];
    yield 'absolute' => ['{tmp}/exports/report.csv'];
    yield 'stream wrapper' => ['file://{tmp}/exports/report.csv'];
  }

  public function testReadFileResolvesRelativePathAgainstLiveDirectoryWithoutCapture(): void {
    $this->testObject->testSetBaseDir(NULL);

    $this->expectException(AssertionException::class);
    $this->expectExceptionMessage('The CSV file "missing-csv-trait-test.csv" does not exist.');

    $this->testObject->csvReadFile('missing-csv-trait-test.csv');
  }

  public function testStepsAndHelpersPassOnMatchingFile(): void {
    $this->writeFixture('report.csv', static::REPORT);

    $this->testObject->csvAssertHasColumns('report.csv', new TableNode([['clip_id', 'title', 'result']]));
    $this->testObject->csvAssertHasColumn('report.csv', 'result');
    $this->testObject->csvAssertNotHasColumn('report.csv', 'status');
    $this->testObject->csvAssertRowCount('report.csv', '2');
    $this->testObject->csvAssertRowExists('report.csv', new TableNode([['result', 'updated'], ['clip_id', 'clip-1']]));
    $this->testObject->csvAssertRowNotExists('report.csv', new TableNode([['clip_id', 'clip-1'], ['result', 'created']]));
    $this->testObject->csvAssertRowsExist('report.csv', new TableNode([['result', 'clip_id'], ['created', 'clip-2'], ['updated', 'clip-1']]));
    $this->testObject->csvAssertRowsNotExist('report.csv', new TableNode([['clip_id', 'result'], ['clip-1', 'failed'], ['clip-3', 'created']]));
    $this->testObject->csvAssertHasRows('report.csv', new TableNode([['clip_id', 'result'], ['clip-1', 'updated'], ['clip-2', 'created']]));
    $this->testObject->csvAssertContains('report.csv', 'First, cl');
    $this->testObject->csvAssertContains('report.csv', 'title');
    $this->testObject->csvAssertNotContains('report.csv', 'failed');

    $this->assertSame(['clip_id', 'title', 'result'], $this->testObject->csvGetHeader('report.csv'));
    $this->assertSame([['clip_id' => 'clip-1', 'title' => 'First, clip', 'result' => 'updated'], ['clip_id' => 'clip-2', 'title' => 'Second', 'result' => 'created']], $this->testObject->csvGetRows('report.csv'));
    $this->assertSame(['clip_id' => 'clip-2', 'title' => 'Second', 'result' => 'created'], $this->testObject->csvFindRow('report.csv', ['result' => 'created']));
    $this->assertNull($this->testObject->csvFindRow('report.csv', ['result' => 'failed']));
    $this->assertTrue($this->testObject->csvHasValue('report.csv', 'Second'));
    $this->assertFalse($this->testObject->csvHasValue('report.csv', 'Third'));
  }

  public function testHeaderOnlyFileHasNoRows(): void {
    $this->writeFixture('report.csv', "clip_id,result\n");

    $this->testObject->csvAssertRowCount('report.csv', '0');
    $this->testObject->csvAssertRowNotExists('report.csv', new TableNode([['clip_id', 'clip-1']]));

    $this->assertSame([], $this->testObject->csvGetRows('report.csv'));
  }

  /**
   * Tests that a step fails with a message naming what went wrong.
   *
   * @param string $content
   *   The CSV content the file holds.
   * @param string $method
   *   The step or helper to call.
   * @param array<int, mixed> $args
   *   The arguments to call it with, after the path.
   * @param class-string<\Throwable> $expected_class
   *   The expected exception class.
   * @param string $expected_message
   *   The expected exception message.
   */
  #[DataProvider('dataProviderStepFails')]
  public function testStepFails(string $content, string $method, array $args, string $expected_class, string $expected_message): void {
    $this->writeFixture('report.csv', $content);

    $this->expectException($expected_class);
    $this->expectExceptionMessage($expected_message);

    $this->testObject->{$method}('report.csv', ...$args);
  }

  public static function dataProviderStepFails(): \Iterator {
    $content = static::REPORT;

    yield 'columns differ' => [$content, 'csvAssertHasColumns', [new TableNode([['clip_id', 'result']])], AssertionException::class, 'The CSV file "report.csv" has the columns "clip_id", "title", "result", but it should have the columns "clip_id", "result".'];
    yield 'columns in another order' => [$content, 'csvAssertHasColumns', [new TableNode([['clip_id', 'result', 'title']])], AssertionException::class, 'The CSV file "report.csv" has the columns "clip_id", "title", "result", but it should have the columns "clip_id", "result", "title".'];
    yield 'columns table with 2 rows' => [$content, 'csvAssertHasColumns', [new TableNode([['clip_id'], ['result']])], \RuntimeException::class, 'The columns table must list the column names in 1 row.'];
    yield 'column missing' => [$content, 'csvAssertHasColumn', ['status'], AssertionException::class, 'The CSV file "report.csv" has no column "status".'];
    yield 'column present' => [$content, 'csvAssertNotHasColumn', ['result'], AssertionException::class, 'The CSV file "report.csv" has the column "result", but it should not.'];
    yield 'row count' => [$content, 'csvAssertRowCount', ['3'], AssertionException::class, 'The CSV file "report.csv" has 2 data row(s), but it should have 3.'];
    yield 'row count not an integer' => [$content, 'csvAssertRowCount', ['many'], \RuntimeException::class, 'The count must be an integer, but "many" was given.'];
    yield 'row count negative' => [$content, 'csvAssertRowCount', ['-1'], \RuntimeException::class, 'The count must be 0 or greater, but "-1" was given.'];
    yield 'row missing' => [$content, 'csvAssertRowExists', [new TableNode([['clip_id', 'clip-1'], ['result', 'created']])], AssertionException::class, 'The CSV file "report.csv" has no row where the column "clip_id" has the value "clip-1" and the column "result" has the value "created".'];
    yield 'row present' => [$content, 'csvAssertRowNotExists', [new TableNode([['clip_id', 'clip-2']])], AssertionException::class, 'The CSV file "report.csv" has a row where the column "clip_id" has the value "clip-2", but it should not.'];
    yield 'row column missing' => [$content, 'csvAssertRowExists', [new TableNode([['status', 'failed']])], AssertionException::class, 'The CSV file "report.csv" has no column "status".'];
    yield 'negated row column missing' => [$content, 'csvAssertRowNotExists', [new TableNode([['status', 'failed']])], AssertionException::class, 'The CSV file "report.csv" has no column "status".'];
    yield 'row table with 1 column' => [$content, 'csvAssertRowExists', [new TableNode([['clip_id'], ['result']])], \RuntimeException::class, 'The row table must have 2 columns: the column name and the value.'];
    yield 'row table with 3 columns' => [$content, 'csvAssertRowExists', [new TableNode([['clip_id', 'clip-1', 'extra']])], \RuntimeException::class, 'The row table must have 2 columns: the column name and the value.'];
    yield 'row table naming a column twice' => [$content, 'csvAssertRowExists', [new TableNode([['clip_id', 'clip-1'], ['clip_id', 'clip-2']])], \RuntimeException::class, 'The row table names the column "clip_id" more than once.'];
    yield 'rows missing 1' => [$content, 'csvAssertRowsExist', [new TableNode([['clip_id', 'result'], ['clip-1', 'updated'], ['clip-2', 'failed']])], AssertionException::class, 'The CSV file "report.csv" has no row where the column "clip_id" has the value "clip-2" and the column "result" has the value "failed".'];
    yield 'rows present 1' => [$content, 'csvAssertRowsNotExist', [new TableNode([['clip_id', 'result'], ['clip-1', 'failed'], ['clip-2', 'created']])], AssertionException::class, 'The CSV file "report.csv" has a row where the column "clip_id" has the value "clip-2" and the column "result" has the value "created", but it should not.'];
    yield 'rows table without rows' => [$content, 'csvAssertRowsExist', [new TableNode([['clip_id', 'result']])], \RuntimeException::class, 'The rows table must list at least 1 row below the column names.'];
    yield 'rows table naming a column twice' => [$content, 'csvAssertRowsNotExist', [new TableNode([['clip_id', 'clip_id'], ['clip-1', 'clip-2']])], \RuntimeException::class, 'The rows table names the column "clip_id" more than once.'];
    yield 'exact rows with a missing column' => [$content, 'csvAssertHasRows', [new TableNode([['status'], ['ok'], ['ok']])], AssertionException::class, 'The CSV file "report.csv" has no column "status".'];
    yield 'exact rows fewer' => [$content, 'csvAssertHasRows', [new TableNode([['clip_id'], ['clip-1']])], AssertionException::class, 'The CSV file "report.csv" has 2 data row(s), but it should have 1.'];
    yield 'exact rows more' => [$content, 'csvAssertHasRows', [new TableNode([['clip_id'], ['clip-1'], ['clip-2'], ['clip-3']])], AssertionException::class, 'The CSV file "report.csv" has 2 data row(s), but it should have 3.'];
    yield 'exact rows in another order' => [$content, 'csvAssertHasRows', [new TableNode([['clip_id', 'result'], ['clip-2', 'created'], ['clip-1', 'updated']])], AssertionException::class, 'The data row 1 of the CSV file "report.csv" has the value "clip-1" in the column "clip_id", but it should have the value "clip-2".'];
    yield 'exact rows value differs' => [$content, 'csvAssertHasRows', [new TableNode([['clip_id', 'result'], ['clip-1', 'updated'], ['clip-2', 'failed']])], AssertionException::class, 'The data row 2 of the CSV file "report.csv" has the value "created" in the column "result", but it should have the value "failed".'];
    yield 'value missing' => [$content, 'csvAssertContains', ['Third'], AssertionException::class, 'The CSV file "report.csv" does not contain "Third".'];
    yield 'value present' => [$content, 'csvAssertNotContains', ['clip-2'], AssertionException::class, 'The CSV file "report.csv" contains "clip-2", but it should not.'];
    yield 'empty file' => ['', 'csvAssertRowCount', ['0'], AssertionException::class, 'The CSV file "report.csv" has no header row.'];
    yield 'blank file header' => ["\n", 'csvGetHeader', [], AssertionException::class, 'The CSV file "report.csv" has no header row.'];
    yield 'empty file value search' => ['', 'csvAssertNotContains', ['x'], AssertionException::class, 'The CSV file "report.csv" has no header row.'];
    yield 'duplicate column' => ["clip_id,result,clip_id\nclip-1,updated,clip-1\n", 'csvGetRows', [], AssertionException::class, 'The CSV file "report.csv" has the column "clip_id" more than once.'];
    yield 'short data row' => ["clip_id,result\nclip-1,updated\nclip-2\n", 'csvAssertRowCount', ['2'], AssertionException::class, 'The data row 2 of the CSV file "report.csv" has 1 value(s), but the header has 2 column(s).'];
    yield 'long data row' => ["clip_id,result\nclip-1,updated,extra\n", 'csvFindRow', [['clip_id' => 'clip-1']], AssertionException::class, 'The data row 1 of the CSV file "report.csv" has 3 value(s), but the header has 2 column(s).'];
  }

  /**
   * Tests that a step on a file that does not exist fails.
   *
   * @param string $method
   *   The step to call.
   * @param array<int, mixed> $args
   *   The arguments to call it with, after the path.
   */
  #[DataProvider('dataProviderStepOnMissingFileFails')]
  public function testStepOnMissingFileFails(string $method, array $args): void {
    $this->expectException(AssertionException::class);
    $this->expectExceptionMessage('The CSV file "missing.csv" does not exist.');

    $this->testObject->{$method}('missing.csv', ...$args);
  }

  public static function dataProviderStepOnMissingFileFails(): \Iterator {
    yield 'row count' => ['csvAssertRowCount', ['0']];
    yield 'negated column' => ['csvAssertNotHasColumn', ['result']];
    yield 'negated value' => ['csvAssertNotContains', ['x']];
    yield 'negated row' => ['csvAssertRowNotExists', [new TableNode([['clip_id', 'clip-1']])]];
  }

}

/**
 * Test implementation of CsvTrait.
 */
class CsvTraitTestImplementation extends WebRawContext {

  use CsvTrait;

  /**
   * Set the captured working directory.
   */
  public function testSetBaseDir(?string $directory): void {
    static::$csvBaseDir = $directory;
  }

  /**
   * Get the captured working directory.
   */
  public function testGetBaseDir(): ?string {
    return static::$csvBaseDir;
  }

}
