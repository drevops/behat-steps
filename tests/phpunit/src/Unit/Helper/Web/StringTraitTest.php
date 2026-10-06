<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Helper\Web;

use DrevOps\BehatSteps\Helper\Web\StringTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for StringTrait.
 */
#[CoversTrait(StringTrait::class)]
class StringTraitTest extends UnitTestCase {

  /**
   * A test implementation of StringTrait.
   */
  protected StringTraitTestImplementation $testObject;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->testObject = new StringTraitTestImplementation();
  }

  #[DataProvider('dataProviderSlug')]
  public function testSlug(string $value, string $expected): void {
    $this->assertSame($expected, $this->testObject->callSlug($value));
  }

  public static function dataProviderSlug(): array {
    return [
      'lowercase ASCII passes through' => ['hello', 'hello'],
      'spaces become single hyphen' => ['hello world', 'hello-world'],
      'mixed case is lowercased' => ['Hello World', 'hello-world'],
      'leading and trailing whitespace trimmed' => ['  hello  ', 'hello'],
      'collapses runs of non-alphanumeric to single hyphen' => ['a___b---c   d', 'a-b-c-d'],
      'punctuation becomes hyphens' => ['feature/scenario: title!', 'feature-scenario-title'],
      'digits preserved' => ['version 1.2.3', 'version-1-2-3'],
      'leading and trailing hyphens stripped' => ['---hello---', 'hello'],
      'empty string falls back to untitled' => ['', 'untitled'],
      'whitespace-only falls back to untitled' => ['   ', 'untitled'],
      'punctuation-only falls back to untitled' => ['!!!', 'untitled'],
      'non-ASCII characters become hyphens' => ['héllo wörld', 'h-llo-w-rld'],
      'a11y digit boundary stays joined' => ['A11y Trait', 'a11y-trait'],
    ];
  }

  #[DataProvider('dataProviderFixStepArgument')]
  public function testFixStepArgument(string $value, string $expected): void {
    $this->assertSame($expected, $this->testObject->callFixStepArgument($value));
  }

  public static function dataProviderFixStepArgument(): array {
    return [
      'no escaping passes through' => ['plain', 'plain'],
      'escaped quote is unescaped' => ['say \\"hello\\"', 'say "hello"'],
      'single quote is untouched' => ["it's", "it's"],
      'backslash without a quote is untouched' => ['a\\b', 'a\\b'],
    ];
  }

  #[DataProvider('dataProviderNormalizeWhitespace')]
  public function testNormalizeWhitespace(string $value, string $expected): void {
    $this->assertSame($expected, $this->testObject->callNormalizeWhitespace($value));
  }

  public static function dataProviderNormalizeWhitespace(): array {
    return [
      'single spaces pass through' => ['a b c', 'a b c'],
      'runs of spaces collapse' => ['a    b', 'a b'],
      'tabs and newlines collapse' => ["a\t\nb", 'a b'],
      'leading and trailing whitespace trimmed' => ["  a b \n", 'a b'],
      'whitespace-only becomes empty' => ["  \t ", ''],
    ];
  }

  #[DataProvider('dataProviderSplitCommaSeparated')]
  public function testSplitCommaSeparated(string $value, array $expected): void {
    $this->assertSame($expected, $this->testObject->callSplitCommaSeparated($value));
  }

  public static function dataProviderSplitCommaSeparated(): array {
    return [
      'single value' => ['one', ['one']],
      'values are trimmed' => [' one , two ', ['one', 'two']],
      'empty segment is kept' => ['one,,two', ['one', '', 'two']],
      'empty string yields one empty value' => ['', ['']],
    ];
  }

  #[DataProvider('dataProviderParseInteger')]
  public function testParseInteger(string $value, ?int $min, int $expected): void {
    $this->assertSame($expected, $this->testObject->callParseInteger($value, 'count', $min));
  }

  public static function dataProviderParseInteger(): array {
    return [
      'zero' => ['0', NULL, 0],
      'positive' => ['42', NULL, 42],
      'negative' => ['-5', NULL, -5],
      'negative zero' => ['-0', NULL, 0],
      'explicit plus sign' => ['+7', NULL, 7],
      'surrounding whitespace' => [' 3 ', NULL, 3],
      'largest integer' => [(string) PHP_INT_MAX, NULL, PHP_INT_MAX],
      'smallest integer' => [(string) PHP_INT_MIN, NULL, PHP_INT_MIN],
      'value equal to the minimum' => ['1', 1, 1],
      'value above the minimum' => ['5', 0, 5],
      'negative value above a negative minimum' => ['-2', -3, -2],
    ];
  }

  #[DataProvider('dataProviderParseIntegerThrows')]
  public function testParseIntegerThrows(string $value, ?int $min, string $expected_message): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage($expected_message);

    $this->testObject->callParseInteger($value, 'count', $min);
  }

  public static function dataProviderParseIntegerThrows(): array {
    return [
      'word' => ['abc', NULL, 'The count must be an integer, but "abc" was given.'],
      'empty string' => ['', NULL, 'The count must be an integer, but "" was given.'],
      'whitespace only' => ['  ', NULL, 'The count must be an integer, but "  " was given.'],
      'decimal' => ['3.5', NULL, 'The count must be an integer, but "3.5" was given.'],
      'integral decimal' => ['3.0', NULL, 'The count must be an integer, but "3.0" was given.'],
      'exponent' => ['1e3', NULL, 'The count must be an integer, but "1e3" was given.'],
      'binary prefix' => ['0b101', NULL, 'The count must be an integer, but "0b101" was given.'],
      'leading zero' => ['007', NULL, 'The count must be an integer, but "007" was given.'],
      'thousands separator' => ['1,000', NULL, 'The count must be an integer, but "1,000" was given.'],
      'trailing text' => ['3abc', NULL, 'The count must be an integer, but "3abc" was given.'],
      'above the integer range' => ['9223372036854775808', NULL, 'The count must be an integer, but "9223372036854775808" was given.'],
      'below the integer range' => ['-9223372036854775809', NULL, 'The count must be an integer, but "-9223372036854775809" was given.'],
      'below a minimum of 1' => ['0', 1, 'The count must be 1 or greater, but "0" was given.'],
      'below a minimum of 0' => ['-1', 0, 'The count must be 0 or greater, but "-1" was given.'],
      'below a negative minimum' => ['-4', -3, 'The count must be -3 or greater, but "-4" was given.'],
      'format checked before the minimum' => ['abc', 1, 'The count must be an integer, but "abc" was given.'],
    ];
  }

  #[DataProvider('dataProviderParseNumber')]
  public function testParseNumber(string $value, ?float $min, float $expected): void {
    $this->assertSame($expected, $this->testObject->callParseNumber($value, 'duration', $min));
  }

  public static function dataProviderParseNumber(): array {
    return [
      'integer' => ['3', NULL, 3.0],
      'decimal' => ['0.5', NULL, 0.5],
      'leading decimal point' => ['.5', NULL, 0.5],
      'trailing decimal point' => ['5.', NULL, 5.0],
      'negative' => ['-1.5', NULL, -1.5],
      'exponent' => ['1e3', NULL, 1000.0],
      'leading zero' => ['007', NULL, 7.0],
      'surrounding whitespace' => [' 2.5 ', NULL, 2.5],
      'value equal to the minimum' => ['0', 0.0, 0.0],
      'fraction above the minimum' => ['0.1', 0.0, 0.1],
    ];
  }

  #[DataProvider('dataProviderParseNumberThrows')]
  public function testParseNumberThrows(string $value, ?float $min, string $expected_message): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage($expected_message);

    $this->testObject->callParseNumber($value, 'duration', $min);
  }

  public static function dataProviderParseNumberThrows(): array {
    return [
      'word' => ['abc', NULL, 'The duration must be a number, but "abc" was given.'],
      'empty string' => ['', NULL, 'The duration must be a number, but "" was given.'],
      'binary prefix' => ['0b101', NULL, 'The duration must be a number, but "0b101" was given.'],
      'thousands separator' => ['1,000.5', NULL, 'The duration must be a number, but "1,000.5" was given.'],
      'trailing text' => ['1.5s', NULL, 'The duration must be a number, but "1.5s" was given.'],
      'infinity' => ['INF', NULL, 'The duration must be a number, but "INF" was given.'],
      'not a number' => ['NAN', NULL, 'The duration must be a number, but "NAN" was given.'],
      'beyond the float range' => ['1e999', NULL, 'The duration must be a number, but "1e999" was given.'],
      'below a minimum of 0' => ['-0.5', 0.0, 'The duration must be 0 or greater, but "-0.5" was given.'],
      'below a fractional minimum' => ['0.25', 0.5, 'The duration must be 0.5 or greater, but "0.25" was given.'],
      'format checked before the minimum' => ['abc', 0.0, 'The duration must be a number, but "abc" was given.'],
    ];
  }

}

/**
 * Test implementation of StringTrait.
 *
 * Exposes the protected helper methods under the test.
 */
class StringTraitTestImplementation {

  use StringTrait;

  public function callSlug(string $value): string {
    return $this->stringSlug($value);
  }

  public function callFixStepArgument(string $value): string {
    return $this->stringFixStepArgument($value);
  }

  public function callNormalizeWhitespace(string $value): string {
    return $this->stringNormalizeWhitespace($value);
  }

  /**
   * Splits a comma-separated string.
   *
   * @return array<int, string>
   *   The trimmed values.
   */
  public function callSplitCommaSeparated(string $value): array {
    return $this->stringSplitCommaSeparated($value);
  }

  public function callParseInteger(string $value, string $name, ?int $min): int {
    return $this->stringParseInteger($value, $name, $min);
  }

  public function callParseNumber(string $value, string $name, ?float $min): float {
    return $this->stringParseNumber($value, $name, $min);
  }

}
