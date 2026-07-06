<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Gherkin\Node\PyStringNode;
use Behat\Hook\AfterScenario;
use Behat\Hook\BeforeScenario;
use Behat\Mink\Exception\ExpectationException;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Flow\JSONPath\JSONPath;
use JsonSchema\Validator;

/**
 * Assert JSON responses with path and schema checks.
 *
 * - Assert response is valid JSON format.
 * - Assert values at JSONPath expressions.
 * - Assert JSONPath existence, type, and element counts.
 * - Validate the response against a JSON Schema.
 *
 * JSONPath expressions use the standard `$.path.to[0].value` syntax.
 *
 * The JSON Schema steps require the optional `justinrainbow/json-schema`
 * package: `composer require --dev justinrainbow/json-schema`.
 */
trait JsonTrait {

  /**
   * Decoded JSON data from the current response.
   *
   * @var array<int|string, mixed>|null
   */
  protected ?array $jsonData = NULL;

  /**
   * Hash of the currently decoded JSON content.
   *
   * Used to detect when page content changes and data needs re-decoding.
   */
  protected ?string $jsonContentHash = NULL;

  /**
   * JSON content set directly for testing without an HTTP request.
   */
  protected ?string $jsonTestContent = NULL;

  /**
   * Clear cached JSON state before each scenario.
   */
  #[BeforeScenario]
  public function jsonBeforeScenario(): void {
    $this->jsonResetState();
  }

  /**
   * Clear cached JSON state after each scenario.
   */
  #[AfterScenario]
  public function jsonAfterScenario(): void {
    $this->jsonResetState();
  }

  /**
   * Set the response JSON content from a fixture file.
   *
   * @code
   * Given the response JSON from the file "json_valid.json"
   * @endcode
   */
  #[Given('the response JSON from the file :filename')]
  public function jsonSetContentFromFile(string $filename): void {
    $this->jsonTestContent = $this->jsonReadFile($filename);
    $this->jsonData = NULL;
    $this->jsonContentHash = NULL;
  }

  /**
   * Set the response JSON content directly from a PyString.
   *
   * @code
   * Given the response JSON content is the following:
   *   """
   *   {"name": "John Doe", "roles": ["admin", "editor"]}
   *   """
   * @endcode
   */
  #[Given('the response JSON content is the following:')]
  public function jsonSetContent(PyStringNode $content): void {
    $this->jsonTestContent = $content->getRaw();
    $this->jsonData = NULL;
    $this->jsonContentHash = NULL;
  }

  /**
   * Assert that a response is valid JSON.
   *
   * @code
   * Then the response should be in JSON format
   *
   * # Content set by a fixture step is validated instead of the page content.
   * Given the response JSON from the file "json_valid.json"
   * Then the response should be in JSON format
   * @endcode
   */
  #[Then('the response should be in JSON format')]
  public function jsonAssertResponseIsJson(): void {
    $content = $this->jsonResolveContent();

    json_decode((string) $content);

    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new ExpectationException(sprintf('The response is not valid JSON: %s', json_last_error_msg()), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that a response is not valid JSON.
   *
   * @code
   * Then the response should not be in JSON format
   * @endcode
   */
  #[Then('the response should not be in JSON format')]
  public function jsonAssertResponseIsNotJson(): void {
    $content = $this->jsonResolveContent();

    json_decode((string) $content);

    if (json_last_error() === JSON_ERROR_NONE) {
      throw new ExpectationException('The response is valid JSON, but it should not be.', $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that a JSONPath expression matches at least one value.
   *
   * @code
   * Then the JSON path "$.name" should exist
   * Then the JSON path "$.user.roles[0]" should exist
   * @endcode
   */
  #[Then('the JSON path :path should exist')]
  public function jsonAssertPathExists(string $path): void {
    $matches = $this->jsonQuery($path);

    if (count($matches) === 0) {
      throw new ExpectationException(sprintf('The JSON path "%s" was not found.', $path), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that a JSONPath expression matches no values.
   *
   * @code
   * Then the JSON path "$.nonexistent" should not exist
   * @endcode
   */
  #[Then('the JSON path :path should not exist')]
  public function jsonAssertPathNotExists(string $path): void {
    $matches = $this->jsonQuery($path);

    if (count($matches) > 0) {
      throw new ExpectationException(sprintf('The JSON path "%s" was found, but it should not exist.', $path), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that the value at a JSONPath equals the expected value.
   *
   * @code
   * Then the JSON path "$.name" should be equal to "John Doe"
   * Then the JSON path "$.age" should be equal to "42"
   * @endcode
   */
  #[Then('the JSON path :path should be equal to :value')]
  public function jsonAssertPathEquals(string $path, string $value): void {
    $actual = $this->jsonScalarToString($this->jsonResolveScalar($path));

    if ($actual !== $value) {
      throw new ExpectationException(sprintf('The JSON path "%s" is "%s", but expected "%s".', $path, $actual, $value), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that the value at a JSONPath does not equal the expected value.
   *
   * @code
   * Then the JSON path "$.name" should not be equal to "Jane Doe"
   * @endcode
   */
  #[Then('the JSON path :path should not be equal to :value')]
  public function jsonAssertPathNotEquals(string $path, string $value): void {
    $actual = $this->jsonScalarToString($this->jsonResolveScalar($path));

    if ($actual === $value) {
      throw new ExpectationException(sprintf('The JSON path "%s" is "%s", but it should not be.', $path, $actual), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that the value at a JSONPath contains the expected text.
   *
   * @code
   * Then the JSON path "$.name" should contain "John"
   * @endcode
   */
  #[Then('the JSON path :path should contain :value')]
  public function jsonAssertPathContains(string $path, string $value): void {
    $actual = $this->jsonScalarToString($this->jsonResolveScalar($path));

    if (!str_contains((string) $actual, $value)) {
      throw new ExpectationException(sprintf('The JSON path "%s" is "%s" and does not contain "%s".', $path, $actual, $value), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that the value at a JSONPath does not contain the expected text.
   *
   * @code
   * Then the JSON path "$.name" should not contain "Jane"
   * @endcode
   */
  #[Then('the JSON path :path should not contain :value')]
  public function jsonAssertPathNotContains(string $path, string $value): void {
    $actual = $this->jsonScalarToString($this->jsonResolveScalar($path));

    if (str_contains((string) $actual, $value)) {
      throw new ExpectationException(sprintf('The JSON path "%s" is "%s" and contains "%s", but it should not.', $path, $actual, $value), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that the value at a JSONPath matches a regular expression.
   *
   * @code
   * Then the JSON path "$.email" should match "/^[^@]+@example\.com$/"
   * @endcode
   */
  #[Then('the JSON path :path should match :pattern')]
  public function jsonAssertPathMatches(string $path, string $pattern): void {
    $actual = $this->jsonScalarToString($this->jsonResolveScalar($path));

    $result = @preg_match($pattern, (string) $actual);
    if ($result === FALSE) {
      throw new ExpectationException(sprintf('The regular expression "%s" is invalid.', $pattern), $this->getSession()->getDriver());
    }

    if ($result === 0) {
      throw new ExpectationException(sprintf('The JSON path "%s" is "%s" and does not match the pattern "%s".', $path, $actual, $pattern), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that the value at a JSONPath does not match a regular expression.
   *
   * @code
   * Then the JSON path "$.email" should not match "/^admin@/"
   * @endcode
   */
  #[Then('the JSON path :path should not match :pattern')]
  public function jsonAssertPathNotMatches(string $path, string $pattern): void {
    $actual = $this->jsonScalarToString($this->jsonResolveScalar($path));

    $result = @preg_match($pattern, (string) $actual);
    if ($result === FALSE) {
      throw new ExpectationException(sprintf('The regular expression "%s" is invalid.', $pattern), $this->getSession()->getDriver());
    }

    if ($result === 1) {
      throw new ExpectationException(sprintf('The JSON path "%s" is "%s" and matches the pattern "%s", but it should not.', $path, $actual, $pattern), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that the value at a JSONPath is null.
   *
   * @code
   * Then the JSON path "$.deleted_at" should be null
   * @endcode
   */
  #[Then('the JSON path :path should be null')]
  public function jsonAssertPathNull(string $path): void {
    $value = $this->jsonResolveSingle($path);

    if ($value !== NULL) {
      throw new ExpectationException(sprintf('The JSON path "%s" is "%s", but expected null.', $path, $this->jsonScalarToString($value)), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that the value at a JSONPath is boolean true.
   *
   * @code
   * Then the JSON path "$.active" should be true
   * @endcode
   */
  #[Then('the JSON path :path should be true')]
  public function jsonAssertPathTrue(string $path): void {
    $value = $this->jsonResolveSingle($path);

    if ($value !== TRUE) {
      throw new ExpectationException(sprintf('The JSON path "%s" is not true.', $path), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that the value at a JSONPath is boolean false.
   *
   * @code
   * Then the JSON path "$.disabled" should be false
   * @endcode
   */
  #[Then('the JSON path :path should be false')]
  public function jsonAssertPathFalse(string $path): void {
    $value = $this->jsonResolveSingle($path);

    if ($value !== FALSE) {
      throw new ExpectationException(sprintf('The JSON path "%s" is not false.', $path), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that the array or object at a JSONPath has a number of elements.
   *
   * The path must resolve to a single array or object; its elements are then
   * counted. Use a container path such as `$.items` rather than `$.items[*]`.
   *
   * @code
   * Then the JSON path "$.items" should have "3" elements
   * Then the JSON path "$.user" should have "2" elements
   * @endcode
   */
  #[Then('the JSON path :path should have :count element(s)')]
  public function jsonAssertPathCount(string $path, string $count): void {
    $value = $this->jsonResolveSingle($path);

    if (!is_array($value)) {
      throw new ExpectationException(sprintf('The JSON path "%s" is not an array or object.', $path), $this->getSession()->getDriver());
    }

    $actual = count($value);
    $expected = (int) $count;

    if ($actual !== $expected) {
      throw new ExpectationException(sprintf('The JSON path "%s" has %d element(s), but expected %d.', $path, $actual, $expected), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that the response validates against an inline JSON Schema.
   *
   * @code
   * Then the response should match the following JSON schema:
   *   """
   *   {"type": "object", "required": ["name"], "properties": {"name": {"type": "string"}}}
   *   """
   * @endcode
   */
  #[Then('the response should match the following JSON schema:')]
  public function jsonAssertMatchesSchema(PyStringNode $schema): void {
    $this->jsonValidateSchema($schema->getRaw());
  }

  /**
   * Assert that the response validates against a JSON Schema from a file.
   *
   * @code
   * Then the response should match the JSON schema in the file "json_schema.json"
   * @endcode
   */
  #[Then('the response should match the JSON schema in the file :filename')]
  public function jsonAssertMatchesSchemaFromFile(string $filename): void {
    $this->jsonValidateSchema($this->jsonReadFile($filename));
  }

  /**
   * Print the last JSON response.
   *
   * @code
   * When I print last JSON response
   * @endcode
   */
  #[When('I print last JSON response')]
  public function jsonPrintLastResponse(): void {
    $content = $this->jsonResolveContent();

    $data = json_decode((string) $content);
    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new ExpectationException(sprintf('The response is not valid JSON: %s', json_last_error_msg()), $this->getSession()->getDriver());
    }

    print (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  }

  /**
   * Reset all cached JSON state.
   */
  protected function jsonResetState(): void {
    $this->jsonData = NULL;
    $this->jsonContentHash = NULL;
    $this->jsonTestContent = NULL;
  }

  /**
   * Read a fixture file's contents.
   *
   * @param string $filename
   *   The fixture file name relative to the Mink files path.
   *
   * @return string
   *   The file contents.
   */
  protected function jsonReadFile(string $filename): string {
    $files_path = rtrim((string) $this->getMinkParameter('files_path'), '/');
    $file_path = $files_path . '/' . $filename;

    if (!file_exists($file_path)) {
      throw new \RuntimeException(sprintf('The file "%s" does not exist.', $file_path));
    }

    $content = file_get_contents($file_path);
    if ($content === FALSE) {
      // @codeCoverageIgnoreStart
      throw new \RuntimeException(sprintf('Failed to read the file "%s".', $file_path));
      // @codeCoverageIgnoreEnd
    }

    return $content;
  }

  /**
   * Resolve the response content to assert against.
   *
   * @return string
   *   The content set by a fixture step, or the live page content.
   */
  protected function jsonResolveContent(): string {
    return $this->jsonTestContent ?? (string) $this->getSession()->getPage()->getContent();
  }

  /**
   * Ensure the JSON response is decoded and cached.
   *
   * Re-decodes the document if the page content has changed since last decode.
   */
  protected function jsonEnsureData(): void {
    if ($this->jsonTestContent !== NULL) {
      if ($this->jsonData === NULL) {
        $this->jsonData = $this->jsonDecode($this->jsonTestContent);
      }
      return;
    }

    $content = (string) $this->getSession()->getPage()->getContent();
    $content_hash = md5($content);

    if ($this->jsonData === NULL || $this->jsonContentHash !== $content_hash) {
      $this->jsonData = $this->jsonDecode($content);
      $this->jsonContentHash = $content_hash;
    }
  }

  /**
   * Decode a JSON string into an array.
   *
   * @param string $content
   *   The JSON content to decode.
   *
   * @return array<int|string, mixed>
   *   The decoded data.
   */
  protected function jsonDecode(string $content): array {
    $data = json_decode($content, TRUE);

    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new \RuntimeException(sprintf('Failed to decode JSON: %s', json_last_error_msg()));
    }

    if (!is_array($data)) {
      throw new \RuntimeException(sprintf('The JSON response must decode to an array or object, but got %s.', gettype($data)));
    }

    return $data;
  }

  /**
   * Run a JSONPath expression against the decoded response.
   *
   * @param string $path
   *   The JSONPath expression.
   *
   * @return array<int, mixed>
   *   The list of matched values.
   */
  protected function jsonQuery(string $path): array {
    $this->jsonEnsureData();

    try {
      $result = (new JSONPath($this->jsonData))->find($path);
    }
    catch (\Exception $exception) {
      throw new ExpectationException(sprintf('The JSON path "%s" is invalid: %s', $path, $exception->getMessage()), $this->getSession()->getDriver());
    }

    $data = $result->getData();

    return is_array($data) ? array_values($data) : [];
  }

  /**
   * Resolve a JSONPath expression to a single matched value.
   *
   * @param string $path
   *   The JSONPath expression.
   *
   * @return mixed
   *   The single matched value.
   */
  protected function jsonResolveSingle(string $path): mixed {
    $matches = $this->jsonQuery($path);

    if (count($matches) === 0) {
      throw new ExpectationException(sprintf('The JSON path "%s" was not found.', $path), $this->getSession()->getDriver());
    }

    if (count($matches) > 1) {
      throw new ExpectationException(sprintf('The JSON path "%s" matched %d values, but a single value is required for this assertion.', $path, count($matches)), $this->getSession()->getDriver());
    }

    return $matches[0];
  }

  /**
   * Resolve a JSONPath expression to a single scalar value.
   *
   * @param string $path
   *   The JSONPath expression.
   *
   * @return mixed
   *   The single scalar (or null) value.
   */
  protected function jsonResolveScalar(string $path): mixed {
    $value = $this->jsonResolveSingle($path);

    if (is_array($value)) {
      throw new ExpectationException(sprintf('The JSON path "%s" resolves to an array or object, but a scalar value is required for this assertion.', $path), $this->getSession()->getDriver());
    }

    return $value;
  }

  /**
   * Convert a scalar JSON value to its string representation.
   *
   * @param mixed $value
   *   The scalar (or null) value.
   *
   * @return string
   *   The string representation: "true"/"false" for booleans, "null" for null.
   */
  protected function jsonScalarToString(mixed $value): string {
    if (is_bool($value)) {
      return $value ? 'true' : 'false';
    }

    if (is_null($value)) {
      return 'null';
    }

    return (string) $value;
  }

  /**
   * Validate the response body against a JSON schema.
   *
   * @param string $schema_json
   *   The JSON schema as a string.
   */
  protected function jsonValidateSchema(string $schema_json): void {
    if (!class_exists(Validator::class)) {
      // @codeCoverageIgnoreStart
      throw new \RuntimeException('JSON Schema validation requires the "justinrainbow/json-schema" package. Install it with "composer require --dev justinrainbow/json-schema".');
      // @codeCoverageIgnoreEnd
    }

    $content = $this->jsonResolveContent();

    $data = json_decode((string) $content);
    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new ExpectationException(sprintf('The response is not valid JSON: %s', json_last_error_msg()), $this->getSession()->getDriver());
    }

    $schema = json_decode($schema_json);
    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new ExpectationException(sprintf('The provided JSON schema is not valid JSON: %s', json_last_error_msg()), $this->getSession()->getDriver());
    }

    $validator = new Validator();
    $validator->validate($data, $schema);

    if (!$validator->isValid()) {
      $messages = [];
      foreach ($validator->getErrors() as $error) {
        $messages[] = sprintf('[%s] %s', $error['property'], $error['message']);
      }
      throw new ExpectationException(sprintf('The response does not match the JSON schema: %s', implode('; ', $messages)), $this->getSession()->getDriver());
    }
  }

}
