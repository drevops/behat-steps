<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests the layer dependency check.
 */
#[CoversFunction('layer_files')]
#[CoversFunction('layer_file_violations')]
#[CoversFunction('layer_referenced_symbol')]
class LintLayersTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    require_once __DIR__ . '/../../../scripts/lint-layers.php';
  }

  /**
   * Assert that the shipped driver layer holds the rule.
   */
  public function testShippedDriverLayerIsClean(): void {
    $violations = [];

    foreach (layer_files(dirname(__DIR__, 3) . '/' . LAYER_DIRECTORY) as $file) {
      $violations = array_merge($violations, layer_file_violations($file, LAYER_FORBIDDEN_ROOTS));
    }

    $this->assertSame([], $violations);
  }

  /**
   * Assert that only PHP files are collected, in path order.
   */
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
   * The classes named here do not exist. The scan reads tokens rather than
   * resolving them, and an expected symbol that matches a real class is
   * rewritten to a '::class' constant by static tooling, which drops the
   * leading separator the scan reports.
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
        "<?php\n\nuse Drupal\\Component\\Utility\\Random;\n",
        [],
      ],
      'prose mention in a comment' => [
        "<?php\n\n// The driver runs without Behat\\Mink loaded.\n",
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

  /**
   * Write a file below the per-test temporary directory.
   *
   * @param string $path
   *   Path relative to the temporary directory. Missing parent directories
   *   are created.
   * @param string $contents
   *   The file contents.
   *
   * @return string
   *   The absolute path written.
   */
  protected function writeFixture(string $path, string $contents): string {
    $full_path = static::$tmp . DIRECTORY_SEPARATOR . $path;
    $directory = dirname($full_path);

    if (!is_dir($directory)) {
      mkdir($directory, 0777, TRUE);
    }

    file_put_contents($full_path, $contents);

    return $full_path;
  }

}
