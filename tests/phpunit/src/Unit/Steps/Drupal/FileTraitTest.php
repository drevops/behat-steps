<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Steps\Drupal;

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Driver\DriverInterface;
use Behat\Mink\Exception\ExpectationException;
use Behat\Mink\Mink;
use Behat\Mink\Session;
use DrevOps\BehatSteps\Backend\BlackboxBackendInterface;
use DrevOps\BehatSteps\Backend\Drupal\DrupalBackendInterface;
use DrevOps\BehatSteps\Backend\Drupal\DrushBackendInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Extension\Registry\BackendRegistry;
use DrevOps\BehatSteps\Steps\Drupal\FileTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use DrevOps\BehatSteps\WebRawContext;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests the managed file module checks, the setup hook and the CSV steps.
 */
#[CoversTrait(FileTrait::class)]
class FileTraitTest extends UnitTestCase {

  /**
   * Tests that a managed file step or helper fails without the file module.
   *
   * The trait's 'enabled' option switches off its hooks but not a step, so the
   * message names no way to switch the trait off.
   *
   * @param string $method
   *   The step or helper to call.
   * @param array<int, mixed> $args
   *   The arguments to call it with.
   */
  #[DataProvider('dataProviderMissingModuleFails')]
  public function testMissingModuleFails(string $method, array $args): void {
    $drupal = $this->createStub(DrupalBackendInterface::class);
    $drupal->method('moduleIsEnabled')->willReturn(FALSE);

    try {
      $this->createContext($drupal)->{$method}(...$args);
      $this->fail(sprintf('%s() ran on a site without the file module.', $method));
    }
    catch (\RuntimeException $exception) {
      $this->assertSame('FileTrait requires that the core "file" module is enabled, for the managed file steps, which does not hold.', $exception->getMessage());
    }
  }

  public static function dataProviderMissingModuleFails(): \Iterator {
    $stub = new EntityStub('file', NULL, ['status' => 1]);

    yield 'fileCreateManagedMultiple' => ['fileCreateManagedMultiple', [new TableNode([['path'], ['document.pdf']])]];
    yield 'fileDeleteManagedMultiple' => ['fileDeleteManagedMultiple', [new TableNode([['filename'], ['document.pdf']])]];
    yield 'fileCreateManaged' => ['fileCreateManaged', ['document.pdf', $stub]];
    yield 'fileCreateEntity' => ['fileCreateEntity', ['document.pdf', $stub]];
    yield 'fileLoadMultiple' => ['fileLoadMultiple', []];
  }

  public function testUnmanagedFileStepsRunWithoutTheModule(): void {
    $drupal = $this->createMock(DrupalBackendInterface::class);
    $drupal->expects($this->never())->method('moduleIsEnabled');
    $drupal->method('fileExistsUnmanaged')->willReturnMap([['public://present.txt', TRUE], ['public://missing.txt', FALSE]]);
    $drupal->method('fileReadUnmanaged')->with('public://present.txt')->willReturn('debug=true');

    $context = $this->createContext($drupal);

    $context->fileAssertUnmanagedExists('public://present.txt');
    $context->fileAssertUnmanagedNotExists('public://missing.txt');
    $context->fileAssertUnmanagedContains('public://present.txt', 'debug=true');
    $context->fileAssertUnmanagedNotContains('public://present.txt', 'debug=false');
  }

  public function testWriteUnmanagedWritesThroughTheBackendAndTeardownDeletesTheFiles(): void {
    $drupal = $this->createMock(DrupalBackendInterface::class);
    $drupal->expects($this->once())->method('fileWriteUnmanaged')->with('public://sample.txt', 'content');
    $drupal->expects($this->once())->method('fileDeleteUnmanaged')->with('public://sample.txt');

    $context = $this->createContext($drupal);

    $context->fileCreateUnmanagedWithContent('public://sample.txt', 'content');
    $context->fileAfterScenario($this->createAfterScenarioScope());
    $context->fileAfterScenario($this->createAfterScenarioScope());
  }

  public function testTeardownOfScenarioThatWroteNothingTouchesNoBackend(): void {
    $this->expectNotToPerformAssertions();

    // The context holds no backend registry, so resolving a backend would
    // throw.
    (new FileTraitTestImplementation())->fileAfterScenario($this->createAfterScenarioScope());
  }

  public function testSetupHookPreparesTheDirectories(): void {
    $drupal = $this->createMock(DrupalBackendInterface::class);
    $drupal->expects($this->exactly(2))->method('filePrepareDirectory')->with($this->logicalOr('private://', 'temporary://'))->willReturn(FALSE);

    $this->createContext($drupal)->fileBeforeScenario($this->createBeforeScenarioScope());
  }

  /**
   * Tests that the setup hook bootstraps nothing without a backend writing files.
   *
   * @param array<string, class-string<\DrevOps\BehatSteps\Backend\BackendInterface>> $backends
   *   The backend interfaces the scenario lists, keyed by name.
   */
  #[DataProvider('dataProviderSetupHookSkipsSuiteWithoutInProcessBackend')]
  public function testSetupHookSkipsSuiteWithoutInProcessBackend(array $backends): void {
    $doubles = [];

    foreach ($backends as $name => $interface) {
      $backend = $this->createMock($interface);
      $backend->expects($this->never())->method('bootstrap');
      $doubles[$name] = $backend;
    }

    $registry = new BackendRegistry($doubles);
    $registry->setScenarioBackends(array_combine(array_keys($doubles), array_keys($doubles)));

    $context = new FileTraitTestImplementation();
    $context->setBackendRegistry($registry);

    $context->fileBeforeScenario($this->createBeforeScenarioScope());
  }

  public static function dataProviderSetupHookSkipsSuiteWithoutInProcessBackend(): \Iterator {
    yield 'drush' => [['drush' => DrushBackendInterface::class]];
    yield 'blackbox' => [['blackbox' => BlackboxBackendInterface::class]];
    yield 'drush and blackbox' => [['drush' => DrushBackendInterface::class, 'blackbox' => BlackboxBackendInterface::class]];
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
  #[DataProvider('dataProviderParseCsv')]
  public function testParseCsv(string $content, array $args, array $expected): void {
    $this->assertSame($expected, (new FileTraitTestImplementation())->fileParseCsv($content, ...$args));
  }

  public static function dataProviderParseCsv(): \Iterator {
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

  public function testCsvStepsAndHelpersPassOnMatchingFile(): void {
    $context = $this->createContext($this->createCsvBackend("clip_id,title,result\nclip-1,\"First, clip\",updated\nclip-2,Second,created\n"));

    $context->fileAssertCsvHasColumns('temporary://report.csv', new TableNode([['clip_id', 'title', 'result']]));
    $context->fileAssertCsvRowCount('temporary://report.csv', '2');
    $context->fileAssertCsvRowExists('temporary://report.csv', new TableNode([['result', 'updated'], ['clip_id', 'clip-1']]));
    $context->fileAssertCsvRowNotExists('temporary://report.csv', new TableNode([['clip_id', 'clip-1'], ['result', 'created']]));

    $this->assertSame(['clip_id', 'title', 'result'], $context->fileGetCsvHeader('temporary://report.csv'));
    $this->assertSame([['clip_id' => 'clip-1', 'title' => 'First, clip', 'result' => 'updated'], ['clip_id' => 'clip-2', 'title' => 'Second', 'result' => 'created']], $context->fileGetCsvRows('temporary://report.csv'));
    $this->assertSame(['clip_id' => 'clip-2', 'title' => 'Second', 'result' => 'created'], $context->fileFindCsvRow('temporary://report.csv', ['result' => 'created']));
    $this->assertNull($context->fileFindCsvRow('temporary://report.csv', ['result' => 'failed']));
  }

  public function testCsvRowCountOfHeaderOnlyFileIsZero(): void {
    $this->expectNotToPerformAssertions();

    $this->createContext($this->createCsvBackend("clip_id,result\n"))->fileAssertCsvRowCount('temporary://report.csv', '0');
  }

  /**
   * Tests that a CSV step fails with a message naming what went wrong.
   *
   * @param string $content
   *   The CSV content the file holds.
   * @param string $method
   *   The step or helper to call.
   * @param array<int, mixed> $args
   *   The arguments to call it with, after the URI.
   * @param class-string<\Throwable> $expected_class
   *   The expected exception class.
   * @param string $expected_message
   *   The expected exception message.
   */
  #[DataProvider('dataProviderCsvFailure')]
  public function testCsvFailure(string $content, string $method, array $args, string $expected_class, string $expected_message): void {
    $context = $this->createContext($this->createCsvBackend($content));

    $this->expectException($expected_class);
    $this->expectExceptionMessage($expected_message);

    $context->{$method}('temporary://report.csv', ...$args);
  }

  public static function dataProviderCsvFailure(): \Iterator {
    $content = "clip_id,title,result\nclip-1,First,updated\nclip-2,Second,created\n";

    yield 'row missing' => [$content, 'fileAssertCsvRowExists', [new TableNode([['clip_id', 'clip-1'], ['result', 'created']])], ExpectationException::class, 'The CSV file "temporary://report.csv" has no row where the column "clip_id" has the value "clip-1" and the column "result" has the value "created".'];
    yield 'row present' => [$content, 'fileAssertCsvRowNotExists', [new TableNode([['clip_id', 'clip-2']])], ExpectationException::class, 'The CSV file "temporary://report.csv" has a row where the column "clip_id" has the value "clip-2", but it should not.'];
    yield 'row column missing' => [$content, 'fileAssertCsvRowExists', [new TableNode([['status', 'failed']])], ExpectationException::class, 'The CSV file "temporary://report.csv" has no column "status".'];
    yield 'negated row column missing' => [$content, 'fileAssertCsvRowNotExists', [new TableNode([['status', 'failed']])], ExpectationException::class, 'The CSV file "temporary://report.csv" has no column "status".'];
    yield 'row count' => [$content, 'fileAssertCsvRowCount', ['3'], ExpectationException::class, 'The CSV file "temporary://report.csv" has 2 data row(s), but it should have 3.'];
    yield 'row count not an integer' => [$content, 'fileAssertCsvRowCount', ['many'], \RuntimeException::class, 'The count must be an integer, but "many" was given.'];
    yield 'row count negative' => [$content, 'fileAssertCsvRowCount', ['-1'], \RuntimeException::class, 'The count must be 0 or greater, but "-1" was given.'];
    yield 'columns differ' => [$content, 'fileAssertCsvHasColumns', [new TableNode([['clip_id', 'result']])], ExpectationException::class, 'The CSV file "temporary://report.csv" has the columns "clip_id", "title", "result", but it should have the columns "clip_id", "result".'];
    yield 'columns in another order' => [$content, 'fileAssertCsvHasColumns', [new TableNode([['clip_id', 'result', 'title']])], ExpectationException::class, 'The CSV file "temporary://report.csv" has the columns "clip_id", "title", "result", but it should have the columns "clip_id", "result", "title".'];
    yield 'columns table with 2 rows' => [$content, 'fileAssertCsvHasColumns', [new TableNode([['clip_id'], ['result']])], \RuntimeException::class, 'The columns table must list the column names in 1 row.'];
    yield 'row table with 1 column' => [$content, 'fileAssertCsvRowExists', [new TableNode([['clip_id'], ['result']])], \RuntimeException::class, 'The row table must have 2 columns: the column name and the value.'];
    yield 'row table with 3 columns' => [$content, 'fileAssertCsvRowExists', [new TableNode([['clip_id', 'clip-1', 'extra']])], \RuntimeException::class, 'The row table must have 2 columns: the column name and the value.'];
    yield 'row table naming a column twice' => [$content, 'fileAssertCsvRowExists', [new TableNode([['clip_id', 'clip-1'], ['clip_id', 'clip-2']])], \RuntimeException::class, 'The row table names the column "clip_id" more than once.'];
    yield 'empty file' => ['', 'fileAssertCsvRowCount', ['0'], ExpectationException::class, 'The CSV file "temporary://report.csv" has no header row.'];
    yield 'empty file header' => ["\n", 'fileGetCsvHeader', [], ExpectationException::class, 'The CSV file "temporary://report.csv" has no header row.'];
    yield 'duplicate column' => ["clip_id,result,clip_id\nclip-1,updated,clip-1\n", 'fileGetCsvRows', [], ExpectationException::class, 'The CSV file "temporary://report.csv" has the column "clip_id" more than once.'];
    yield 'short data row' => ["clip_id,result\nclip-1,updated\nclip-2\n", 'fileAssertCsvRowCount', ['2'], ExpectationException::class, 'The data row 2 of the CSV file "temporary://report.csv" has 1 value(s), but the header has 2 column(s).'];
    yield 'long data row' => ["clip_id,result\nclip-1,updated,extra\n", 'fileFindCsvRow', [['clip_id' => 'clip-1']], ExpectationException::class, 'The data row 1 of the CSV file "temporary://report.csv" has 3 value(s), but the header has 2 column(s).'];
  }

  public function testCsvStepOnMissingFileFails(): void {
    $drupal = $this->createStub(DrupalBackendInterface::class);
    $drupal->method('fileExistsUnmanaged')->willReturn(FALSE);

    $this->expectException(ExpectationException::class);
    $this->expectExceptionMessage('The file "temporary://missing.csv" does not exist.');

    $this->createContext($drupal)->fileAssertCsvRowCount('temporary://missing.csv', '0');
  }

  /**
   * Creates a Drupal backend holding 1 CSV file at 'temporary://report.csv'.
   *
   * @param string $content
   *   The CSV content.
   */
  protected function createCsvBackend(string $content): DrupalBackendInterface {
    $drupal = $this->createStub(DrupalBackendInterface::class);
    $drupal->method('fileExistsUnmanaged')->willReturnMap([['temporary://report.csv', TRUE]]);
    $drupal->method('fileReadUnmanaged')->willReturnMap([['temporary://report.csv', $content]]);

    return $drupal;
  }

  /**
   * Creates a context backed by the given Drupal backend.
   *
   * @param \DrevOps\BehatSteps\Backend\Drupal\DrupalBackendInterface $drupal
   *   The backend the scenario lists.
   */
  protected function createContext(DrupalBackendInterface $drupal): FileTraitTestImplementation {
    $registry = new BackendRegistry(['drupal' => $drupal]);
    $registry->setScenarioBackends(['drupal' => 'drupal']);

    $session = $this->createStub(Session::class);
    $session->method('getDriver')->willReturn($this->createStub(DriverInterface::class));

    $mink = new Mink(['default' => $session]);
    $mink->setDefaultSessionName('default');

    $context = new FileTraitTestImplementation();
    $context->setBackendRegistry($registry);
    $context->setMink($mink);

    return $context;
  }

}

/**
 * Test implementation of FileTrait.
 */
class FileTraitTestImplementation extends WebRawContext {

  use FileTrait;

}
