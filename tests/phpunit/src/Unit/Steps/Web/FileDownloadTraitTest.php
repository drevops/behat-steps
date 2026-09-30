<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Steps\Web;

use DrevOps\BehatSteps\Behat\Context\WebRawContext;
use DrevOps\BehatSteps\Steps\Web\FileDownloadTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Tests for FileDownloadTrait.
 */
#[CoversTrait(FileDownloadTrait::class)]
class FileDownloadTraitTest extends UnitTestCase {

  /**
   * Directory the download tests write into.
   */
  protected static string $downloadDir;

  /**
   * A test implementation of FileDownloadTrait.
   *
   * @var \DrevOps\BehatSteps\Tests\Unit\Steps\Web\FileDownloadTraitTestImplementation
   */
  protected $testObject;

  public static function setUpBeforeClass(): void {
    self::$downloadDir = dirname(__DIR__, 6) . '/.artifacts/tmp/file-download-' . getmypid();

    (new Filesystem())->mkdir(self::$downloadDir);
  }

  public static function tearDownAfterClass(): void {
    (new Filesystem())->remove(self::$downloadDir);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->testObject = new FileDownloadTraitTestImplementation(['file_download' => ['temp_dir' => self::$downloadDir]]);
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
    $object = new FileDownloadTraitTestImplementation(['file_download' => ['temp_dir' => self::$downloadDir, 'timeout' => 300]]);
    $object->response = new MockResponse('content');

    $object->fileDownloadProcess('http://example.com/files/large.zip');

    $this->assertSame(['timeout' => 300], $object->detachedOptions);
  }

  /**
   * Tests the responses a download rejects.
   *
   * @param \Symfony\Component\HttpClient\Response\MockResponse $response
   *   The response the server sends.
   * @param string $message
   *   The exception message expected.
   */
  #[DataProvider('dataProviderProcessRejectsTheResponse')]
  public function testProcessRejectsTheResponse(MockResponse $response, string $message): void {
    $this->testObject->response = $response;

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage($message);

    $this->testObject->fileDownloadProcess('http://example.com/missing.pdf');
  }

  public static function dataProviderProcessRejectsTheResponse(): array {
    return [
      'an error status' => [new MockResponse('Not found', ['http_code' => 404]), 'The URL http://example.com/missing.pdf returned HTTP status 404.'],
      'an error status with an empty body' => [new MockResponse('', ['http_code' => 401]), 'The URL http://example.com/missing.pdf returned HTTP status 401.'],
      'an empty body' => [new MockResponse(''), 'Unable to save temp file from URL http://example.com/missing.pdf.'],
      'an unreachable host' => [new MockResponse('', ['error' => 'Could not resolve host']), 'Unable to download file from URL http://example.com/missing.pdf: Could not resolve host'],
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
      ['/pattern/i', TRUE],
      ['/pattern/m', TRUE],
      ['/pattern/s', TRUE],
      ['/pattern/x', TRUE],
      ['/pattern/u', TRUE],
      ['/pattern/imsxu', TRUE],
      ['/pattern/', TRUE],
      ['/[a-z]+\d{3}/i', TRUE],
      ['/path\/to\/file/i', TRUE],
      ['/pattern/A', TRUE],
      ['/pattern/D', TRUE],
      ['simple text', FALSE],
      ['path/to/file', FALSE],
      ['/not a regex', FALSE],
      ['not a regex/', FALSE],
      ['', FALSE],
      ['/', FALSE],
      ['//', FALSE],
      ['/pattern/g', FALSE],
      ['/pattern/igm', FALSE],
      ['/pattern/I', FALSE],
      ['/pattern/123', FALSE],
      ['/pattern/ i', FALSE],
      ['path/to/some/file.txt', FALSE],
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
    $directory = static::$tmp . DIRECTORY_SEPARATOR . 'downloads';
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
   * The options the trait asked the detached browser for.
   *
   * @var array<string, mixed>
   */
  public array $detachedOptions = [];

  /**
   * {@inheritdoc}
   */
  public function httpDetachedClient(array $options = []): AbstractBrowser {
    $this->detachedOptions = $options;

    return new HttpBrowser(new MockHttpClient($this->response ?? new MockResponse('')));
  }

}
