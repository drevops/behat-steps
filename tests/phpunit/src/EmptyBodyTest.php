<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Asserts that every empty class-like and function body is written as '{}'.
 *
 * CONTRIBUTING.md states the rule. No installed coding standard has a sniff
 * for it, so this test holds it.
 */
#[CoversNothing]
class EmptyBodyTest extends UnitTestCase {

  /**
   * Paths the coding standard checks, relative to the repository root.
   */
  protected const PATHS = [
    'docs.php',
    'scripts',
    'src',
    'tests/behat/bootstrap',
    'tests/behat/fixtures_drupal/d11/web/modules/custom',
    'tests/behat/fixtures_drupal/d12/web/modules/custom',
    'tests/phpunit/src',
  ];

  /**
   * Files kept in step with an upstream copy, so never reformatted.
   */
  protected const EXCLUDED = ['tests/behat/bootstrap/BehatCliContext.php'];

  /**
   * Extensions of the files holding PHP.
   */
  protected const EXTENSIONS = ['engine', 'inc', 'install', 'module', 'php', 'profile', 'theme'];

  /**
   * Tests that a file writes every empty body as '{}'.
   *
   * @param string $file
   *   A path relative to the repository root.
   */
  #[DataProvider('dataProviderEmptyBodiesAreBracePairs')]
  public function testEmptyBodiesAreBracePairs(string $file): void {
    $lines = static::paddedEmptyBodies((string) file_get_contents(dirname(__DIR__, 3) . '/' . $file));

    $this->assertSame([], $lines, sprintf('%s pads an empty body on line(s) %s. Write it as "{}".', $file, implode(', ', $lines)));
  }

  public static function dataProviderEmptyBodiesAreBracePairs(): array {
    return static::discoverFiles();
  }

  /**
   * Tests that a padded empty body is found, and nothing else is.
   *
   * @param string $source
   *   PHP source code.
   * @param array<int, int> $expected
   *   The lines expected to hold a padded empty body.
   */
  #[DataProvider('dataProviderPaddedEmptyBodiesAreDetected')]
  public function testPaddedEmptyBodiesAreDetected(string $source, array $expected): void {
    $this->assertSame($expected, static::paddedEmptyBodies($source));
  }

  public static function dataProviderPaddedEmptyBodiesAreDetected(): array {
    return [
      'class written as a brace pair' => ["<?php\nclass A {}\n", []],
      'class with a brace on each line' => ["<?php\nclass A {\n}\n", [2]],
      'class wrapping a blank line' => ["<?php\nclass A {\n\n}\n", [2]],
      'class wrapping a space' => ["<?php\nclass A { }\n", [2]],
      'interface' => ["<?php\ninterface A extends B {\n}\n", [2]],
      'trait' => ["<?php\ntrait A {\n}\n", [2]],
      'enum' => ["<?php\nenum A {\n}\n", [2]],
      'anonymous class' => ["<?php\n\$a = new class {\n};\n", [2]],
      'method' => ["<?php\nclass A {\n\n  public function b(): void {\n  }\n\n}\n", [4]],
      'promoted constructor' => ["<?php\nclass A {\n\n  public function __construct(\n    protected int \$b,\n  ) {\n  }\n\n}\n", [6]],
      'closure' => ["<?php\n\$a = static function (): void {\n};\n", [2]],
      'body holding a comment' => ["<?php\nclass A {\n  // Nothing to add.\n}\n", []],
      'class holding a member' => ["<?php\nclass A {\n\n  public int \$b = 1;\n\n}\n", []],
      'abstract method before an empty one' => ["<?php\ninterface A {\n\n  public function b(): void;\n\n}\nfunction c(): void {\n}\n", [7]],
      'catch block' => ["<?php\ntry {\n  a();\n}\ncatch (\\Exception) {\n}\n", []],
      'control structure' => ["<?php\nif (\$a) {\n}\n", []],
      'class constant in a condition' => ["<?php\nif (\$a === B::class) {\n}\n", []],
      'interpolated string' => ["<?php\nfunction a(): string {\n  return \"{\$b}\";\n}\n", []],
    ];
  }

  /**
   * Return the lines of the empty bodies holding whitespace.
   *
   * A body belongs to a class, an interface, a trait, an enum or a function,
   * closures included. A 'catch' block or a control structure has none.
   *
   * @param string $source
   *   PHP source code.
   *
   * @return array<int, int>
   *   The line of each padded body's opening brace.
   */
  protected static function paddedEmptyBodies(string $source): array {
    $tokens = \PhpToken::tokenize($source);
    $lines = [];
    $opens_body = FALSE;
    $previous = NULL;

    foreach ($tokens as $index => $token) {
      if ($token->isIgnorable()) {
        continue;
      }

      // 'Foo::class' carries the class keyword without declaring a class.
      if ($token->is([T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM, T_FUNCTION]) && !$previous?->is(T_DOUBLE_COLON)) {
        $opens_body = TRUE;
      }
      elseif ($token->id === ord('{')) {
        if ($opens_body && static::isPadded($tokens, $index)) {
          $lines[] = $token->line;
        }

        $opens_body = FALSE;
      }
      elseif ($token->is([';', '}'])) {
        $opens_body = FALSE;
      }

      $previous = $token;
    }

    return $lines;
  }

  /**
   * Check whether only whitespace separates an opening brace from its closer.
   *
   * @param array<int, \PhpToken> $tokens
   *   The tokens of a file.
   * @param int $index
   *   The position of the opening brace.
   */
  protected static function isPadded(array $tokens, int $index): bool {
    return ($tokens[$index + 1] ?? NULL)?->is(T_WHITESPACE) === TRUE && ($tokens[$index + 2] ?? NULL)?->id === ord('}');
  }

  /**
   * Return every file the coding standard checks, keyed by relative path.
   *
   * @return array<string, array{string}>
   *   Paths relative to the repository root, as data provider rows.
   */
  protected static function discoverFiles(): array {
    $root = dirname(__DIR__, 3);
    $files = [];

    foreach (static::PATHS as $path) {
      if (is_file($root . '/' . $path)) {
        $files[$path] = [$path];

        continue;
      }

      $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $path, \FilesystemIterator::SKIP_DOTS));

      foreach ($iterator as $file) {
        if (!$file instanceof \SplFileInfo || !in_array($file->getExtension(), static::EXTENSIONS, TRUE)) {
          continue;
        }

        $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($root) + 1));

        if (!in_array($relative, static::EXCLUDED, TRUE)) {
          $files[$relative] = [$relative];
        }
      }
    }

    ksort($files);

    return $files;
  }

}
