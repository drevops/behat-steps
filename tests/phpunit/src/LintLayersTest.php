<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests the layer dependency check.
 */
#[CoversFunction('layer_paths_files')]
#[CoversFunction('layer_files')]
#[CoversFunction('layer_file_violations')]
#[CoversFunction('layer_is_forbidden')]
#[CoversFunction('layer_has_prefix')]
#[CoversFunction('layer_imported_symbol')]
#[CoversFunction('layer_referenced_symbol')]
#[CoversFunction('layer_file_call_violations')]
#[CoversFunction('layer_code_tokens')]
#[CoversFunction('layer_call')]
#[CoversFunction('layer_is_internal_function')]
class LintLayersTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    require_once __DIR__ . '/../../../scripts/lint-layers.php';
  }

  public function testShippedLayersAreClean(): void {
    $root = dirname(__DIR__, 3);
    $violations = [];

    foreach (LAYERS as $layer) {
      foreach (layer_paths_files($layer['paths'], $layer['exclude'], $root) as $file) {
        $references = layer_file_violations($file, $layer['forbidden'], $layer['allowed'] ?? []);
        $calls = layer_file_call_violations($file, $layer['forbidden_methods'] ?? [], $layer['internal_functions_only'] ?? FALSE);
        $violations = array_merge($violations, $references, $calls);
      }
    }

    $this->assertSame([], $violations);
  }

  public function testPathsFilesMergesPathsAndSorts(): void {
    $this->writeFixture('src/B/Only.php', '<?php');
    $this->writeFixture('src/A/Second.php', '<?php');
    $this->writeFixture('src/A/First.php', '<?php');

    $expected = [
      static::$tmp . '/src/A/First.php',
      static::$tmp . '/src/A/Second.php',
      static::$tmp . '/src/B/Only.php',
    ];

    $this->assertSame($expected, layer_paths_files(['src/B/Only.php', 'src/A'], [], static::$tmp));
  }

  public function testPathsFilesSkipsExcludedDirectoryAndFile(): void {
    $this->writeFixture('src/A/Kept.php', '<?php');
    $this->writeFixture('src/A/Skipped.php', '<?php');
    $this->writeFixture('src/A/Skip/Nested.php', '<?php');
    $this->writeFixture('src/A/Skipper/Lookalike.php', '<?php');

    $expected = [
      static::$tmp . '/src/A/Kept.php',
      static::$tmp . '/src/A/Skipper/Lookalike.php',
    ];

    $this->assertSame($expected, layer_paths_files(['src/A'], ['src/A/Skip', 'src/A/Skipped.php'], static::$tmp));
  }

  public function testFilesCollectsPhpFilesRecursively(): void {
    $this->writeFixture('Nested/Second.php', '<?php');
    $this->writeFixture('First.php', '<?php');
    $this->writeFixture('README.md', 'not code');

    $expected = [
      static::$tmp . '/First.php',
      static::$tmp . '/Nested/Second.php',
    ];

    $this->assertSame($expected, layer_files(static::$tmp));
  }

  public function testFilesCollectsSingleFile(): void {
    $file = $this->writeFixture('Only.php', '<?php');

    $this->assertSame([$file], layer_files($file));
  }

  /**
   * Assert that a file is reported against the forbidden roots.
   *
   * @param string $code
   *   The file contents to scan.
   * @param array<int, array{line: int, symbol: string}> $expected
   *   The expected violations.
   */
  #[DataProvider('dataProviderFileViolations')]
  public function testFileViolations(string $code, array $expected): void {
    $file = $this->writeFixture('Subject.php', $code);

    $this->assertSame($expected, layer_file_violations($file, ['Behat', 'Mink']));
  }

  /**
   * Fixture rows for the violation scan.
   *
   * The classes named here under the forbidden roots do not exist. The scan
   * reads tokens rather than resolving them.
   *
   * Static tooling rewrites an expected symbol that matches a real class to
   * a '::class' constant, which drops the leading separator the scan reports.
   */
  public static function dataProviderFileViolations(): array {
    return [
      'import' => [
        "<?php\n\nuse Behat\\Mink\\FakeSession;\n",
        [['line' => 3, 'symbol' => 'Behat\\Mink\\FakeSession']],
      ],
      'fully qualified reference' => [
        "<?php\n\n\$session = new \\Behat\\Mink\\FakeSession();\n",
        [['line' => 3, 'symbol' => '\\Behat\\Mink\\FakeSession']],
      ],
      'class name in a string literal' => [
        "<?php\n\n\$class = 'Mink\\Driver\\FakeDriver';\n",
        [['line' => 3, 'symbol' => 'Mink\\Driver\\FakeDriver']],
      ],
      'several references' => [
        "<?php\n\nuse Behat\\Mink\\FakeSession;\nuse Mink\\FakeThing;\n",
        [
          ['line' => 3, 'symbol' => 'Behat\\Mink\\FakeSession'],
          ['line' => 4, 'symbol' => 'Mink\\FakeThing'],
        ],
      ],
      'permitted namespace' => [
        "<?php\n\nuse Symfony\\Component\\Process\\Process;\n",
        [],
      ],
      'prose mention in a comment' => [
        "<?php\n\n// The backend runs without Behat\\Mink loaded.\n",
        [],
      ],
      'prose mention in a docblock' => [
        "<?php\n\n/**\n * Usable without Behat or Mink.\n */\n",
        [],
      ],
      'string that is not a class name' => [
        "<?php\n\n\$message = 'Behat is not required';\n",
        [],
      ],
      'unqualified name' => [
        "<?php\n\nclass Behat {}\n",
        [],
      ],
      'bare import of a global class' => [
        "<?php\n\nuse Behat;\n",
        [['line' => 3, 'symbol' => 'Behat']],
      ],
      'aliased bare import of a global class' => [
        "<?php\n\nuse Mink as M;\n",
        [['line' => 3, 'symbol' => 'Mink']],
      ],
      'trait use inside a class body' => [
        "<?php\n\nclass Subject {\n  use Behat;\n}\n",
        [],
      ],
      'closure use' => [
        "<?php\n\n\$f = function () use (\$x) {};\n",
        [],
      ],
      'bare import of a permitted class' => [
        "<?php\n\nuse Exception;\n",
        [],
      ],
    ];
  }

  public function testFileViolationsAgainstNamespacePrefix(): void {
    $file = $this->writeFixture('Subject.php', "<?php\n\nuse DrevOps\\BehatSteps\\Extension\\FakeTag;\nuse DrevOps\\BehatSteps\\Steps\\Web\\FakeTrait;\n");

    $expected = [['line' => 3, 'symbol' => 'DrevOps\\BehatSteps\\Extension\\FakeTag']];

    $this->assertSame($expected, layer_file_violations($file, ['DrevOps\\BehatSteps\\Extension']));
  }

  public function testFileViolationsSkipsAllowedPrefix(): void {
    $file = $this->writeFixture('Subject.php', "<?php\n\nuse DrevOps\\BehatSteps\\Backend\\Drupal\\Capability\\FakeCapabilityInterface;\nuse DrevOps\\BehatSteps\\Backend\\Drupal\\Core\\FakeCore;\n");

    $expected = [['line' => 4, 'symbol' => 'DrevOps\\BehatSteps\\Backend\\Drupal\\Core\\FakeCore']];

    $this->assertSame($expected, layer_file_violations($file, ['DrevOps\\BehatSteps\\Backend\\Drupal'], ['DrevOps\\BehatSteps\\Backend\\Drupal\\Capability']));
  }

  /**
   * Assert that a prefix matches whole segments only.
   *
   * @param string $symbol
   *   The qualified symbol to check.
   * @param array<int, string> $forbidden
   *   The forbidden prefixes.
   * @param bool $expected
   *   Whether the symbol is forbidden.
   */
  #[DataProvider('dataProviderIsForbidden')]
  public function testIsForbidden(string $symbol, array $forbidden, bool $expected): void {
    $this->assertSame($expected, layer_is_forbidden($symbol, $forbidden));
  }

  public static function dataProviderIsForbidden(): array {
    return [
      'a symbol below the prefix' => ['DrevOps\\BehatSteps\\Extension\\FakeTag', ['DrevOps\\BehatSteps\\Extension'], TRUE],
      'the prefix itself' => ['DrevOps\\BehatSteps\\Extension', ['DrevOps\\BehatSteps\\Extension'], TRUE],
      'a segment the prefix only starts' => ['DrevOps\\BehatSteps\\ExtensionLike\\FakeTag', ['DrevOps\\BehatSteps\\Extension'], FALSE],
      'a symbol below a root' => ['Behat\\Mink\\FakeSession', ['Behat'], TRUE],
      'a root the symbol only starts with' => ['Behatlike\\FakeThing', ['Behat'], FALSE],
      'a symbol under none of the prefixes' => ['Symfony\\Component\\FakeThing', ['Behat', 'DrevOps\\BehatSteps\\Extension'], FALSE],
      'no prefixes' => ['Behat\\Mink\\FakeSession', [], FALSE],
    ];
  }

  /**
   * Assert that an allowed prefix reopens a namespace below a forbidden one.
   *
   * @param string $symbol
   *   The qualified symbol to check.
   * @param bool $expected
   *   Whether the symbol is forbidden.
   */
  #[DataProvider('dataProviderIsForbiddenWithAllowedPrefix')]
  public function testIsForbiddenWithAllowedPrefix(string $symbol, bool $expected): void {
    $this->assertSame($expected, layer_is_forbidden($symbol, ['DrevOps\\BehatSteps\\Backend\\Drupal'], ['DrevOps\\BehatSteps\\Backend\\Drupal\\Capability']));
  }

  public static function dataProviderIsForbiddenWithAllowedPrefix(): array {
    return [
      'a symbol below the allowed prefix' => ['DrevOps\\BehatSteps\\Backend\\Drupal\\Capability\\FakeCapabilityInterface', FALSE],
      'a symbol beside the allowed prefix' => ['DrevOps\\BehatSteps\\Backend\\Drupal\\Core\\FakeCore', TRUE],
      'a segment the allowed prefix only starts' => ['DrevOps\\BehatSteps\\Backend\\Drupal\\CapabilityLike\\FakeThing', TRUE],
      'a symbol under neither prefix' => ['DrevOps\\BehatSteps\\Backend\\FakeThing', FALSE],
    ];
  }

  /**
   * Assert that a file is reported for the calls a layer forbids.
   *
   * @param string $code
   *   The file contents to scan.
   * @param array<int, array{line: int, symbol: string}> $expected
   *   The expected violations.
   */
  #[DataProvider('dataProviderFileCallViolations')]
  public function testFileCallViolations(string $code, array $expected): void {
    $file = $this->writeFixture('Subject.php', $code);

    $this->assertSame($expected, layer_file_call_violations($file, ['getCore'], TRUE));
  }

  /**
   * Fixture rows for the call scan.
   *
   * The functions named here outside PHP's own set do not exist.
   */
  public static function dataProviderFileCallViolations(): array {
    return [
      'a forbidden method' => ["<?php\n\n\$core = \$backend->getCore();\n", [['line' => 3, 'symbol' => '->getCore()']]],
      'a forbidden method through the nullsafe operator' => ["<?php\n\n\$core = \$backend?->getCore();\n", [['line' => 3, 'symbol' => '?->getCore()']]],
      'a forbidden static method' => ["<?php\n\n\$core = Backend::getCore();\n", [['line' => 3, 'symbol' => '::getCore()']]],
      'a forbidden method in another case' => ["<?php\n\n\$core = \$backend->GETCORE();\n", [['line' => 3, 'symbol' => '->GETCORE()']]],
      'a forbidden method inside a string' => ["<?php\n\n\$text = \"{\$backend->getCore()}\";\n", [['line' => 3, 'symbol' => '->getCore()']]],
      'a permitted method' => ["<?php\n\n\$version = \$backend->getDrupalVersion();\n", []],
      'a function PHP defines' => ["<?php\n\n\$text = sprintf('%s', 'a');\n", []],
      'a fully qualified function PHP defines' => ["<?php\n\n\$text = \\sprintf('%s', 'a');\n", []],
      'a function PHP does not define' => ["<?php\n\nfake_drupal_flush_caches();\n", [['line' => 3, 'symbol' => 'fake_drupal_flush_caches()']]],
      'a fully qualified function PHP does not define' => ["<?php\n\n\\fake_drupal_static_reset();\n", [['line' => 3, 'symbol' => '\\fake_drupal_static_reset()']]],
      'a namespaced function' => ["<?php\n\nFake\\helper();\n", [['line' => 3, 'symbol' => 'Fake\\helper()']]],
      'a first-class callable of a function PHP defines' => ["<?php\n\n\$callable = strval(...);\n", []],
      'a function declaration' => ["<?php\n\nfunction fake_helper(): void {}\n", []],
      'a function declaration returning by reference' => ["<?php\n\nfunction &fake_helper(): array {}\n", []],
      'an instantiation' => ["<?php\n\n\$object = new FakeClass();\n", []],
      'an attribute' => ["<?php\n\n#[FakeAttribute('value', ['key' => 'value'])]\nfunction fake_helper(): void {}\n", []],
      'a language construct' => ["<?php\n\n\$is_set = isset(\$a) && !empty(\$b);\n", []],
      'a closure and an arrow function' => ["<?php\n\n\$f = function () {};\n\$g = fn() => 1;\n", []],
      'a call in a comment' => ["<?php\n\n// fake_drupal_flush_caches();\n", []],
    ];
  }

  public function testFileCallViolationsAllowsEveryFunctionWhenTheCheckIsOff(): void {
    $file = $this->writeFixture('Subject.php', "<?php\n\nfake_drupal_flush_caches();\n");

    $this->assertSame([], layer_file_call_violations($file, [], FALSE));
  }

}
