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
#[CoversFunction('layer_referenced_symbol')]
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
        $violations = array_merge($violations, layer_file_violations($file, $layer['forbidden']));
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
    ];
  }

  public function testFileViolationsAgainstNamespacePrefix(): void {
    $file = $this->writeFixture('Subject.php', "<?php\n\nuse DrevOps\\BehatSteps\\Extension\\FakeTag;\nuse DrevOps\\BehatSteps\\Steps\\Web\\FakeTrait;\n");

    $expected = [['line' => 3, 'symbol' => 'DrevOps\\BehatSteps\\Extension\\FakeTag']];

    $this->assertSame($expected, layer_file_violations($file, ['DrevOps\\BehatSteps\\Extension']));
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

}
