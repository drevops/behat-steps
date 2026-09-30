<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Http;

use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\BrowserKit\CookieJar;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\ScopingHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Builds bare and detached browsers on 1 shared transport.
 *
 * The transport carries the connection options the 'browserkit_http' session
 * declares, and the Mink session's own browser sends through it as well.
 *
 * @see \DrevOps\BehatSteps\Behat\Mink\ServiceContainer\Driver\BrowserKitFactory
 */
class HttpClientFactory implements HttpClientFactoryInterface {

  /**
   * Constructs an HttpClientFactory object.
   *
   * @param \Symfony\Contracts\HttpClient\HttpClientInterface $transport
   *   The transport every browser sends through.
   * @param string|null $baseUrl
   *   The site's base URL. A detached browser sends its headers and
   *   credentials to this host only.
   */
  public function __construct(
    protected readonly HttpClientInterface $transport,
    protected readonly ?string $baseUrl = NULL,
  ) {
  }

  /**
   * Creates the transport the page, detached and bare browsers share.
   *
   * @param array<array-key, mixed> $options
   *   Symfony HttpClient options, as a 'browserkit_http' session declares
   *   them under 'http_client_parameters'.
   * @param string|null $base_url
   *   The site's base URL. The options apply to requests to its host only,
   *   or to every request when it names no host.
   * @param \Symfony\Contracts\HttpClient\HttpClientInterface|null $client
   *   The client to apply the options to. Defaults to a new client.
   */
  public static function createTransport(array $options, ?string $base_url, ?HttpClientInterface $client = NULL): HttpClientInterface {
    $client ??= HttpClient::create();

    if ($options === []) {
      return $client;
    }

    $pattern = static::sitePattern($base_url);

    if ($pattern === NULL) {
      return $client->withOptions($options);
    }

    return new ScopingHttpClient($client, [$pattern => $options]);
  }

  /**
   * {@inheritdoc}
   */
  public function createBare(array $options = []): AbstractBrowser {
    return new HttpBrowser($this->withOptions($this->transport, $options));
  }

  /**
   * {@inheritdoc}
   */
  public function createDetached(HttpIdentity $identity, array $options = []): AbstractBrowser {
    $transport = $this->withOptions($this->transport, $options);
    $pattern = static::sitePattern($this->baseUrl);
    $site_options = $this->identityOptions($identity);

    if ($pattern !== NULL && $site_options !== []) {
      $transport = new ScopingHttpClient($transport, [$pattern => $site_options]);
    }

    return new HttpBrowser($transport, NULL, $this->cookieJar($identity));
  }

  /**
   * Returns the pattern matching requests to the site's own host.
   *
   * Symfony matches the pattern against a normalized URL, which keeps any
   * credentials, lowercases the host and drops a default port.
   *
   * @param string|null $base_url
   *   The site's base URL.
   *
   * @return string|null
   *   The pattern, or NULL when the base URL names no host.
   */
  protected static function sitePattern(?string $base_url): ?string {
    $parts = parse_url((string) $base_url);

    if (!is_array($parts) || !isset($parts['host']) || $parts['host'] === '') {
      return NULL;
    }

    $scheme = strtolower($parts['scheme'] ?? 'http');
    $default_port = $scheme === 'https' ? 443 : 80;
    $port = isset($parts['port']) && $parts['port'] !== $default_port ? ':' . $parts['port'] : '';

    return preg_quote($scheme . '://', '{') . '(?:[^/?#@]*@)?' . preg_quote(strtolower($parts['host']) . $port, '{') . '(?=[/?#]|$)';
  }

  /**
   * Returns a client that merges the given options over the transport's.
   *
   * @param \Symfony\Contracts\HttpClient\HttpClientInterface $client
   *   The client to extend.
   * @param array<string, mixed> $options
   *   Symfony HttpClient options.
   */
  protected function withOptions(HttpClientInterface $client, array $options): HttpClientInterface {
    return $options === [] ? $client : $client->withOptions($options);
  }

  /**
   * Returns the options that send an identity's headers and credentials.
   *
   * @param \DrevOps\BehatSteps\Behat\Http\HttpIdentity $identity
   *   The identity to send.
   *
   * @return array<string, mixed>
   *   Symfony HttpClient options, empty when the identity carries neither.
   */
  protected function identityOptions(HttpIdentity $identity): array {
    $options = [];

    if ($identity->headers !== []) {
      $options['headers'] = $identity->headers;
    }

    // An 'Authorization' header among the headers takes precedence, because
    // Symfony applies 'auth_basic' only to a request without one.
    if ($identity->credentials !== NULL) {
      $options['auth_basic'] = [$identity->credentials['username'], $identity->credentials['password']];
    }

    return $options;
  }

  /**
   * Returns a cookie jar holding an identity's cookies for their own host.
   *
   * @param \DrevOps\BehatSteps\Behat\Http\HttpIdentity $identity
   *   The identity whose cookies to hold.
   */
  protected function cookieJar(HttpIdentity $identity): CookieJar {
    $jar = new CookieJar();
    $host = parse_url($identity->cookieUrl, PHP_URL_HOST);

    // Without a host the cookies would match every request, so none are held.
    if (!is_string($host) || $host === '') {
      return $jar;
    }

    foreach ($identity->cookies as $name => $value) {
      $jar->set(new Cookie($name, $value, NULL, '/', $host, FALSE, TRUE, TRUE));
    }

    return $jar;
  }

}
