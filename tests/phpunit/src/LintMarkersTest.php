<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests the trait marker check.
 */
#[CoversFunction('marker_traits')]
#[CoversFunction('marker_file_facts')]
#[CoversFunction('marker_name')]
#[CoversFunction('marker_violations')]
#[CoversFunction('marker_is_hook')]
class LintMarkersTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    require_once __DIR__ . '/../../../scripts/lint-markers.php';
  }

  /**
   * Assert that every shipped trait declares a consistent marker.
   */
  public function testShippedTraitsAreMarked(): void {
    $traits = marker_traits(dirname(__DIR__, 3));

    $this->assertNotEmpty($traits);
    $this->assertSame([], marker_violations($traits));
  }

  /**
   * Assert that only trait files below the marked directories are read.
   */
  public function testTraitsCollectsPhpFilesBelowMarkedDirectories(): void {
    $this->writeFixture('src/Steps/Web/PathTrait.php', "<?php\n\n#[Steps]\ntrait PathTrait {}\n");
    $this->writeFixture('src/Steps/Web/README.md', 'not code');
    $this->writeFixture('src/Helper/StringTrait.php', "<?php\n\n#[Helper]\ntrait StringTrait {}\n");
    $this->writeFixture('src/Driver/Ignored.php', "<?php\n\ntrait Ignored {}\n");

    $this->assertSame(['PathTrait', 'StringTrait'], array_keys(marker_traits(static::$tmp)));
  }

  /**
   * Assert that a repository missing a marked directory reads the rest.
   */
  public function testTraitsSkipsMissingDirectory(): void {
    $this->writeFixture('src/Helper/StringTrait.php', "<?php\n\n#[Helper]\ntrait StringTrait {}\n");

    $this->assertSame(['StringTrait'], array_keys(marker_traits(static::$tmp)));
  }

  /**
   * Assert that the facts of one file are read from its tokens.
   *
   * @param string $code
   *   The file contents to read.
   * @param array<string, array<int, string>> $expected
   *   The expected facts.
   */
  #[DataProvider('dataProviderFileFacts')]
  public function testFileFacts(string $code, array $expected): void {
    $file = $this->writeFixture('Subject.php', $code);

    $this->assertSame($expected, marker_file_facts($file));
  }

  /**
   * Fixture rows for the fact reader.
   */
  public static function dataProviderFileFacts(): array {
    return [
      'bare marker' => [
        "<?php\n\n#[Steps]\ntrait PathTrait {}\n",
        ['markers' => ['Steps'], 'composed' => [], 'members' => []],
      ],
      'imported marker' => [
        "<?php\n\nuse DrevOps\\BehatSteps\\Attribute\\Steps;\n\n#[Steps]\ntrait PathTrait {}\n",
        ['markers' => ['Steps'], 'composed' => [], 'members' => []],
      ],
      'fully qualified marker' => [
        "<?php\n\n#[\\DrevOps\\BehatSteps\\Attribute\\Steps]\ntrait PathTrait {}\n",
        ['markers' => ['Steps'], 'composed' => [], 'members' => []],
      ],
      'composed trait' => [
        "<?php\n\n#[Steps]\ntrait PathTrait {\n  use StringTrait;\n}\n",
        ['markers' => ['Steps'], 'composed' => ['StringTrait'], 'members' => []],
      ],
      'composed trait with a conflict resolution block' => [
        "<?php\n\n#[Steps]\ntrait PathTrait {\n  use StringTrait {\n    slug as protected rawSlug;\n  }\n}\n",
        ['markers' => ['Steps'], 'composed' => ['StringTrait'], 'members' => []],
      ],
      'closure binding a variable' => [
        "<?php\n\n#[Steps]\ntrait PathTrait {\n  public function run(): callable {\n    \$value = 1;\n\n    return function () use (\$value) {\n      return \$value;\n    };\n  }\n}\n",
        ['markers' => ['Steps'], 'composed' => [], 'members' => []],
      ],
      'member attribute with arguments' => [
        "<?php\n\n#[Steps]\ntrait PathTrait {\n  #[Then('the path should be :path')]\n  public function assertPath(string \$path): void {}\n}\n",
        ['markers' => ['Steps'], 'composed' => [], 'members' => ['Then']],
      ],
      'member attribute with an array argument' => [
        "<?php\n\n#[Steps]\ntrait PathTrait {\n  #[Then(['a', 'b'])]\n  public function assertPath(): void {}\n}\n",
        ['markers' => ['Steps'], 'composed' => [], 'members' => ['Then']],
      ],
      'member attribute naming a constant' => [
        "<?php\n\n#[Steps]\ntrait PathTrait {\n  #[Then(self::PATTERN)]\n  public function assertPath(): void {}\n}\n",
        ['markers' => ['Steps'], 'composed' => [], 'members' => ['Then']],
      ],
      'grouped member attributes' => [
        "<?php\n\n#[Steps]\ntrait PathTrait {\n  #[Given('a'), Then('b')]\n  public function assertPath(): void {}\n}\n",
        ['markers' => ['Steps'], 'composed' => [], 'members' => ['Given', 'Then']],
      ],
      'no marker at all' => [
        "<?php\n\ntrait PathTrait {}\n",
        ['markers' => [], 'composed' => [], 'members' => []],
      ],
    ];
  }

  /**
   * Assert that the marker rules are reported against the traits.
   *
   * @param array<string, array{markers: array<int, string>, composed: array<int, string>, members: array<int, string>}> $traits
   *   The traits to check.
   * @param array<int, string> $expected
   *   The expected violations.
   */
  #[DataProvider('dataProviderViolations')]
  public function testViolations(array $traits, array $expected): void {
    $this->assertSame($expected, marker_violations($traits));
  }

  /**
   * Fixture rows for the violation check.
   */
  public static function dataProviderViolations(): array {
    $steps = ['markers' => ['Steps'], 'composed' => [], 'members' => []];
    $helper = ['markers' => ['Helper'], 'composed' => [], 'members' => []];

    return [
      'a marked pair' => [
        ['PathTrait' => $steps, 'StringTrait' => $helper],
        [],
      ],
      'no marker' => [
        ['PathTrait' => ['markers' => [], 'composed' => [], 'members' => []]],
        ['PathTrait carries 0 markers, and exactly one of #[Steps] or #[Helper] is required'],
      ],
      'both markers' => [
        ['PathTrait' => ['markers' => ['Steps', 'Helper'], 'composed' => [], 'members' => []]],
        ['PathTrait carries 2 markers, and exactly one of #[Steps] or #[Helper] is required'],
      ],
      'one marker repeated' => [
        ['PathTrait' => ['markers' => ['Steps', 'Steps'], 'composed' => [], 'members' => []]],
        [],
      ],
      'vocabulary composing vocabulary' => [
        [
          'PathTrait' => ['markers' => ['Steps'], 'composed' => ['LinkTrait'], 'members' => []],
          'LinkTrait' => $steps,
        ],
        ['PathTrait composes the vocabulary trait LinkTrait'],
      ],
      'vocabulary composing plumbing' => [
        [
          'PathTrait' => ['markers' => ['Steps'], 'composed' => ['StringTrait'], 'members' => []],
          'StringTrait' => $helper,
        ],
        [],
      ],
      'plumbing composing plumbing' => [
        [
          'LastStepTrait' => ['markers' => ['Helper'], 'composed' => ['StringTrait'], 'members' => []],
          'StringTrait' => $helper,
        ],
        [],
      ],
      'plumbing registering a step' => [
        ['StringTrait' => ['markers' => ['Helper'], 'composed' => [], 'members' => ['Then']]],
        ['StringTrait registers Gherkin through #[Then]'],
      ],
      'plumbing registering a transformation' => [
        ['StringTrait' => ['markers' => ['Helper'], 'composed' => [], 'members' => ['Transform']]],
        ['StringTrait registers Gherkin through #[Transform]'],
      ],
      'plumbing registering a hook' => [
        ['StringTrait' => ['markers' => ['Helper'], 'composed' => [], 'members' => ['BeforeScenario']]],
        ['StringTrait registers a hook through #[BeforeScenario]'],
      ],
      'plumbing allowed to register a hook' => [
        ['DrupalApiTrait' => ['markers' => ['Helper'], 'composed' => [], 'members' => ['AfterScenario']]],
        [],
      ],
      'plumbing carrying an unrelated attribute' => [
        ['StringTrait' => ['markers' => ['Helper'], 'composed' => [], 'members' => ['Deprecated']]],
        [],
      ],
      'vocabulary registering a step' => [
        ['PathTrait' => ['markers' => ['Steps'], 'composed' => [], 'members' => ['Then', 'BeforeScenario']]],
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
