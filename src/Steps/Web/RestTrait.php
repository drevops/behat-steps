<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Web;

use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Hook\BeforeScenario;
use Behat\Mink\Exception\ExpectationException;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use DrevOps\BehatSteps\Extension\Config\Option;
use DrevOps\BehatSteps\Steps\Helper\Web\RequestHeadersTrait;
use DrevOps\BehatSteps\Steps\Helper\Web\StringTrait;
use Symfony\Component\BrowserKit\AbstractBrowser;

/**
 * Test REST APIs with lightweight steps.
 *
 * - Set HTTP headers for subsequent requests.
 * - Send requests with any HTTP method (GET, POST, PUT, PATCH, DELETE).
 * - Send requests without the browser session's cookies.
 * - Assert response status codes and body content.
 *
 * Send requests without cookies with tag: `@rest-no-cookies`.
 *
 * Skip processing with tag: `@behat-steps-skip:RestTrait`.
 *
 * @title REST APIs
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\WebRawContext
 */
trait RestTrait {

  use RequestHeadersTrait;
  use StringTrait;

  /**
   * Tag that sends the scenario's REST requests without cookies.
   */
  protected const string REST_NO_COOKIES_TAG = 'rest-no-cookies';

  /**
   * Reset REST headers before each scenario.
   */
  #[BeforeScenario]
  public function restBeforeScenario(BeforeScenarioScope $scope): void {
    if ($this->skipTag(__TRAIT__, $scope)) {
      return;
    }

    $this->requestHeadersReset();
  }

  /**
   * Set a REST header for subsequent requests.
   *
   * @code
   * Given the REST header "Accept" has the value "application/json"
   * Given the REST header "Authorization" has the value "Bearer abc123"
   * @endcode
   *
   * @actor:system
   */
  #[Given('the REST header :name has the value :value')]
  public function restSetHeader(string $name, string $value): void {
    $this->requestHeadersSet($name, $value);
  }

  /**
   * Send a REST request to a URL.
   *
   * @code
   * When I send a REST "GET" request to "/api/resource"
   * When I send a REST "DELETE" request to "/api/resource/1"
   * @endcode
   *
   * @actor:person
   */
  #[When('I send a REST :method request to :url')]
  public function restSendRequest(string $method, string $url): void {
    $this->restSend($method, $url);
  }

  /**
   * Send a REST request to a URL with a body.
   *
   * @code
   * When I send a REST "POST" request to "/api/resource" with the body:
   *   """
   *   {"name": "example"}
   *   """
   * @endcode
   *
   * @actor:person
   */
  #[When('I send a REST :method request to :url with the body:')]
  public function restSendRequestWithBody(string $method, string $url, PyStringNode $body): void {
    $this->restSend($method, $url, $body->getRaw());
  }

  /**
   * Print the last REST response.
   *
   * @code
   * When I print the last REST response
   * @endcode
   *
   * @actor:person
   */
  #[When('I print the last REST response')]
  public function restPrintLastResponse(): void {
    print $this->getSession()->getPage()->getContent();
  }

  /**
   * Assert the REST response status code.
   *
   * @code
   * Then the REST response status code should be 200
   * Then the REST response status code should be 404
   * @endcode
   *
   * @actor:system
   */
  #[Then('the REST response status code should be :code')]
  public function restAssertResponseStatusCodeEquals(string $code): void {
    $code = $this->stringParseInteger($code, 'status code');

    $actual = $this->getSession()->getStatusCode();

    if ($actual !== $code) {
      throw new ExpectationException(sprintf('Expected the REST response status code to be %d, but got %d.', $code, $actual), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert the REST response contains text.
   *
   * @code
   * Then the REST response should contain "success"
   * @endcode
   *
   * @actor:system
   */
  #[Then('the REST response should contain :value')]
  public function restAssertResponseContains(string $value): void {
    $content = $this->getSession()->getPage()->getContent();

    if (!str_contains((string) $content, $value)) {
      throw new ExpectationException(sprintf('The REST response does not contain "%s".', $value), $this->getSession()->getDriver());
    }
  }

  /**
   * Resolve a relative URL against the Mink base URL.
   *
   * @param string $url
   *   The URL to resolve.
   *
   * @return string
   *   The resolved absolute URL.
   */
  public function restResolveUrl(string $url): string {
    if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
      return $url;
    }

    return rtrim($this->getMinkParameter('base_url'), '/') . '/' . ltrim($url, '/');
  }

  /**
   * Get the page client REST requests are sent through.
   *
   * A REST response becomes the page, so the REST assertions read it from
   * the session like any other page.
   *
   * @return mixed
   *   The BrowserKit browser the Mink session drives.
   */
  public function restGetClient(): mixed {
    return $this->httpPageClient();
  }

  /**
   * Whether REST requests carry the browser session's cookies.
   *
   * @return bool
   *   TRUE to send the session's cookies with each REST request.
   */
  public function restGetSendCookies(): bool {
    return $this->getOptionBool('rest', 'send_cookies');
  }

  /**
   * Send a REST request with the stored headers.
   *
   * The response becomes the page. When 'restGetSendCookies()' returns FALSE,
   * the request carries no cookies and the session's cookie jar is left as it
   * was before the request, so the browser session stays logged in.
   *
   * @param string $method
   *   The HTTP method, in any case.
   * @param string $url
   *   The URL, relative to the Mink base URL or absolute.
   * @param string|null $body
   *   The request body, or NULL to send none.
   *
   * @throws \RuntimeException
   *   When the request goes without cookies and 'restGetClient()' returns
   *   something other than a BrowserKit browser.
   */
  public function restSend(string $method, string $url, ?string $body = NULL): void {
    $client = $this->restGetClient();
    $method = strtoupper($method);
    $url = $this->restResolveUrl($url);
    $server = $this->restCreateServerArray();

    if ($this->restGetSendCookies()) {
      $client->request($method, $url, [], [], $server, $body);

      return;
    }

    if (!$client instanceof AbstractBrowser) {
      throw new \RuntimeException(sprintf('Sending a REST request without cookies requires "restGetClient()" to return a "%s", but it returned a "%s".', AbstractBrowser::class, get_debug_type($client)));
    }

    $this->restSendWithoutCookies($client, $method, $url, $server, $body);
  }

  /**
   * Send a request with an empty cookie jar, then put the jar's cookies back.
   *
   * Cookies the response sets are discarded with the emptied jar.
   *
   * @param \Symfony\Component\BrowserKit\AbstractBrowser<covariant object, covariant object> $client
   *   The browser to send the request through.
   * @param string $method
   *   The HTTP method.
   * @param string $url
   *   The absolute URL.
   * @param array<string, string> $server
   *   The server array carrying the request headers.
   * @param string|null $body
   *   The request body, or NULL to send none.
   */
  protected function restSendWithoutCookies(AbstractBrowser $client, string $method, string $url, array $server, ?string $body): void {
    $jar = $client->getCookieJar();
    $cookies = $jar->all();
    $jar->clear();

    try {
      $client->request($method, $url, [], [], $server, $body);
    }
    finally {
      $jar->clear();

      foreach ($cookies as $cookie) {
        $jar->set($cookie);
      }
    }
  }

  /**
   * Convert stored headers to the server array format for BrowserKit.
   *
   * @return array<string, string>
   *   The server array.
   */
  protected function restCreateServerArray(): array {
    $server = [];

    foreach ($this->requestHeadersAll() as $name => $value) {
      $key = strtoupper(str_replace('-', '_', $name));

      if ($key !== 'CONTENT_TYPE' && $key !== 'CONTENT_LENGTH') {
        $key = 'HTTP_' . $key;
      }

      $server[$key] = $value;
    }

    return $server;
  }

  /**
   * Declares the options this trait reads.
   *
   * @return array<int, \DrevOps\BehatSteps\Extension\Config\Option>
   *   The options this trait declares.
   */
  protected function restConfigSchema(): array {
    return [
      new Option('enabled', default: TRUE, description: "If `TRUE`, clears the context's request headers before each scenario. If `FALSE`, they aren't cleared, which changes nothing, because Behat builds a new context for each scenario and its headers start empty."),
      new Option('send_cookies', default: TRUE, description: "If `TRUE`, each REST request carries the browser session's cookies and keeps the cookies its response sets, so a logged-in session authenticates it. If `FALSE`, each REST request carries no cookies and its response's cookies are discarded, while the browser session keeps its own.", tags: [self::REST_NO_COOKIES_TAG => FALSE]),
    ];
  }

}
