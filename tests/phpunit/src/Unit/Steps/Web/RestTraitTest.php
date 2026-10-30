<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Steps\Web;

use Behat\Mink\Driver\BrowserKitDriver;
use Behat\Mink\Driver\DriverInterface;
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

      return new MockResponse('{"status": "ok"}', ['response_headers' => ['Content-Type: application/json']]);
    }))));

    return $this->session;
  }

}
