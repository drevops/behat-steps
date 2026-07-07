<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use DrevOps\BehatSteps\HelperTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for HelperTrait.
 */
#[CoversClass(HelperTrait::class)]
class HelperTraitTest extends UnitTestCase {

  /**
   * A test implementation of HelperTrait.
   */
  protected HelperTraitTestImplementation $testObject;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->testObject = new HelperTraitTestImplementation();
  }

  #[DataProvider('dataProviderSlug')]
  public function testSlug(string $value, string $expected): void {
    $this->assertSame($expected, $this->testObject->callHelperSlug($value));
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
      'unicode strips to untitled when no ASCII alnum remains' => ['héllo wörld', 'h-llo-w-rld'],
      'a11y digit boundary stays joined' => ['A11y Trait', 'a11y-trait'],
    ];
  }

}

/**
 * Test implementation of HelperTrait.
 *
 * Exposes the protected helper methods under the test.
 */
class HelperTraitTestImplementation {

  use HelperTrait;

  public function callHelperSlug(string $value): string {
    return $this->helperSlug($value);
  }

}
