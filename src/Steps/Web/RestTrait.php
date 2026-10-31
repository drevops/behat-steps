<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Web;

use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Hook\BeforeScenario;
use Behat\Mink\Driver\BrowserKitDriver;
use Behat\Mink\Exception\ExpectationException;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use DrevOps\BehatSteps\Extension\Config\Option;
use DrevOps\BehatSteps\Extension\ServiceContainer\BehatStepsExtension;
use DrevOps\BehatSteps\Steps\Helper\Web\FixtureDirectoryTrait;
use DrevOps\BehatSteps\Steps\Helper\Web\JsonPathTrait;
use DrevOps\BehatSteps\Steps\Helper\Web\RequestHeadersTrait;
use DrevOps\BehatSteps\Steps\Helper\Web\RestResponseTrait;
use DrevOps\BehatSteps\Steps\Helper\Web\StringTrait;
use Symfony\Component\BrowserKit\AbstractBrowser;

/**
 * Test REST APIs with lightweight steps.
 *
 * - Set HTTP headers for subsequent requests, literally or from a JSON path
 *   in the last response.
 * - Send requests with any HTTP method (GET, POST, PUT, PATCH, DELETE), with
 *   a body written inline or read from a fixture file.
 * - Assert response status codes, headers and body content.
 *
 * Requests go through the `rest` Mink session, apart from the browser's: they
 * carry no browser cookies, and the browser keeps its page. The JSON and XML
 * steps read the same session.
 *
 * The JSON path header steps require the optional `softcreatr/jsonpath`
 * package: `composer require --dev softcreatr/jsonpath`.
 *
 * Skip processing with tag: `@behat-steps-skip:RestTrait`.
 *
 * @title REST APIs
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\WebRawContext
 */
trait RestTrait {

  use FixtureDirectoryTrait;
  use JsonPathTrait;
  use RequestHeadersTrait;
  use RestResponseTrait;
  use StringTrait;

  /**
   * The headers the REST steps set, sent with REST requests only.
   *
   * @var array<string, string>
   */
  protected array $restHeaders = [];

  /**
   * Reset REST headers before each scenario.
   */
  #[BeforeScenario]
  public function restBeforeScenario(BeforeScenarioScope $scope): void {
    if ($this->skipTag(__TRAIT__, $scope)) {
      return;
    }

    $this->restHeaders = [];
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
    $this->restHeaders[$name] = $value;
  }

  /**
   * Set a REST header to the value at a JSON path in the last REST response.
   *
   * @code
   * Given the REST header "X-Session-Id" has the value from the JSON path "$.session_id"
   * @endcode
   *
   * @actor:system
   */
  #[Given('the REST header :name has the value from the JSON path :path')]
  public function restSetHeaderFromJsonPath(string $name, string $path): void {
    $this->restHeaders[$name] = $this->restResolveHeaderValue($path);
  }

  /**
   * Set a REST header to a prefix and the value at a JSON path.
   *
   * The prefix is prepended as it is given, so a prefix separated from the
   * value by a space ends with that space.
   *
   * @code
   * Given the REST header "Authorization" has the value from the JSON path "$.access_token" with the prefix "Bearer "
   * @endcode
   *
   * @actor:system
   */
  #[Given('the REST header :name has the value from the JSON path :path with the prefix :prefix')]
  public function restSetHeaderFromJsonPathWithPrefix(string $name, string $path, string $prefix): void {
    $this->restHeaders[$name] = $prefix . $this->restResolveHeaderValue($path);
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
   * Send a REST request to a URL with a body read from a fixture file.
   *
   * The file path is relative to the Mink `files_path` parameter.
   *
   * @code
   * When I send a REST "POST" request to "/api/import" with the body from the file "items.xml"
   * @endcode
   *
   * @actor:person
   */
  #[When('I send a REST :method request to :url with the body from the file :filename')]
  public function restSendRequestWithBodyFromFile(string $method, string $url, string $filename): void {
    $this->restSend($method, $url, $this->fixtureDirectoryReadFile($filename));
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
    print $this->restResponseGetContent();
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

    $actual = $this->restResponseGetStatusCode();

    if ($actual !== $code) {
      throw new ExpectationException(sprintf('Expected the REST response status code to be %d, but got %d.', $code, $actual), $this->httpRestSession()->getDriver());
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
    $content = $this->restResponseGetContent();

    if (!str_contains($content, $value)) {
      throw new ExpectationException(sprintf('The REST response does not contain "%s".', $value), $this->httpRestSession()->getDriver());
    }
  }

  /**
   * Assert the REST response contains a header.
   *
   * @code
   * Then the REST response should contain the header "Content-Type"
   * @endcode
   *
   * @actor:system
   */
  #[Then('the REST response should contain the header :name')]
  public function restAssertResponseHeaderExists(string $name): void {
    if ($this->restResponseFindHeader($name) === NULL) {
      throw new ExpectationException(sprintf('The REST response does not contain the header "%s".', $name), $this->httpRestSession()->getDriver());
    }
  }

  /**
   * Assert the REST response does not contain a header.
   *
   * @code
   * Then the REST response should not contain the header "Set-Cookie"
   * @endcode
   *
   * @actor:system
   */
  #[Then('the REST response should not contain the header :name')]
  public function restAssertResponseHeaderNotExists(string $name): void {
    if ($this->restResponseFindHeader($name) !== NULL) {
      throw new ExpectationException(sprintf('The REST response contains the header "%s", but it should not.', $name), $this->httpRestSession()->getDriver());
    }
  }

  /**
   * Assert a header of the REST response contains a value.
   *
   * The comparison ignores case.
   *
   * @code
   * Then the REST response header "Content-Type" should contain "application/json"
   * @endcode
   *
   * @actor:system
   */
  #[Then('the REST response header :name should contain :value')]
  public function restAssertResponseHeaderContains(string $name, string $value): void {
    $actual = $this->restResponseFindHeader($name);

    if ($actual === NULL) {
      throw new ExpectationException(sprintf('The REST response does not contain the header "%s".', $name), $this->httpRestSession()->getDriver());
    }

    if (!str_contains(strtolower($actual), strtolower($value))) {
      throw new ExpectationException(sprintf('The REST response header "%s" is "%s" and does not contain "%s".', $name, $actual, $value), $this->httpRestSession()->getDriver());
    }
  }

  /**
   * Assert a header of the REST response does not contain a value.
   *
   * The comparison ignores case.
   *
   * @code
   * Then the REST response header "Cache-Control" should not contain "public"
   * @endcode
   *
   * @actor:system
   */
  #[Then('the REST response header :name should not contain :value')]
  public function restAssertResponseHeaderNotContains(string $name, string $value): void {
    $actual = $this->restResponseFindHeader($name);

    if ($actual === NULL) {
      throw new ExpectationException(sprintf('The REST response does not contain the header "%s".', $name), $this->httpRestSession()->getDriver());
    }

    if (str_contains(strtolower($actual), strtolower($value))) {
      throw new ExpectationException(sprintf('The REST response header "%s" is "%s" and contains "%s", but it should not.', $name, $actual, $value), $this->httpRestSession()->getDriver());
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
   * Send a REST request through the REST session.
   *
   * The request carries the scenario's request headers, such as the one the
   * basic authentication step sets, and the REST headers over them.
   *
   * @param string $method
   *   The HTTP method, in any case.
   * @param string $url
   *   The URL, relative to the Mink base URL or absolute.
   * @param string|null $body
   *   The request body, or NULL to send none.
   */
  public function restSend(string $method, string $url, ?string $body = NULL): void {
    $this->restGetBrowser()->request(strtoupper($method), $this->restResolveUrl($url), [], [], $this->restCreateServerArray(), $body);
  }

  /**
   * Get the scalar at a JSON path in the last REST response.
   *
   * @param string $path
   *   The JSONPath expression, which must match exactly 1 scalar.
   *
   * @return mixed
   *   The matched scalar, or NULL for a JSON `null`.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   When the expression matches no value, more than 1, or an array or object.
   * @throws \RuntimeException
   *   When no REST request has been sent, or the response is not JSON.
   */
  public function restGetResponseJsonValue(string $path): mixed {
    return $this->jsonPathGetScalar($this->jsonPathDecode($this->restResponseGetContent()), $path);
  }

  /**
   * Resolves a JSON path in the last REST response to a header value.
   *
   * A JSON boolean becomes "true" or "false", and a number its string form.
   *
   * @param string $path
   *   The JSONPath expression.
   *
   * @return string
   *   The header value.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   When the expression matches a JSON `null`, which a header can't carry.
   */
  protected function restResolveHeaderValue(string $path): string {
    $value = $this->restGetResponseJsonValue($path);

    if ($value === NULL) {
      throw new ExpectationException(sprintf('The JSON path "%s" is null in the REST response, but a value is required.', $path), $this->httpRestSession()->getDriver());
    }

    return $this->jsonPathFormatScalar($value);
  }

  /**
   * Get the BrowserKit browser the REST session drives.
   *
   * @return \Symfony\Component\BrowserKit\AbstractBrowser<covariant object, covariant object>
   *   The browser.
   *
   * @throws \RuntimeException
   *   When the REST session runs a browser driver other than BrowserKit.
   */
  public function restGetBrowser(): AbstractBrowser {
    $driver = $this->httpRestSession()->getDriver();

    if (!$driver instanceof BrowserKitDriver) {
      throw new \RuntimeException(sprintf('The "%s" Mink session must run the BrowserKit browser driver, but it runs "%s".', BehatStepsExtension::REST_SESSION, $driver::class));
    }

    return $driver->getClient();
  }

  /**
   * Convert the stored headers to the server array format for BrowserKit.
   *
   * A REST header replaces a scenario request header of the same name.
   *
   * @return array<string, string>
   *   The server array.
   */
  protected function restCreateServerArray(): array {
    $server = [];

    foreach ([$this->requestHeadersAll(), $this->restHeaders] as $headers) {
      foreach ($headers as $name => $value) {
        $key = strtoupper(str_replace('-', '_', $name));
        $prefix = in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], TRUE) ? '' : 'HTTP_';
        $server[$prefix . $key] = $value;
      }
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
      new Option('enabled', default: TRUE, description: "If `TRUE`, clears the REST headers before each scenario. If `FALSE`, they aren't cleared, which changes nothing, because Behat builds a new context for each scenario and its headers start empty."),
    ];
  }

}
