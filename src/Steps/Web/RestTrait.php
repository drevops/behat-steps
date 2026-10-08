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
use DrevOps\BehatSteps\Behat\Config\Option;
use DrevOps\BehatSteps\Helper\Web\RequestHeadersTrait;
use DrevOps\BehatSteps\Helper\Web\StringTrait;

/**
 * Test REST APIs with lightweight steps and no Drupal dependencies.
 *
 * - Set HTTP headers for subsequent requests.
 * - Send requests with any HTTP method (GET, POST, PUT, PATCH, DELETE).
 * - Assert response status codes and body content.
 *
 * Skip processing with tag: `@behat-steps-skip:RestTrait`.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
 */
trait RestTrait {

  use RequestHeadersTrait;
  use StringTrait;

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
   */
  #[Given('the REST header :name has the value :value')]
  public function restSetHeader(string $name, string $value): void {
    $this->requestHeadersSet($name, $value);
  }

  /**
   * Send a REST request to a URL.
   *
   * @code
   * When I send a REST "GET" request to the URL "/api/resource"
   * When I send a REST "DELETE" request to the URL "/api/resource/1"
   * @endcode
   */
  #[When('I send a REST :method request to the URL :url')]
  public function restSendRequest(string $method, string $url): void {
    $client = $this->restGetClient();
    $client->request(strtoupper($method), $this->restResolveUrl($url), [], [], $this->restCreateServerArray());
  }

  /**
   * Send a REST request to a URL with a body.
   *
   * @code
   * When I send a REST "POST" request to the URL "/api/resource" with the body:
   *   """
   *   {"name": "example"}
   *   """
   * @endcode
   */
  #[When('I send a REST :method request to the URL :url with the body:')]
  public function restSendRequestWithBody(string $method, string $url, PyStringNode $body): void {
    $client = $this->restGetClient();
    $client->request(strtoupper($method), $this->restResolveUrl($url), [], [], $this->restCreateServerArray(), $body->getRaw());
  }

  /**
   * Assert the REST response status code.
   *
   * @code
   * Then the REST response status code should be 200
   * Then the REST response status code should be 404
   * @endcode
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
   */
  #[Then('the REST response should contain :text')]
  public function restAssertResponseContains(string $text): void {
    $content = $this->getSession()->getPage()->getContent();

    if (!str_contains((string) $content, $text)) {
      throw new ExpectationException(sprintf('The REST response does not contain "%s".', $text), $this->getSession()->getDriver());
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
   * @return array<int, \DrevOps\BehatSteps\Behat\Config\Option>
   *   The options this trait declares.
   */
  protected function restConfigSchema(): array {
    return [
      new Option('enabled', default: TRUE, description: 'Reset the request state this trait accumulates between scenarios.'),
    ];
  }

}
