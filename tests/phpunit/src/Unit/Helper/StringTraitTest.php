<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Helper;

use DrevOps\BehatSteps\Helper\StringTrait;
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

}

/**
 * Test implementation of StringTrait.
 *
 * Exposes the protected helper methods under the test.
 */
class StringTraitTestImplementation {

  use StringTrait;

  public function callSlug(string $value): string {
    return $this->slug($value);
  }

  public function callFixStepArgument(string $value): string {
    return $this->fixStepArgument($value);
  }

  public function callNormalizeWhitespace(string $value): string {
    return $this->normalizeWhitespace($value);
  }

  /**
   * Split a comma-separated string.
   *
   * @return array<int, string>
   *   The trimmed values.
   */
  public function callSplitCommaSeparated(string $value): array {
    return $this->splitCommaSeparated($value);
  }

}
