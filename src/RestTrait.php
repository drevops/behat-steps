<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Hook\BeforeScenario;
use Behat\Mink\Exception\ExpectationException;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;

/**
 * Lightweight REST API testing with no Drupal dependencies.
 *
 * - Set HTTP headers for subsequent requests.
 * - Send requests with any HTTP method (GET, POST, PUT, PATCH, DELETE).
 * - Assert response status codes and body content.
 *
 * Skip processing with tags: `@behat-steps-skip:restBeforeScenario`
 */
trait RestTrait {

  /**
   * Accumulated REST headers for the current scenario.
   *
   * @var array<string, string>
   */
  protected array $restHeaders = [];

  /**
   * Reset REST headers before each scenario.
   */
  #[BeforeScenario]
  public function restBeforeScenario(BeforeScenarioScope $scope): void {
    if ($scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__)) {
      return;
    }

    $this->restHeaders = [];
  }

  /**
   * Set a REST header for subsequent requests.
   *
   * @code
   * Given a REST header "Accept" with value "application/json"
   * Given a REST header "Authorization" with value "Bearer abc123"
   * @endcode
   */
  #[Given('a REST header :name with value :value')]
  public function restSetHeader(string $name, string $value): void {
    $this->restHeaders[$name] = $value;
  }

  /**
   * Send a REST request to a URL.
   *
   * @code
   * When I send a REST "GET" request to "/api/resource"
   * When I send a REST "DELETE" request to "/api/resource/1"
   * @endcode
   */
  #[When('I send a REST :method request to :url')]
  public function restSendRequest(string $method, string $url): void {
    $client = $this->restGetClient();
    $client->request(strtoupper($method), $this->restResolveUrl($url), [], [], $this->restCreateServerArray());
  }

  /**
   * Send a REST request to a URL with a body.
   *
   * @code
   * When I send a REST "POST" request to "/api/resource" with body:
   *   """
   *   {"name": "example"}
   *   """
   * @endcode
   */
  #[When('I send a REST :method request to :url with body:')]
  public function restSendRequestWithBody(string $method, string $url, PyStringNode $body): void {
    $client = $this->restGetClient();
    $client->request(strtoupper($method), $this->restResolveUrl($url), [], [], $this->restCreateServerArray(), (string) $body);
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
  public function restAssertResponseStatusCode(int $code): void {
    $actual = $this->getSession()->getStatusCode();

    if ($actual !== $code) {
      throw new ExpectationException(sprintf('Expected response status code %d, but got %d.', $code, $actual), $this->getSession()->getDriver());
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
  protected function restResolveUrl(string $url): string {
    if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
      return $url;
    }

    return rtrim($this->getMinkParameter('base_url'), '/') . '/' . ltrim($url, '/');
  }

  /**
   * Get the BrowserKit client from the current Mink driver.
   *
   * @return mixed
   *   The BrowserKit client.
   */
  protected function restGetClient(): mixed {
    $driver = $this->getSession()->getDriver();

    if (!method_exists($driver, 'getClient')) {
      // @codeCoverageIgnoreStart
      throw new UnsupportedDriverActionException('REST requests require a BrowserKit-based driver (e.g. Goutte, BrowserKit). The current driver "%s" does not support this.', $driver);
      // @codeCoverageIgnoreEnd
    }

    return $driver->getClient();
  }

  /**
   * Convert stored headers to the server array format for BrowserKit.
   *
   * Header names are uppercased, hyphens replaced with underscores, and
   * prefixed with 'HTTP_' (except 'Content-Type' and 'Content-Length').
   *
   * @return array<string, string>
   *   The server array.
   */
  protected function restCreateServerArray(): array {
    $server = [];

    foreach ($this->restHeaders as $name => $value) {
      $key = strtoupper(str_replace('-', '_', $name));

      if ($key !== 'CONTENT_TYPE' && $key !== 'CONTENT_LENGTH') {
        $key = 'HTTP_' . $key;
      }

      $server[$key] = $value;
    }

    return $server;
  }

}
