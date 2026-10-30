<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Helper\Web;

use Behat\Mink\Exception\ExpectationException;
use Flow\JSONPath\JSONPath;

/**
 * Looks up values in decoded JSON with JSONPath expressions.
 *
 * The lookups require the optional `softcreatr/jsonpath` package.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\WebRawContext
 */
trait JsonPathTrait {

  /**
   * Decode a JSON string into an array.
   *
   * @param string $content
   *   The JSON content to decode.
   *
   * @return array<int|string, mixed>
   *   The decoded data.
   *
   * @throws \RuntimeException
   *   When the content is not valid JSON or decodes to a scalar.
   */
  public function jsonPathDecode(string $content): array {
    $data = json_decode($content, TRUE);

    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new \RuntimeException(sprintf('Failed to decode JSON: %s.', json_last_error_msg()));
    }

    if (!is_array($data)) {
      throw new \RuntimeException(sprintf('The JSON response must decode to an array or object, but got %s.', get_debug_type($data)));
    }

    return $data;
  }

  /**
   * Run a JSONPath expression against decoded JSON.
   *
   * @param array<int|string, mixed> $data
   *   The decoded JSON.
   * @param string $path
   *   The JSONPath expression.
   *
   * @return array<int, mixed>
   *   The list of matched values.
   *
   * @throws \RuntimeException
   *   When the expression is invalid.
   */
  public function jsonPathQuery(array $data, string $path): array {
    try {
      $result = (new JSONPath($data))->find($path);
    }
    catch (\Exception $exception) {
      throw new \RuntimeException(sprintf('The JSON path "%s" is invalid: %s.', $path, $exception->getMessage()), $exception->getCode(), $exception);
    }

    $matches = $result->getData();

    return is_array($matches) ? array_values($matches) : [];
  }

  /**
   * Get the value a JSONPath expression matches in decoded JSON.
   *
   * The expression must match exactly 1 value. A JSON `null` is a match and
   * is returned as NULL.
   *
   * @param array<int|string, mixed> $data
   *   The decoded JSON.
   * @param string $path
   *   The JSONPath expression.
   *
   * @return mixed
   *   The matched value.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   If the expression matches no value or more than 1.
   */
  public function jsonPathGetValue(array $data, string $path): mixed {
    $matches = $this->jsonPathQuery($data, $path);

    if (count($matches) === 0) {
      throw new ExpectationException(sprintf('The JSON path "%s" was not found.', $path), $this->httpRestSession()->getDriver());
    }

    if (count($matches) > 1) {
      throw new ExpectationException(sprintf('The JSON path "%s" matched %d values, but a single value is required for this assertion.', $path, count($matches)), $this->httpRestSession()->getDriver());
    }

    return $matches[0];
  }

  /**
   * Get the scalar value a JSONPath expression matches in decoded JSON.
   *
   * @param array<int|string, mixed> $data
   *   The decoded JSON.
   * @param string $path
   *   The JSONPath expression.
   *
   * @return mixed
   *   The matched scalar, or NULL for a JSON `null`.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   If the expression matches no value, more than 1, or an array or object.
   */
  public function jsonPathGetScalar(array $data, string $path): mixed {
    $value = $this->jsonPathGetValue($data, $path);

    if (is_array($value)) {
      throw new ExpectationException(sprintf('The JSON path "%s" resolves to an array or object, but a scalar value is required for this assertion.', $path), $this->httpRestSession()->getDriver());
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
  protected function jsonPathFormatScalar(mixed $value): string {
    if (is_bool($value)) {
      return $value ? 'true' : 'false';
    }

    if ($value === NULL) {
      return 'null';
    }

    return (string) $value;
  }

}
