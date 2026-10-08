<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Steps\Web;

use Behat\Mink\Driver\DriverInterface;
use Behat\Mink\Exception\ExpectationException;
use Behat\Mink\Session;
use DrevOps\BehatSteps\Behat\Context\WebRawContext;
use DrevOps\BehatSteps\Steps\Web\FileDownloadTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Tests for FileDownloadTrait.
 */
#[CoversTrait(FileDownloadTrait::class)]
class FileDownloadTraitTest extends UnitTestCase {

  /**
   * A test implementation of FileDownloadTrait.
   *
   * @var \DrevOps\BehatSteps\Tests\Unit\Steps\Web\FileDownloadTraitTestImplementation
   */
  protected $testObject;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->testObject = new FileDownloadTraitTestImplementation(['file_download' => ['temp_dir' => static::$tmp]]);
  }

  public function testProcessWritesTheDownloadedFile(): void {
    $this->testObject->response = new MockResponse('a,b', ['response_headers' => ['Content-Type: text/csv', 'Content-Disposition: attachment; filename="report.csv"']]);

    $info = $this->testObject->fileDownloadProcess('http://example.com/export', ['max_duration' => 30]);

    $this->assertSame('report.csv', $info['file_name']);
    $this->assertSame('text/csv', $info['content_type']);
    $this->assertStringEqualsFile($info['file_path'], 'a,b');
    $this->assertSame(['max_duration' => 30, 'timeout' => 120], $this->testObject->detachedOptions);
  }

  public function testProcessNamesTheFileAfterTheUrl(): void {
    $this->testObject->response = new MockResponse('%PDF');

    $info = $this->testObject->fileDownloadProcess('http://example.com/files/manual.pdf');

    $this->assertSame('manual.pdf', $info['file_name']);
    $this->assertStringEqualsFile($info['file_path'], '%PDF');
  }

  public function testProcessTimeoutComesFromTheOption(): void {
    $object = new FileDownloadTraitTestImplementation(['file_download' => ['temp_dir' => static::$tmp, 'timeout' => 300]]);
    $object->response = new MockResponse('content');

    $object->fileDownloadProcess('http://example.com/files/large.zip');

    $this->assertSame(['timeout' => 300], $object->detachedOptions);
  }

  /**
   * Tests the responses a download rejects.
   *
   * @param \Symfony\Component\HttpClient\Response\MockResponse $response
   *   The response the server sends.
   * @param string $expected_message
   *   The exception message expected.
   */
  #[DataProvider('dataProviderProcessRejectsTheResponse')]
  public function testProcessRejectsTheResponse(MockResponse $response, string $expected_message): void {
    $this->testObject->response = $response;

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage($expected_message);

    $this->testObject->fileDownloadProcess('http://example.com/missing.pdf');
  }

  public static function dataProviderProcessRejectsTheResponse(): array {
    return [
      'an error status' => [new MockResponse('Not found', ['http_code' => 404]), 'The URL "http://example.com/missing.pdf" returned HTTP status 404.'],
      'an error status with an empty body' => [new MockResponse('', ['http_code' => 401]), 'The URL "http://example.com/missing.pdf" returned HTTP status 401.'],
      'an empty body' => [new MockResponse(''), 'Unable to save temp file from URL "http://example.com/missing.pdf".'],
      'an unreachable host' => [new MockResponse('', ['error' => 'Could not resolve host']), 'Unable to download file from URL "http://example.com/missing.pdf": Could not resolve host.'],
    ];
  }

  /**
   * Tests the file information read from the response headers.
   *
   * @param array<string, string|array<int, string>> $headers
   *   The response headers.
   * @param array<string, string> $expected
   *   The file information expected.
   */
  #[DataProvider('dataProviderParseHeaders')]
  public function testParseHeaders(array $headers, array $expected): void {
    $this->assertSame($expected, $this->testObject->fileDownloadParseHeaders($headers));
  }

  public static function dataProviderParseHeaders(): array {
    return [
      'a file name and a content type' => [['content-disposition' => ['attachment; filename="report.csv"'], 'content-type' => ['text/csv']], ['file_name' => 'report.csv', 'content_type' => 'text/csv']],
      'header names in another letter case' => [['Content-Type' => 'application/pdf'], ['content_type' => 'application/pdf']],
      'an inline disposition' => [['content-disposition' => ['inline']], []],
      'no headers' => [[], []],
    ];
  }

  #[DataProvider('dataProviderIsRegex')]
  public function testIsRegex(string $input, bool $expected): void {
    $result = $this->testObject->fileDownloadIsRegex($input);
    $this->assertSame($expected, $result);
  }

  public static function dataProviderIsRegex(): array {
    return [
      'case-insensitive flag' => ['/pattern/i', TRUE],
      'multiline flag' => ['/pattern/m', TRUE],
      'dotall flag' => ['/pattern/s', TRUE],
      'extended flag' => ['/pattern/x', TRUE],
      'unicode flag' => ['/pattern/u', TRUE],
      'every common flag at once' => ['/pattern/imsxu', TRUE],
      'no flags' => ['/pattern/', TRUE],
      'character class and quantifier' => ['/[a-z]+\d{3}/i', TRUE],
      'escaped delimiters inside the pattern' => ['/path\/to\/file/i', TRUE],
      'anchored flag' => ['/pattern/A', TRUE],
      'dollar-end-only flag' => ['/pattern/D', TRUE],
      'plain text' => ['simple text', FALSE],
      'a path with slashes' => ['path/to/file', FALSE],
      'an opening delimiter only' => ['/not a regex', FALSE],
      'a closing delimiter only' => ['not a regex/', FALSE],
      'an empty string' => ['', FALSE],
      'a lone delimiter' => ['/', FALSE],
      'delimiters around nothing' => ['//', FALSE],
      'the JavaScript global flag' => ['/pattern/g', FALSE],
      'flags including the JavaScript global flag' => ['/pattern/igm', FALSE],
      'an upper-case flag' => ['/pattern/I', FALSE],
      'numeric flags' => ['/pattern/123', FALSE],
      'a space before the flag' => ['/pattern/ i', FALSE],
      'a file path with an extension' => ['path/to/some/file.txt', FALSE],
    ];
  }

  /**
   * Tests that the download tag on either line prepares the directory.
   *
   * @param list<string> $scenario_tags
   *   Tags on the scenario.
   * @param list<string> $feature_tags
   *   Tags on the feature.
   * @param bool $expected
   *   Whether the directory is expected to be prepared.
   */
  #[DataProvider('dataProviderDownloadTagPreparesDirectory')]
  public function testDownloadTagPreparesDirectory(array $scenario_tags, array $feature_tags, bool $expected): void {
    $directory = static::$tmp . '/scenario';
    $context = new FileDownloadTraitTestImplementation(['file_download' => ['temp_dir' => $directory]]);

    $context->fileDownloadBeforeScenario($this->createBeforeScenarioScope($scenario_tags, $feature_tags));
    $this->assertSame($expected, is_dir($directory));

    $context->fileDownloadAfterScenario($this->createAfterScenarioScope($scenario_tags, $feature_tags));
    $this->assertDirectoryDoesNotExist($directory);
  }

  public static function dataProviderDownloadTagPreparesDirectory(): array {
    return [
      'on neither' => [['javascript'], ['api'], FALSE],
      'on the scenario' => [['download'], [], TRUE],
      'on the feature' => [[], ['download'], TRUE],
      'on both' => [['download'], ['download'], TRUE],
      'skipped on the feature' => [['download'], ['behat-steps-skip:FileDownloadTrait'], FALSE],
    ];
  }

  #[DataProvider('dataProviderFindLine')]
  public function testFindLine(string $search, ?string $expected): void {
    $this->testObject->response = new MockResponse("Line one\nLine two\n", ['response_headers' => ['Content-Disposition: attachment; filename="lines.txt"']]);
    $this->testObject->fileDownloadLoad('http://example.com/lines.txt');

    $this->assertSame($expected, $this->testObject->fileDownloadFindLine($search));
  }

  public static function dataProviderFindLine(): array {
    return [
      'text' => ['two', 'Line two'],
      'regular expression' => ['/^Line o/', 'Line one'],
      'missing text' => ['three', NULL],
      'missing match' => ['/^two/', NULL],
    ];
  }

  public function testFindLineRequiresDownload(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('No file has been downloaded. Download a file before asserting on it.');

    $this->testObject->fileDownloadFindLine('Line');
  }

  #[DataProvider('dataProviderAssertZipContainsFiles')]
  public function testAssertZipContainsFiles(array $names, bool $is_partial, ?string $expected_message): void {
    $this->loadZip();

    if ($expected_message !== NULL) {
      $this->expectException(ExpectationException::class);
      $this->expectExceptionMessage($expected_message);
    }

    $this->testObject->fileDownloadAssertZipContainsFiles($names, $is_partial);

    $this->addToAssertionCount(1);
  }

  public static function dataProviderAssertZipContainsFiles(): array {
    return [
      'exact names' => [['report.csv', 'images/logo.png'], FALSE, NULL],
      'partial names' => [['report', 'logo'], TRUE, NULL],
      'a missing exact name' => [['report.csv', 'missing.txt'], FALSE, 'Unable to find file "missing.txt" in archive.'],
      'a partial name as an exact name' => [['report'], FALSE, 'Unable to find file "report" in archive.'],
      'a missing partial name' => [['missing'], TRUE, 'Unable to find any file partially named "missing" in archive.'],
      'every missing name' => [['a.txt', 'b.txt'], FALSE, 'Unable to find file "a.txt" in archive.' . PHP_EOL . 'Unable to find file "b.txt" in archive.'],
    ];
  }

  #[DataProvider('dataProviderAssertZipNotContainsFiles')]
  public function testAssertZipNotContainsFiles(array $names, bool $is_partial, ?string $expected_message): void {
    $this->loadZip();

    if ($expected_message !== NULL) {
      $this->expectException(ExpectationException::class);
      $this->expectExceptionMessage($expected_message);
    }

    $this->testObject->fileDownloadAssertZipNotContainsFiles($names, $is_partial);

    $this->addToAssertionCount(1);
  }

  public static function dataProviderAssertZipNotContainsFiles(): array {
    return [
      'absent exact names' => [['missing.txt'], FALSE, NULL],
      'absent partial names' => [['missing'], TRUE, NULL],
      'a present exact name' => [['report.csv'], FALSE, 'Found file "report.csv" in archive, but it should not.'],
      'a present partial name' => [['logo'], TRUE, 'Found file partially named "logo" in archive, but it should not.'],
      'every present name' => [['report', 'logo'], TRUE, 'Found file partially named "report" in archive, but it should not.' . PHP_EOL . 'Found file partially named "logo" in archive, but it should not.'],
    ];
  }

  /**
   * Download a ZIP archive holding a report and a logo.
   */
  protected function loadZip(): void {
    $path = static::$tmp . '/source.zip';

    $zip = new \ZipArchive();
    $zip->open($path, \ZipArchive::CREATE);
    $zip->addFromString('report.csv', 'a,b');
    $zip->addFromString('images/logo.png', 'png');
    $zip->close();

    $this->testObject->session = new Session($this->createStub(DriverInterface::class));
    $this->testObject->response = new MockResponse((string) file_get_contents($path), ['response_headers' => ['Content-Type: application/zip', 'Content-Disposition: attachment; filename="archive.zip"']]);
    $this->testObject->fileDownloadLoad('http://example.com/archive.zip');
  }

}

/**
 * Test implementation of FileDownloadTrait.
 */
class FileDownloadTraitTestImplementation extends WebRawContext {

  use FileDownloadTrait {
    fileDownloadIsRegex as public;
    fileDownloadParseHeaders as public;
  }

  /**
   * The response the detached browser answers with.
   */
  public ?MockResponse $response = NULL;

  /**
   * The options the trait passed to httpDetachedClient().
   *
   * @var array<string, mixed>
   */
  public array $detachedOptions = [];

  /**
   * The session getSession() returns.
   */
  public ?Session $session = NULL;

  public function getSession(mixed $name = NULL): Session {
    return $this->session ?? throw new \RuntimeException('Set the session double before the trait reaches it.');
  }

  /**
   * {@inheritdoc}
   */
  public function httpDetachedClient(array $options = []): AbstractBrowser {
    $this->detachedOptions = $options;

    return new HttpBrowser(new MockHttpClient($this->response ?? new MockResponse('')));
  }

}
