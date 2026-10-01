<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests that every trait reads tags through the Tag readers, by name.
 *
 * A reader given the scope reads the scenario together with its feature, so a
 * tag on the 'Feature:' line applies to every scenario below it. A tag named
 * in a constant is spelled once, and the constant is what every read shares.
 */
#[CoversNothing]
class TagReadTest extends UnitTestCase {

  /**
   * The Tag methods a trait reads a tag through.
   */
  protected const READERS = ['has', 'values', 'valueStates'];

  /**
   * The Tag methods that return a raw list, which a trait does not call.
   */
  protected const RAW_READERS = ['all', 'normalize', 'on'];

  /**
   * The methods that return a single node of a scope.
   */
  protected const NODE_GETTERS = ['getFeature', 'getScenario'];

  /**
   * Tests that a trait reads tags only through the readers, with named tags.
   *
   * @param string $trait
   *   Fully qualified trait name.
   */
  #[DataProvider('dataProviderTraitReadsTagsThroughReaders')]
  public function testTraitReadsTagsThroughReaders(string $trait): void {
    $violations = [];
    $tokens = static::significantTokens((string) static::reflect($trait)->getFileName());

    foreach (array_keys($tokens) as $index) {
      $method = static::tagMethodCalledAt($tokens, $index);

      if ($method === NULL) {
        continue;
      }

      $line = is_array($tokens[$index]) ? $tokens[$index][2] : 0;

      if (in_array($method, static::RAW_READERS, TRUE)) {
        $violations[] = sprintf('Line %d calls Tag::%s(). Read a tag through Tag::has(), Tag::values() or Tag::valueStates().', $line, $method);

        continue;
      }

      if (!in_array($method, static::READERS, TRUE)) {
        continue;
      }

      [$subject, $name] = static::callArguments($tokens, $index + 3) + [[], []];

      if (static::callsAny($subject, static::NODE_GETTERS)) {
        $violations[] = sprintf('Line %d passes Tag::%s() a single node. Pass the scope, so a tag on the "Feature:" line applies to the scenario.', $line, $method);
      }

      if (static::holdsStringLiteral($name)) {
        $violations[] = sprintf('Line %d passes Tag::%s() the tag as a string literal. Name the tag in a constant.', $line, $method);
      }
    }

    $this->assertSame([], $violations, 'A trait reads a tag through Tag::has(), Tag::values() or Tag::valueStates(), passing the scope and a constant naming the tag.');
  }

  public static function dataProviderTraitReadsTagsThroughReaders(): array {
    return static::discoverTraits();
  }

  /**
   * Return the Tag method a static call starting at a token invokes.
   *
   * @param array<int, array{int, string, int}|string> $tokens
   *   Tokens without whitespace and comments.
   * @param int $index
   *   The token that may open the call.
   *
   * @return string|null
   *   The method name, or NULL when the tokens there do not call 'Tag::'.
   */
  protected static function tagMethodCalledAt(array $tokens, int $index): ?string {
    $class = $tokens[$index];
    $separator = $tokens[$index + 1] ?? NULL;
    $method = $tokens[$index + 2] ?? NULL;

    if (!is_array($class) || $class[0] !== T_STRING || $class[1] !== 'Tag') {
      return NULL;
    }

    if (!is_array($separator) || $separator[0] !== T_DOUBLE_COLON || !is_array($method) || $method[0] !== T_STRING) {
      return NULL;
    }

    return ($tokens[$index + 3] ?? NULL) === '(' ? $method[1] : NULL;
  }

  /**
   * Split the arguments of a call into their tokens.
   *
   * @param array<int, array{int, string, int}|string> $tokens
   *   Tokens without whitespace and comments.
   * @param int $open
   *   The index of the call's opening parenthesis.
   *
   * @return array<int, array<int, array{int, string, int}|string>>
   *   The tokens of each argument, in order.
   */
  protected static function callArguments(array $tokens, int $open): array {
    $arguments = [[]];
    $depth = 0;
    $count = count($tokens);

    for ($index = $open; $index < $count; $index++) {
      $token = $tokens[$index];

      if (in_array($token, ['(', '[', '{'], TRUE)) {
        $depth++;

        if ($depth === 1) {
          continue;
        }
      }
      elseif (in_array($token, [')', ']', '}'], TRUE)) {
        $depth--;

        if ($depth === 0) {
          break;
        }
      }
      elseif ($token === ',' && $depth === 1) {
        $arguments[] = [];

        continue;
      }

      $arguments[count($arguments) - 1][] = $token;
    }

    return $arguments;
  }

  /**
   * Whether an argument calls any of the named methods.
   *
   * @param array<int, array{int, string, int}|string> $argument
   *   The tokens of one argument.
   * @param array<int, string> $methods
   *   The method names to look for.
   */
  protected static function callsAny(array $argument, array $methods): bool {
    foreach ($argument as $token) {
      if (is_array($token) && $token[0] === T_STRING && in_array($token[1], $methods, TRUE)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Whether an argument holds a string literal.
   *
   * @param array<int, array{int, string, int}|string> $argument
   *   The tokens of one argument.
   */
  protected static function holdsStringLiteral(array $argument): bool {
    foreach ($argument as $token) {
      if (is_array($token) && in_array($token[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], TRUE)) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
