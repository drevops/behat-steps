<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Steps\Web;

use Behat\Mink\Driver\BrowserKitDriver;
use Behat\Mink\Driver\DriverInterface;
use Behat\Mink\Exception\ExpectationException;
use Behat\Mink\Session;
use DrevOps\BehatSteps\Steps\Web\RestTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use DrevOps\BehatSteps\WebRawContext;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Tests for RestTrait.
 */
#[CoversTrait(RestTrait::class)]
class RestTraitTest extends UnitTestCase {

  /**
   * Tests the headers a REST request carries.
   *
   * @param array<string, string> $request_headers
   *   The scenario's request headers.
   * @param array<string, string> $rest_headers
   *   The REST headers.
   * @param array<string, string> $expected
   *   The headers expected on the request, keyed by lowercase name.
   */
  #[DataProvider('dataProviderSendHeaders')]
  public function testSendHeaders(array $request_headers, array $rest_headers, array $expected): void {
    $object = new RestTraitTestImplementation();

    foreach ($request_headers as $name => $value) {
      $object->requestHeadersSet($name, $value);
    }

    foreach ($rest_headers as $name => $value) {
      $object->restSetHeader($name, $value);
    }

    $object->restSend('post', 'http://example.com/api', '{"name": "example"}');

    $this->assertSame('POST', $object->requests[0]['method']);
    $this->assertSame('{"name": "example"}', $object->requests[0]['body']);
    $this->assertSame($expected, array_intersect_key($object->requests[0]['headers'], $expected));
    $this->assertSame(200, $object->restResponseGetStatusCode());
    $this->assertSame('{"status": "ok"}', $object->restResponseGetContent());
  }

  public static function dataProviderSendHeaders(): array {
    return [
      'scenario headers' => [['X-Config-No-Override' => 'system.site'], [], ['x-config-no-override' => 'system.site']],
      'REST headers' => [[], ['Accept' => 'application/json', 'Content-Type' => 'application/json'], ['accept' => 'application/json', 'content-type' => 'application/json']],
      'REST header replaces a scenario header' => [['Authorization' => 'Basic YTpi'], ['Authorization' => 'Bearer t1'], ['authorization' => 'Bearer t1']],
      'REST header replaces a scenario header in another case' => [['authorization' => 'Basic YTpi'], ['Authorization' => 'Bearer t1'], ['authorization' => 'Bearer t1']],
    ];
  }

  public function testBeforeScenarioClearsRestHeaders(): void {
    $object = new RestTraitTestImplementation();
    $object->restSetHeader('X-Custom', 'value');

    $object->restBeforeScenario($this->createBeforeScenarioScope());
    $object->restSend('GET', 'http://example.com/api');

    $this->assertArrayNotHasKey('x-custom', $object->requests[0]['headers']);
  }

  /**
   * Tests the reads that fail before any REST request.
   *
   * @param string $method
   *   The helper to call.
   * @param array<int, string> $arguments
   *   The arguments to call it with.
   */
  #[DataProvider('dataProviderResponseRequiresRequest')]
  public function testResponseRequiresRequest(string $method, array $arguments): void {
    $object = new RestTraitTestImplementation();

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('No REST request has been sent in this scenario. The REST, JSON and XML steps read the response of "When I send a REST :method request to :url", not the page the browser loaded.');

    $object->{$method}(...$arguments);
  }

  public static function dataProviderResponseRequiresRequest(): array {
    return [
      'content' => ['restResponseGetContent', []],
      'status code' => ['restResponseGetStatusCode', []],
      'header' => ['restResponseFindHeader', ['Content-Type']],
    ];
  }

  public function testResponseFindsHeader(): void {
    $object = new RestTraitTestImplementation();
    $object->restSend('GET', 'http://example.com/api');

    $this->assertSame('application/json', $object->restResponseFindHeader('content-type'));
    $this->assertNull($object->restResponseFindHeader('X-Nonexistent-Header'));
  }

  /**
   * Tests setting a REST header from a JSON path in the last response.
   *
   * @param string $path
   *   The JSONPath expression.
   * @param string $prefix
   *   The prefix, or an empty string to use the step without one.
   * @param string $expected
   *   The header value expected on the next request.
   */
  #[DataProvider('dataProviderSetHeaderFromJsonPath')]
  public function testSetHeaderFromJsonPath(string $path, string $prefix, string $expected): void {
    $object = new RestTraitTestImplementation();
    $object->responseBody = '{"access_token": "t1", "expires_in": 3600, "ratio": 1.5, "refresh": true, "revoked": false}';
    $object->restSend('POST', 'http://example.com/oauth/token');

    if ($prefix === '') {
      $object->restSetHeaderFromJsonPath('X-Token', $path);
    }
    else {
      $object->restSetHeaderFromJsonPathWithPrefix('X-Token', $path, $prefix);
    }

    $object->restSend('GET', 'http://example.com/api');

    $this->assertSame($expected, $object->requests[1]['headers']['x-token']);
  }

  public static function dataProviderSetHeaderFromJsonPath(): array {
    return [
      'string' => ['$.access_token', '', 't1'],
      'integer' => ['$.expires_in', '', '3600'],
      'float' => ['$.ratio', '', '1.5'],
      'true' => ['$.refresh', '', 'true'],
      'false' => ['$.revoked', '', 'false'],
      'prefix' => ['$.access_token', 'Bearer ', 'Bearer t1'],
      'prefix without a space' => ['$.access_token', 'token=', 'token=t1'],
    ];
  }

  /**
   * Tests that a JSON path read returns the value it matched, NULL included.
   *
   * @param string $body
   *   The response body.
   * @param mixed $expected
   *   The value expected.
   */
  #[DataProvider('dataProviderGetResponseJsonValue')]
  public function testGetResponseJsonValue(string $body, mixed $expected): void {
    $object = new RestTraitTestImplementation();
    $object->responseBody = $body;
    $object->restSend('GET', 'http://example.com/api');

    $this->assertSame($expected, $object->restGetResponseJsonValue('$.token'));
  }

  public static function dataProviderGetResponseJsonValue(): array {
    return [
      'string' => ['{"token": "t1"}', 't1'],
      'integer' => ['{"token": 3600}', 3600],
      'float' => ['{"token": 1.5}', 1.5],
      'true' => ['{"token": true}', TRUE],
      'false' => ['{"token": false}', FALSE],
      'null' => ['{"token": null}', NULL],
    ];
  }

  /**
   * Tests that the steps setting a header fail on a JSON null.
   *
   * @param string $prefix
   *   The header prefix, or an empty string to use the step without a prefix.
   */
  #[DataProvider('dataProviderSetHeaderFromJsonPathFailsOnNull')]
  public function testSetHeaderFromJsonPathFailsOnNull(string $prefix): void {
    $object = new RestTraitTestImplementation();
    $object->responseBody = '{"token": null}';
    $object->restSend('GET', 'http://example.com/api');

    $this->expectException(ExpectationException::class);
    $this->expectExceptionMessage('The JSON path "$.token" is null in the REST response, but a value is required.');

    if ($prefix === '') {
      $object->restSetHeaderFromJsonPath('X-Token', '$.token');

      return;
    }

    $object->restSetHeaderFromJsonPathWithPrefix('X-Token', '$.token', $prefix);
  }

  public static function dataProviderSetHeaderFromJsonPathFailsOnNull(): array {
    return [
      'without a prefix' => [''],
      'with a prefix' => ['Bearer '],
    ];
  }

  /**
   * Tests the JSON path reads that fail on the last response.
   *
   * @param string $body
   *   The response body.
   * @param string $path
   *   The JSONPath expression.
   * @param class-string<\Throwable> $expected_exception
   *   The exception class.
   * @param string $expected_message
   *   The exception message.
   */
  #[DataProvider('dataProviderGetResponseJsonValueThrows')]
  public function testGetResponseJsonValueThrows(string $body, string $path, string $expected_exception, string $expected_message): void {
    $object = new RestTraitTestImplementation();
    $object->responseBody = $body;
    $object->restSend('GET', 'http://example.com/api');

    $this->expectException($expected_exception);
    $this->expectExceptionMessage($expected_message);

    $object->restGetResponseJsonValue($path);
  }

  public static function dataProviderGetResponseJsonValueThrows(): array {
    return [
      'not JSON' => ['<html></html>', '$.token', \RuntimeException::class, 'Failed to decode JSON: Syntax error.'],
      'missing' => ['{"token": "t1"}', '$.missing', ExpectationException::class, 'The JSON path "$.missing" was not found.'],
      'container' => ['{"token": {"value": "t1"}}', '$.token', ExpectationException::class, 'The JSON path "$.token" resolves to an array or object, but a scalar value is required for this assertion.'],
      'several' => ['{"tokens": ["t1", "t2"]}', '$.tokens[*]', ExpectationException::class, 'The JSON path "$.tokens[*]" matched 2 values, but a single value is required for this assertion.'],
    ];
  }

  public function testGetResponseJsonValueRequiresRequest(): void {
    $object = new RestTraitTestImplementation();

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('No REST request has been sent in this scenario.');

    $object->restGetResponseJsonValue('$.token');
  }

  public function testSendRequestWithBodyFromFile(): void {
    $this->writeFixture('fixtures/items.xml', '<items><item>1</item></items>');
    $object = new RestTraitTestImplementation();
    $object->setMinkParameters(['files_path' => static::$tmp . '/fixtures']);

    $object->restSendRequestWithBodyFromFile('put', 'http://example.com/api/import', 'items.xml');

    $this->assertSame('PUT', $object->requests[0]['method']);
    $this->assertSame('<items><item>1</item></items>', $object->requests[0]['body']);
  }

  public function testSendRequestWithBodyFromFileRequiresFile(): void {
    mkdir(static::$tmp . '/fixtures');
    $object = new RestTraitTestImplementation();
    $object->setMinkParameters(['files_path' => static::$tmp . '/fixtures']);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('items.xml" does not exist.');

    $object->restSendRequestWithBodyFromFile('POST', 'http://example.com/api/import', 'items.xml');
  }

  public function testGetBrowserRequiresBrowserKitSession(): void {
    $driver = $this->createMock(DriverInterface::class);
    $object = new RestTraitTestImplementation();
    $object->session = new Session($driver);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage(sprintf('The "rest" Mink session must run the BrowserKit browser driver, but it runs "%s".', $driver::class));

    $object->restGetBrowser();
  }

}

/**
 * Test implementation of RestTrait.
 */
class RestTraitTestImplementation extends WebRawContext {

  use RestTrait;

  /**
   * The session httpRestSession() returns.
   */
  public ?Session $session = NULL;

  /**
   * The body of every response the REST session's browser receives.
   */
  public string $responseBody = '{"status": "ok"}';

  /**
   * The requests the REST session's browser received.
   *
   * @var array<int, array{method: string, body: string, headers: array<string, string>}>
   */
  public array $requests = [];

  /**
   * {@inheritdoc}
   */
  public function httpRestSession(): Session {
    $this->session ??= new Session(new BrowserKitDriver(new HttpBrowser(new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
      $headers = [];

      foreach ($options['normalized_headers'] as $name => $lines) {
        $headers[$name] = substr((string) $lines[0], strlen($name) + 2);
      }

      $this->requests[] = [
        'method' => $method,
        'body' => is_string($options['body'] ?? NULL) ? $options['body'] : '',
        'headers' => $headers,
      ];

      return new MockResponse($this->responseBody, ['response_headers' => ['Content-Type: application/json']]);
    }))));

    return $this->session;
  }

}
