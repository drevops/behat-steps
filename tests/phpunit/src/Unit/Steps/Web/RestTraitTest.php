<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Steps\Web;

use DrevOps\BehatSteps\Steps\Web\RestTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use DrevOps\BehatSteps\WebRawContext;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Tests for RestTrait.
 */
#[CoversTrait(RestTrait::class)]
class RestTraitTest extends UnitTestCase {

  /**
   * Tests the cookies a request carries and the cookies the jar keeps.
   *
   * @param bool $send_cookies
   *   The value of the 'rest.send_cookies' option.
   * @param string|null $expected_cookie
   *   The Cookie header the request is expected to carry, or NULL for none.
   * @param array<string, string> $expected_jar
   *   The cookies the jar is expected to hold after the request.
   */
  #[DataProvider('dataProviderSendCookies')]
  public function testSendCookies(bool $send_cookies, ?string $expected_cookie, array $expected_jar): void {
    $object = new RestTraitTestImplementation(['rest' => ['send_cookies' => $send_cookies]]);
    $object->response = new MockResponse('{"status": "ok"}', ['response_headers' => ['Set-Cookie: added=1; path=/']]);
    $object->testGetBrowser()->getCookieJar()->set(new Cookie('SESS', 'abc', NULL, '/', 'example.com'));

    $object->restSend('post', 'http://example.com/api', '{"name": "example"}');

    $this->assertSame('POST', $object->requests[0]['method']);
    $this->assertSame('{"name": "example"}', $object->requests[0]['body']);
    $this->assertSame($expected_cookie, $object->requests[0]['cookie']);
    $this->assertSame($expected_jar, $object->testGetBrowser()->getCookieJar()->allValues('http://example.com/'));
    $this->assertSame('{"status": "ok"}', $object->testGetBrowser()->getInternalResponse()->getContent());
  }

  public static function dataProviderSendCookies(): array {
    return [
      'cookies sent and kept' => [TRUE, 'cookie: SESS=abc', ['SESS' => 'abc', 'added' => '1']],
      'cookies withheld and restored' => [FALSE, NULL, ['SESS' => 'abc']],
    ];
  }

  public function testSendWithoutCookiesRestoresTheJarWhenTheRequestFails(): void {
    $object = new RestTraitTestImplementation(['rest' => ['send_cookies' => FALSE]]);
    $object->exception = new TransportException('Connection refused.');
    $object->testGetBrowser()->getCookieJar()->set(new Cookie('SESS', 'abc', NULL, '/', 'example.com'));

    try {
      $object->restSend('GET', 'http://example.com/api');
      $this->fail('The request should have failed.');
    }
    catch (TransportException $exception) {
      $this->assertSame('Connection refused.', $exception->getMessage());
    }

    $this->assertSame(['SESS' => 'abc'], $object->testGetBrowser()->getCookieJar()->allValues('http://example.com/'));
  }

  public function testSendWithoutCookiesRequiresBrowserKitClient(): void {
    $object = new RestTraitTestImplementation(['rest' => ['send_cookies' => FALSE]]);
    $object->customClient = new \stdClass();

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Sending a REST request without cookies requires "restGetClient()" to return a "Symfony\Component\BrowserKit\AbstractBrowser", but it returned a "stdClass".');

    $object->restSend('GET', 'http://example.com/api');
  }

}

/**
 * Test implementation of RestTrait.
 */
class RestTraitTestImplementation extends WebRawContext {

  use RestTrait;

  /**
   * The response the client answers with.
   */
  public ?MockResponse $response = NULL;

  /**
   * The exception the client throws instead of answering.
   */
  public ?\Throwable $exception = NULL;

  /**
   * The client restGetClient() returns instead of the browser.
   */
  public mixed $customClient = NULL;

  /**
   * The requests the client received.
   *
   * @var array<int, array{method: string, body: string, cookie: string|null}>
   */
  public array $requests = [];

  /**
   * The browser the client is built on.
   */
  protected ?HttpBrowser $browser = NULL;

  /**
   * {@inheritdoc}
   */
  public function restGetClient(): mixed {
    return $this->customClient ?? $this->testGetBrowser();
  }

  /**
   * Returns the browser, building it on first use.
   */
  public function testGetBrowser(): HttpBrowser {
    $this->browser ??= new HttpBrowser(new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
      if ($this->exception instanceof \Throwable) {
        throw $this->exception;
      }

      $this->requests[] = [
        'method' => $method,
        'body' => is_string($options['body'] ?? NULL) ? $options['body'] : '',
        'cookie' => $options['normalized_headers']['cookie'][0] ?? NULL,
      ];

      return $this->response ?? new MockResponse('');
    }));

    return $this->browser;
  }

}
