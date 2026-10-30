<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Steps\Helper\Web;

use Behat\Mink\Driver\BrowserKitDriver;
use Behat\Mink\Exception\ExpectationException;
use Behat\Mink\Session;
use DrevOps\BehatSteps\Steps\Helper\Web\JsonPathTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use DrevOps\BehatSteps\WebRawContext;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\HttpClient\MockHttpClient;

/**
 * Tests for JsonPathTrait.
 */
#[CoversTrait(JsonPathTrait::class)]
class JsonPathTraitTest extends UnitTestCase {

  /**
   * The decoded JSON the lookups run against.
   */
  protected const array DATA = [
    'name' => 'John Doe',
    'age' => 42,
    'active' => TRUE,
    'deleted_at' => NULL,
    'roles' => ['admin', 'editor'],
    'user' => ['id' => 1],
  ];

  /**
   * A test implementation of JsonPathTrait.
   */
  protected JsonPathTraitTestImplementation $testObject;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->testObject = new JsonPathTraitTestImplementation();
  }

  /**
   * Tests decoding JSON content.
   *
   * @param string $content
   *   The JSON content.
   * @param array<int|string, mixed>|null $expected
   *   The decoded data, or NULL when decoding fails.
   * @param string|null $expected_message
   *   The exception message when decoding fails.
   */
  #[DataProvider('dataProviderDecode')]
  public function testDecode(string $content, ?array $expected, ?string $expected_message): void {
    if ($expected_message !== NULL) {
      $this->expectException(\RuntimeException::class);
      $this->expectExceptionMessage($expected_message);
    }

    $this->assertSame($expected, $this->testObject->jsonPathDecode($content));
  }

  public static function dataProviderDecode(): array {
    return [
      'object' => ['{"name": "John"}', ['name' => 'John'], NULL],
      'array' => ['[1, 2]', [1, 2], NULL],
      'invalid' => ['{invalid', NULL, 'Failed to decode JSON: Syntax error.'],
      'scalar' => ['"text"', NULL, 'The JSON response must decode to an array or object, but got string.'],
    ];
  }

  /**
   * Tests running a JSONPath expression.
   *
   * @param string $path
   *   The JSONPath expression.
   * @param array<int, mixed> $expected
   *   The matched values.
   */
  #[DataProvider('dataProviderQuery')]
  public function testQuery(string $path, array $expected): void {
    $this->assertSame($expected, $this->testObject->jsonPathQuery(static::DATA, $path));
  }

  public static function dataProviderQuery(): array {
    return [
      'scalar' => ['$.name', ['John Doe']],
      'wildcard' => ['$.roles[*]', ['admin', 'editor']],
      'container' => ['$.user', [['id' => 1]]],
      'null' => ['$.deleted_at', [NULL]],
      'missing' => ['$.missing', []],
    ];
  }

  public function testQueryRejectsInvalidPath(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The JSON path "$.items[?(@.x" is invalid:');

    $this->testObject->jsonPathQuery(static::DATA, '$.items[?(@.x');
  }

  /**
   * Tests getting the value a JSONPath expression matches.
   *
   * @param string $path
   *   The JSONPath expression.
   * @param mixed $expected
   *   The matched value.
   */
  #[DataProvider('dataProviderGetValue')]
  public function testGetValue(string $path, mixed $expected): void {
    $this->assertSame($expected, $this->testObject->jsonPathGetValue(static::DATA, $path));
  }

  public static function dataProviderGetValue(): array {
    return [
      'scalar' => ['$.age', 42],
      'container' => ['$.user', ['id' => 1]],
      'null' => ['$.deleted_at', NULL],
    ];
  }

  /**
   * Tests the lookups that fail on what a JSONPath expression matches.
   *
   * @param string $method
   *   The helper to call.
   * @param string $path
   *   The JSONPath expression.
   * @param string $expected_message
   *   The exception message.
   */
  #[DataProvider('dataProviderGetThrows')]
  public function testGetThrows(string $method, string $path, string $expected_message): void {
    $this->expectException(ExpectationException::class);
    $this->expectExceptionMessage($expected_message);

    $this->testObject->{$method}(static::DATA, $path);
  }

  public static function dataProviderGetThrows(): array {
    return [
      'value missing' => ['jsonPathGetValue', '$.missing', 'The JSON path "$.missing" was not found.'],
      'value several' => ['jsonPathGetValue', '$.roles[*]', 'The JSON path "$.roles[*]" matched 2 values, but a single value is required for this assertion.'],
      'scalar missing' => ['jsonPathGetScalar', '$.missing', 'The JSON path "$.missing" was not found.'],
      'scalar container' => ['jsonPathGetScalar', '$.user', 'The JSON path "$.user" resolves to an array or object, but a scalar value is required for this assertion.'],
    ];
  }

  /**
   * Tests getting the scalar a JSONPath expression matches.
   *
   * @param string $path
   *   The JSONPath expression.
   * @param mixed $expected
   *   The matched scalar.
   */
  #[DataProvider('dataProviderGetScalar')]
  public function testGetScalar(string $path, mixed $expected): void {
    $this->assertSame($expected, $this->testObject->jsonPathGetScalar(static::DATA, $path));
  }

  public static function dataProviderGetScalar(): array {
    return [
      'string' => ['$.name', 'John Doe'],
      'integer' => ['$.age', 42],
      'boolean' => ['$.active', TRUE],
      'null' => ['$.deleted_at', NULL],
    ];
  }

  /**
   * Tests formatting a scalar JSON value.
   *
   * @param mixed $value
   *   The scalar value.
   * @param string $expected
   *   The formatted value.
   */
  #[DataProvider('dataProviderFormatScalar')]
  public function testFormatScalar(mixed $value, string $expected): void {
    $this->assertSame($expected, $this->testObject->callFormatScalar($value));
  }

  public static function dataProviderFormatScalar(): array {
    return [
      'string' => ['text', 'text'],
      'integer' => [42, '42'],
      'float' => [1.5, '1.5'],
      'true' => [TRUE, 'true'],
      'false' => [FALSE, 'false'],
      'null' => [NULL, 'null'],
    ];
  }

}

/**
 * Test implementation of JsonPathTrait.
 */
class JsonPathTraitTestImplementation extends WebRawContext {

  use JsonPathTrait;

  /**
   * {@inheritdoc}
   */
  public function httpRestSession(): Session {
    return new Session(new BrowserKitDriver(new HttpBrowser(new MockHttpClient())));
  }

  /**
   * Run jsonPathFormatScalar().
   */
  public function callFormatScalar(mixed $value): string {
    return $this->jsonPathFormatScalar($value);
  }

}
