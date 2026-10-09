<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Asserts that a blank line separates every control structure from its code.
 *
 * CONTRIBUTING.md states the rule.
 */
#[CoversNothing]
class ControlStructureSpacingTest extends UnitTestCase {

  /**
   * Keywords that open a control structure.
   */
  protected const KEYWORDS = [T_IF, T_FOREACH, T_FOR, T_WHILE, T_DO, T_SWITCH, T_TRY];

  /**
   * Tests that a file separates every control structure with blank lines.
   *
   * @param string $file
   *   A path relative to the repository root.
   */
  #[DataProvider('dataProviderControlStructuresAreSeparated')]
  public function testControlStructuresAreSeparated(string $file): void {
    $lines = static::findUnseparatedStructures((string) file_get_contents(dirname(__DIR__, 3) . '/' . $file));

    $this->assertSame([], $lines, sprintf('%s leaves no blank line around the control structure on line(s) %s. Add one above it, unless it opens its block, and below it, unless it closes its block.', $file, implode(', ', $lines)));
  }

  public static function dataProviderControlStructuresAreSeparated(): array {
    return static::discoverCodingStandardFiles();
  }

  /**
   * Tests that an unseparated control structure is found, and nothing else is.
   *
   * @param string $source
   *   PHP source code.
   * @param array<int, int> $expected
   *   The lines expected to hold an unseparated control structure.
   */
  #[DataProvider('dataProviderUnseparatedStructuresAreDetected')]
  public function testUnseparatedStructuresAreDetected(string $source, array $expected): void {
    $this->assertSame($expected, static::findUnseparatedStructures($source));
  }

  public static function dataProviderUnseparatedStructuresAreDetected(): array {
    return [
      'separated on both sides' => ["<?php\n\$a = 1;\n\nif (\$a) {\n  \$b = 2;\n}\n\n\$c = 3;\n", []],
      'statement directly above' => ["<?php\n\$a = 1;\nif (\$a) {\n}\n", [3]],
      'statement directly below' => ["<?php\nif (\$a) {\n}\n\$b = 1;\n", [2]],
      'first and last in its block' => ["<?php\nfunction a(): void {\n  if (\$b) {\n  }\n}\n", []],
      'nested structure followed by a statement' => ["<?php\nforeach (\$a as \$b) {\n  if (\$b) {\n  }\n  \$c = 1;\n}\n", [3]],
      'consecutive structures' => ["<?php\nif (\$a) {\n}\nif (\$b) {\n}\n", [2, 4]],
      'every keyword' => ["<?php\n\$a = 1;\nfor (;;) {\n}\nforeach (\$a as \$b) {\n}\nwhile (\$a) {\n}\nswitch (\$a) {\n}\ntry {\n}\nfinally {\n}\ndo {\n} while (\$a);\nif (\$a) {\n}\n", [3, 5, 7, 9, 11, 15, 17]],
      'comment above belonging to the structure' => ["<?php\n\$a = 1;\n\n// Explain.\nif (\$a) {\n}\n", []],
      'comment between a statement and the structure' => ["<?php\n\$a = 1;\n// Explain.\nif (\$a) {\n}\n", [4]],
      'trailing comment on the statement above' => ["<?php\n\$a = 1; // Note.\nif (\$a) {\n}\n", [3]],
      'else, elseif and else if continuing the structure' => ["<?php\nif (\$a) {\n}\nelseif (\$b) {\n}\nelse if (\$c) {\n}\nelse {\n}\n\n\$d = 1;\n", []],
      'catch and finally continuing the structure' => ["<?php\ntry {\n}\ncatch (\\Exception) {\n}\nfinally {\n}\n\$a = 1;\n", [2]],
      'while closing a do block' => ["<?php\n\$a = 1;\n\ndo {\n} while (\$a);\n\n\$b = 2;\n", []],
      'case labels around the structure' => ["<?php\nswitch (\$a) {\n  case 1:\n    if (\$b) {\n    }\n  case 2:\n    break;\n}\n", []],
      'break after the structure' => ["<?php\nswitch (\$a) {\n  case 1:\n    if (\$b) {\n    }\n    break;\n}\n", [4]],
      'comment above the statement below' => ["<?php\nif (\$a) {\n}\n\n// @codeCoverageIgnoreEnd\n\$b = 1;\n", []],
      'comment against the structure and the statement below' => ["<?php\nif (\$a) {\n}\n// @codeCoverageIgnoreEnd\n\$b = 1;\n", [2]],
      'comment before a closing brace' => ["<?php\nfunction a(): void {\n  // @codeCoverageIgnoreStart\n  if (\$b) {\n  }\n  // @codeCoverageIgnoreEnd\n}\n", []],
      'closure body' => ["<?php\n\$a = static function (): void {\n  if (\$b) {\n  }\n};\n", []],
      'match expression' => ["<?php\n\$a = 1;\n\$b = match (\$a) {\n  1 => 2,\n};\n", []],
      'keyword inside a string' => ["<?php\n\$a = 'if (\$b) {}';\n\$c = <<<'EOT'\nforeach (\$d as \$e) {}\nEOT;\n", []],
    ];
  }

  /**
   * Return the lines of the control structures missing a blank line.
   *
   * A structure needs a blank line above it, unless it opens its block, and
   * below it, unless it closes its block. The blank line may sit anywhere
   * among the comments between the structure and its neighbour.
   *
   * @param string $source
   *   PHP source code.
   *
   * @return array<int, int>
   *   The line of each unseparated structure's keyword.
   */
  protected static function findUnseparatedStructures(string $source): array {
    $tokens = \PhpToken::tokenize($source);
    $pairs = static::collectBracketPairs($tokens);
    $lines = [];

    foreach ($tokens as $index => $token) {
      if (!$token->is(static::KEYWORDS) || !static::isStatementStart($tokens, $pairs, $index)) {
        continue;
      }

      $previous = static::findPreviousSignificant($tokens, $index);

      if ($previous !== NULL && !static::isBlockStart($tokens[$previous]) && !static::isSeparated($tokens, $previous, $index)) {
        $lines[] = $token->line;
      }

      $end = static::findStructureEnd($tokens, $pairs, $index);
      $next = $end === NULL ? NULL : static::findNextSignificant($tokens, $end);

      if ($end !== NULL && $next !== NULL && !static::isBlockEnd($tokens[$next]) && !static::isSeparated($tokens, $end, $next)) {
        $lines[] = $token->line;
      }
    }

    return array_values(array_unique($lines));
  }

  /**
   * Check whether a keyword opens a statement of its own.
   *
   * A keyword opens a statement when it follows a statement or a block
   * opener. The 'if' of an 'else if' continues its chain, and the 'while'
   * after a 'do' block closes that block.
   *
   * @param array<int, \PhpToken> $tokens
   *   The tokens of a file.
   * @param array<int, int> $pairs
   *   The position of each bracket's partner, keyed by position.
   * @param int $index
   *   The position of the keyword.
   */
  protected static function isStatementStart(array $tokens, array $pairs, int $index): bool {
    $previous = static::findPreviousSignificant($tokens, $index);

    if ($previous === NULL) {
      return TRUE;
    }

    if ($tokens[$index]->is(T_IF) && $tokens[$previous]->is(T_ELSE)) {
      return FALSE;
    }

    if ($tokens[$index]->is(T_WHILE) && $tokens[$previous]->text === '}') {
      $opener = static::findPreviousSignificant($tokens, $pairs[$previous] ?? $previous);

      if ($opener !== NULL && $tokens[$opener]->is(T_DO)) {
        return FALSE;
      }
    }

    return in_array($tokens[$previous]->text, [';', '{', '}', ':'], TRUE);
  }

  /**
   * Check whether a token opens the block a statement sits in.
   *
   * @param \PhpToken $token
   *   The token before the statement.
   */
  protected static function isBlockStart(\PhpToken $token): bool {
    return in_array($token->text, ['{', ':'], TRUE);
  }

  /**
   * Check whether a token closes the block a statement sits in.
   *
   * @param \PhpToken $token
   *   The token after the statement.
   */
  protected static function isBlockEnd(\PhpToken $token): bool {
    return $token->text === '}' || $token->is([T_CASE, T_DEFAULT]);
  }

  /**
   * Check whether a blank line separates 2 tokens.
   *
   * @param array<int, \PhpToken> $tokens
   *   The tokens of a file.
   * @param int $from
   *   The position of the first token.
   * @param int $to
   *   The position of the second token.
   */
  protected static function isSeparated(array $tokens, int $from, int $to): bool {
    $gap = implode('', array_map(static fn(\PhpToken $token): string => $token->text, array_slice($tokens, $from + 1, $to - $from - 1)));

    return preg_match('/\n[ \t]*\n/', $gap) === 1;
  }

  /**
   * Return the position of the token that ends a control structure.
   *
   * The structure takes in its 'elseif', 'else', 'catch' and 'finally'
   * blocks, and a 'do' block takes in its closing 'while'.
   *
   * @param array<int, \PhpToken> $tokens
   *   The tokens of a file.
   * @param array<int, int> $pairs
   *   The position of each bracket's partner, keyed by position.
   * @param int $index
   *   The position of the keyword.
   *
   * @return int|null
   *   The position of the last closing brace, or of the semicolon ending a
   *   'do-while', or NULL for a structure written without braces.
   */
  protected static function findStructureEnd(array $tokens, array $pairs, int $index): ?int {
    if ($tokens[$index]->is(T_DO)) {
      $end = static::findBlockEnd($tokens, $pairs, $index);
      $while = $end === NULL ? NULL : static::findNextSignificant($tokens, $end);
      $condition_end = $while === NULL ? NULL : static::findConditionEnd($tokens, $pairs, $while);

      return $condition_end === NULL ? NULL : static::findNextSignificant($tokens, $condition_end);
    }

    $end = $tokens[$index]->is(T_TRY) ? static::findBlockEnd($tokens, $pairs, $index) : static::findConditionalBlockEnd($tokens, $pairs, $index);

    while ($end !== NULL) {
      $next = static::findNextSignificant($tokens, $end);

      if ($next === NULL || !$tokens[$next]->is([T_ELSEIF, T_ELSE, T_CATCH, T_FINALLY])) {
        return $end;
      }

      $after = static::findNextSignificant($tokens, $next);

      // An 'else if' continues with the condition of its 'if'.
      if ($after !== NULL && $tokens[$after]->is(T_IF)) {
        $next = $after;
      }

      $end = $tokens[$next]->is([T_ELSE, T_FINALLY]) ? static::findBlockEnd($tokens, $pairs, $next) : static::findConditionalBlockEnd($tokens, $pairs, $next);
    }

    return NULL;
  }

  /**
   * Return the position of the brace closing the block after a token.
   *
   * @param array<int, \PhpToken> $tokens
   *   The tokens of a file.
   * @param array<int, int> $pairs
   *   The position of each bracket's partner, keyed by position.
   * @param int $index
   *   The position of the token before the block.
   *
   * @return int|null
   *   The position of the closing brace, or NULL when no block follows.
   */
  protected static function findBlockEnd(array $tokens, array $pairs, int $index): ?int {
    $opener = static::findNextSignificant($tokens, $index);

    return $opener !== NULL && $tokens[$opener]->text === '{' ? ($pairs[$opener] ?? NULL) : NULL;
  }

  /**
   * Return the position of the brace closing the block after a condition.
   *
   * @param array<int, \PhpToken> $tokens
   *   The tokens of a file.
   * @param array<int, int> $pairs
   *   The position of each bracket's partner, keyed by position.
   * @param int $index
   *   The position of the keyword the condition follows.
   *
   * @return int|null
   *   The position of the closing brace, or NULL when no block follows.
   */
  protected static function findConditionalBlockEnd(array $tokens, array $pairs, int $index): ?int {
    $condition_end = static::findConditionEnd($tokens, $pairs, $index);

    return $condition_end === NULL ? NULL : static::findBlockEnd($tokens, $pairs, $condition_end);
  }

  /**
   * Return the position of the parenthesis closing a condition.
   *
   * @param array<int, \PhpToken> $tokens
   *   The tokens of a file.
   * @param array<int, int> $pairs
   *   The position of each bracket's partner, keyed by position.
   * @param int $index
   *   The position of the keyword the condition follows.
   *
   * @return int|null
   *   The position of the closing parenthesis, or NULL when none follows.
   */
  protected static function findConditionEnd(array $tokens, array $pairs, int $index): ?int {
    $opener = static::findNextSignificant($tokens, $index);

    return $opener !== NULL && $tokens[$opener]->text === '(' ? ($pairs[$opener] ?? NULL) : NULL;
  }

  /**
   * Return the position of each bracket's partner.
   *
   * @param array<int, \PhpToken> $tokens
   *   The tokens of a file.
   *
   * @return array<int, int>
   *   The position of each bracket's partner, keyed by position.
   */
  protected static function collectBracketPairs(array $tokens): array {
    $pairs = [];
    $openers = [];

    foreach ($tokens as $index => $token) {
      // An attribute's '#[' and an interpolation's '${' close with a plain
      // bracket.
      if (in_array($token->text, ['(', '[', '{', '#[', '${'], TRUE)) {
        $openers[] = $index;
      }
      elseif (in_array($token->text, [')', ']', '}'], TRUE)) {
        $opener = array_pop($openers);

        if ($opener !== NULL) {
          $pairs[$opener] = $index;
          $pairs[$index] = $opener;
        }
      }
    }

    return $pairs;
  }

  /**
   * Return the position of the nearest code token before a token.
   *
   * @param array<int, \PhpToken> $tokens
   *   The tokens of a file.
   * @param int $index
   *   The position to search back from.
   *
   * @return int|null
   *   The position, or NULL when only the opening tag, whitespace and
   *   comments come before.
   */
  protected static function findPreviousSignificant(array $tokens, int $index): ?int {
    for ($i = $index - 1; $i >= 0; $i--) {
      if (!$tokens[$i]->isIgnorable()) {
        return $i;
      }
    }

    return NULL;
  }

  /**
   * Return the position of the nearest code token after a token.
   *
   * @param array<int, \PhpToken> $tokens
   *   The tokens of a file.
   * @param int $index
   *   The position to search forward from.
   *
   * @return int|null
   *   The position, or NULL when only whitespace and comments follow.
   */
  protected static function findNextSignificant(array $tokens, int $index): ?int {
    $count = count($tokens);

    for ($i = $index + 1; $i < $count; $i++) {
      if (!$tokens[$i]->isIgnorable()) {
        return $i;
      }
    }

    return NULL;
  }

}
