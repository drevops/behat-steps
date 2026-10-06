<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Asserts that every constant declared under `src/` carries a native type.
 *
 * CONTRIBUTING.md states the rule. Rector's 'AddTypeToConstRector' types a
 * constant only in a final class, so this test holds the rest.
 */
#[CoversNothing]
class TypedConstantTest extends UnitTestCase {

  /**
   * Assert that a type declares every constant with a native type.
   *
   * @param class-string $type
   *   The class, interface or trait to check.
   */
  #[DataProvider('dataProviderConstantsDeclareNativeTypes')]
  public function testConstantsDeclareNativeTypes(string $type): void {
    $untyped = static::collectUntypedConstants((string) file_get_contents((string) static::reflect($type)->getFileName()));

    $this->assertSame([], $untyped, sprintf('%s declares %s without a native type. Write the type after "const": "public const string NAME", not "public const NAME".', $type, implode(', ', $untyped)));
  }

  public static function dataProviderConstantsDeclareNativeTypes(): array {
    return static::discoverSourceTypes();
  }

  /**
   * Assert that an untyped constant is found, and nothing else is.
   *
   * @param string $source
   *   PHP source code.
   * @param array<int, string> $expected
   *   The constants expected to be reported, in source order.
   */
  #[DataProvider('dataProviderUntypedConstantsAreDetected')]
  public function testUntypedConstantsAreDetected(string $source, array $expected): void {
    $this->assertSame($expected, static::collectUntypedConstants($source));
  }

  public static function dataProviderUntypedConstantsAreDetected(): array {
    return [
      'typed' => ["<?php\nclass A {\n  public const string B = 'b';\n}\n", []],
      'untyped' => ["<?php\nclass A {\n  public const B = 'b';\n}\n", ['B']],
      'nullable type' => ["<?php\nclass A {\n  public const ?string B = NULL;\n}\n", []],
      'union type' => ["<?php\nclass A {\n  public const int|string B = 1;\n}\n", []],
      'final' => ["<?php\nclass A {\n  final public const B = 1;\n}\n", ['B']],
      'no visibility' => ["<?php\nclass A {\n  const B = 1;\n}\n", ['B']],
      'several in one declaration' => ["<?php\nclass A {\n  const B = 1, C = 2;\n}\n", ['B']],
      'interface' => ["<?php\ninterface A {\n  const B = 1;\n}\n", ['B']],
      'trait' => ["<?php\ntrait A {\n  protected const B = [];\n}\n", ['B']],
      'enum' => ["<?php\nenum A {\n  case B;\n  const C = self::B;\n}\n", ['C']],
      'anonymous class' => ["<?php\nfunction a(): object {\n  return new class {\n    const B = 1;\n  };\n}\n", ['B']],
      'after a method' => ["<?php\nclass A {\n  public function b(): string {\n    return \"{\$this->c}\";\n  }\n  const D = 1;\n}\n", ['D']],
      'namespace constant' => ["<?php\nnamespace A;\nconst B = 1;\n", []],
      'imported constant' => ["<?php\nuse const A\\B;\nclass C {}\n", []],
      'after a class constant fetch' => ["<?php\n\$a = B::class;\nconst C = 1;\n", []],
    ];
  }

  /**
   * Collect the constants a class-like body declares without a native type.
   *
   * A constant outside a class, interface, trait or enum body cannot carry a
   * type, so only the bodies are read.
   *
   * An untyped declaration puts '=' right after the name, and a typed one
   * puts the type between 'const' and the name.
   *
   * @param string $source
   *   PHP source code.
   *
   * @return array<int, string>
   *   The first constant each untyped declaration names, in source order.
   */
  protected static function collectUntypedConstants(string $source): array {
    $tokens = array_values(array_filter(\PhpToken::tokenize($source), static fn(\PhpToken $token): bool => !$token->isIgnorable()));
    $bodies = [];
    $depth = 0;
    $opens_body = FALSE;
    $previous = NULL;
    $untyped = [];

    foreach ($tokens as $index => $token) {
      // 'Foo::class' carries the class keyword without declaring a class.
      if ($token->is([T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM]) && !$previous?->is(T_DOUBLE_COLON)) {
        $opens_body = TRUE;
      }
      elseif ($token->is(['{', T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES])) {
        $depth++;

        if ($opens_body) {
          $bodies[] = $depth;
          $opens_body = FALSE;
        }
      }
      elseif ($token->is('}')) {
        if (end($bodies) === $depth) {
          array_pop($bodies);
        }

        $depth--;
      }
      elseif ($token->is(T_CONST) && end($bodies) === $depth && ($tokens[$index + 2] ?? NULL)?->is('=')) {
        $untyped[] = $tokens[$index + 1]->text;
      }

      $previous = $token;
    }

    return $untyped;
  }

}
