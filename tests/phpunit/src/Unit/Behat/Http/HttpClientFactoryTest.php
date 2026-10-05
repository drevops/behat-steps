<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Http;

use DrevOps\BehatSteps\Behat\Http\HttpClientFactory;
use DrevOps\BehatSteps\Behat\Http\HttpIdentity;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Tests the shared transport and the browsers built on it.
 */
#[CoversClass(HttpClientFactory::class)]
#[CoversClass(HttpIdentity::class)]
class HttpClientFactoryTest extends UnitTestCase {

  /**
   * The options each request reached the transport with, in request order.
   *
   * @var array<int, array{url: string, options: array<string, mixed>}>
   */
  protected array $requests = [];

  public function testTransportWithoutOptionsIsTheClient(): void {
    $client = $this->recordingClient();

    $this->assertSame($client, HttpClientFactory::createTransport([], 'http://example.com', $client));
  }

  /**
   * Tests which requests the transport applies the site's options to.
   *
   * @param string $base_url
   *   The site's base URL.
   * @param string $url
   *   The URL requested.
   * @param bool $expected
   *   Whether the request is expected to carry the site's options.
   */
  #[DataProvider('dataProviderTransportScopesOptionsToTheSite')]
  public function testTransportScopesOptionsToTheSite(string $base_url, string $url, bool $expected): void {
    $transport = HttpClientFactory::createTransport(['headers' => ['X-Site' => 'yes']], $base_url, $this->recordingClient());

    $transport->request('GET', $url)->getContent();

    $this->assertSame($expected ? 'yes' : NULL, $this->requestHeader(0, 'X-Site'));
  }

  public static function dataProviderTransportScopesOptionsToTheSite(): \Iterator {
    yield 'the site' => ['http://example.com:8080', 'http://example.com:8080/page', TRUE];
    yield 'the site with credentials in the URL' => ['http://example.com:8080', 'http://bob:pw@example.com:8080/page', TRUE];
    yield 'the site with credentials in the base URL' => ['http://bob:pw@example.com:8080', 'http://example.com:8080/page', TRUE];
    yield 'the site in another letter case' => ['http://Example.com:8080', 'http://EXAMPLE.com:8080/page', TRUE];
    yield 'the site with its default port spelled out' => ['https://example.com:443', 'https://example.com/page', TRUE];
    yield 'the site root with no path' => ['http://example.com', 'http://example.com', TRUE];
    yield 'another port on the same host' => ['http://example.com:8080', 'http://example.com:8081/page', FALSE];
    yield 'a host the site name is a prefix of' => ['http://example.com', 'http://example.com.test/page', FALSE];
    yield 'another scheme' => ['http://example.com', 'https://example.com/page', FALSE];
    yield 'a third-party host' => ['http://example.com', 'https://cdn.example.org/engine.js', FALSE];
  }

  public function testTransportAppliesOptionsEverywhereWithoutSiteHost(): void {
    $transport = HttpClientFactory::createTransport(['headers' => ['X-Site' => 'yes']], NULL, $this->recordingClient());

    $transport->request('GET', 'https://cdn.example.org/engine.js')->getContent();

    $this->assertSame('yes', $this->requestHeader(0, 'X-Site'));
  }

  public function testBareBrowserCarriesTheSettingsAndNoState(): void {
    $factory = new HttpClientFactory($this->recordingClient(), 'http://example.com');

    $browser = $factory->createBare(['timeout' => 7]);
    $browser->request('GET', 'http://example.com/file');

    $this->assertInstanceOf(HttpBrowser::class, $browser);
    $this->assertSame('Response body', $browser->getInternalResponse()->getContent());
    $this->assertEqualsWithDelta(7.0, $this->requests[0]['options']['timeout'], 0.001);
    $this->assertNull($this->requestHeader(0, 'Cookie'));
    $this->assertNull($this->requestHeader(0, 'Authorization'));
  }

  /**
   * Tests which requests a detached browser sends the identity with.
   *
   * @param string $url
   *   The URL requested.
   * @param string|null $cookie
   *   The Cookie header expected, or NULL for none.
   * @param string|null $token
   *   The step-set header expected, or NULL for none.
   * @param string|null $authorization
   *   The Authorization header expected, or NULL for none.
   */
  #[DataProvider('dataProviderDetachedBrowserSendsTheIdentityToTheSite')]
  public function testDetachedBrowserSendsTheIdentityToTheSite(string $url, ?string $cookie, ?string $token, ?string $authorization): void {
    $factory = new HttpClientFactory($this->recordingClient(), 'http://example.com');
    $identity = new HttpIdentity(['SESS' => 'abc'], 'http://example.com/page', ['X-Token' => 't1'], ['username' => 'bob', 'password' => 'pw']);

    $factory->createDetached($identity)->request('GET', $url);

    $this->assertSame($cookie, $this->requestHeader(0, 'Cookie'));
    $this->assertSame($token, $this->requestHeader(0, 'X-Token'));
    $this->assertSame($authorization, $this->requestHeader(0, 'Authorization'));
  }

  public static function dataProviderDetachedBrowserSendsTheIdentityToTheSite(): \Iterator {
    yield 'the site' => ['http://example.com/file', 'SESS=abc', 't1', 'Basic ' . base64_encode('bob:pw')];
    yield 'a subdomain of the site' => ['http://files.example.com/file', 'SESS=abc', NULL, NULL];
    yield 'a third-party host' => ['https://cdn.example.org/file', NULL, NULL, NULL];
  }

  public function testDetachedAuthorizationHeaderWinsOverCredentials(): void {
    $factory = new HttpClientFactory($this->recordingClient(), 'http://example.com');
    $identity = new HttpIdentity([], '', ['Authorization' => 'Bearer t2'], ['username' => 'bob', 'password' => 'pw']);

    $factory->createDetached($identity)->request('GET', 'http://example.com/file');

    $this->assertSame('Bearer t2', $this->requestHeader(0, 'Authorization'));
  }

  public function testDetachedBrowserWithoutPageSendsNoCookies(): void {
    $factory = new HttpClientFactory($this->recordingClient(), 'http://example.com');

    $factory->createDetached(new HttpIdentity(['SESS' => 'abc']))->request('GET', 'http://example.com/file');

    $this->assertNull($this->requestHeader(0, 'Cookie'));
  }

  public function testDetachedBrowserWithoutSiteHostSendsOnlyCookies(): void {
    $factory = new HttpClientFactory($this->recordingClient());
    $identity = new HttpIdentity(['SESS' => 'abc'], 'http://example.com/page', ['X-Token' => 't1'], ['username' => 'bob', 'password' => 'pw']);

    $factory->createDetached($identity, ['timeout' => 3])->request('GET', 'http://example.com/file');

    $this->assertSame('SESS=abc', $this->requestHeader(0, 'Cookie'));
    $this->assertNull($this->requestHeader(0, 'X-Token'));
    $this->assertNull($this->requestHeader(0, 'Authorization'));
    $this->assertEqualsWithDelta(3.0, $this->requests[0]['options']['timeout'], 0.001);
  }

  /**
   * Tests that a browser's own options win over the site's.
   *
   * @param string $browser
   *   The browser to build: 'bare' or 'detached'.
   * @param string $url
   *   The URL requested.
   * @param string|null $site_header
   *   The header only the site declares, or NULL when it is expected absent.
   */
  #[DataProvider('dataProviderBrowserOptionsWinOverTheSiteOptions')]
  public function testBrowserOptionsWinOverTheSiteOptions(string $browser, string $url, ?string $site_header): void {
    $transport = HttpClientFactory::createTransport(['timeout' => 30, 'headers' => ['X-Site' => 'yes', 'X-Shared' => 'site']], 'http://example.com', $this->recordingClient());
    $factory = new HttpClientFactory($transport, 'http://example.com');
    $options = ['timeout' => 7, 'headers' => ['X-Shared' => 'browser']];

    $client = $browser === 'bare' ? $factory->createBare($options) : $factory->createDetached(new HttpIdentity(), $options);
    $client->request('GET', $url);

    $this->assertEqualsWithDelta(7.0, $this->requests[0]['options']['timeout'], 0.001);
    $this->assertSame('browser', $this->requestHeader(0, 'X-Shared'));
    $this->assertSame($site_header, $this->requestHeader(0, 'X-Site'));
  }

  public static function dataProviderBrowserOptionsWinOverTheSiteOptions(): \Iterator {
    yield 'a bare browser requesting the site' => ['bare', 'http://example.com/file', 'yes'];
    yield 'a bare browser requesting another host' => ['bare', 'https://cdn.example.org/engine.js', NULL];
    yield 'a detached browser requesting the site' => ['detached', 'http://example.com/file', 'yes'];
    yield 'a detached browser requesting another host' => ['detached', 'https://cdn.example.org/file', NULL];
  }

  public function testWithTransportDecoratesEveryBrowserOfTheCopy(): void {
    $transport = $this->recordingClient();
    $factory = new HttpClientFactory($transport, 'http://example.com');
    $received = NULL;

    $decorated = $factory->withTransport(static function (HttpClientInterface $inner) use (&$received): HttpClientInterface {
      $received = $inner;

      return $inner->withOptions(['headers' => ['X-Decorated' => 'yes']]);
    });

    $decorated->createBare()->request('GET', 'http://example.com/bare');
    $decorated->createDetached(new HttpIdentity())->request('GET', 'http://example.com/detached');
    $factory->createBare()->request('GET', 'http://example.com/original');

    $this->assertSame($transport, $received);
    $this->assertNotSame($factory, $decorated);
    $this->assertSame('yes', $this->requestHeader(0, 'X-Decorated'));
    $this->assertSame('yes', $this->requestHeader(1, 'X-Decorated'));
    $this->assertNull($this->requestHeader(2, 'X-Decorated'));
  }

  /**
   * Returns a client that records each request and answers it.
   */
  protected function recordingClient(): MockHttpClient {
    return new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
      $this->requests[] = ['url' => $url, 'options' => $options];

      return new MockResponse('Response body');
    });
  }

  /**
   * Returns the value of a header a recorded request carried.
   *
   * @param int $index
   *   The position of the request.
   * @param string $name
   *   The header name.
   *
   * @return string|null
   *   The header value, or NULL when the request carried no such header.
   */
  protected function requestHeader(int $index, string $name): ?string {
    $lines = $this->requests[$index]['options']['normalized_headers'][strtolower($name)] ?? [];

    if (!is_array($lines) || !isset($lines[0]) || !is_string($lines[0])) {
      return NULL;
    }

    return explode(': ', $lines[0], 2)[1] ?? NULL;
  }

}
