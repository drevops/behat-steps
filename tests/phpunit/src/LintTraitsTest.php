<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests the trait composition check.
 */
#[CoversFunction('traits_collect')]
#[CoversFunction('traits_file_facts')]
#[CoversFunction('traits_name')]
#[CoversFunction('traits_violations')]
class LintTraitsTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    require_once __DIR__ . '/../../../scripts/lint-traits.php';
  }

  /**
   * Assert that every shipped trait composes what its directory allows.
   */
  public function testShippedTraitsComposeWhatTheyMay(): void {
    $traits = traits_collect(dirname(__DIR__, 3));

    $this->assertNotEmpty($traits);
    $this->assertSame([], traits_violations($traits));
  }

  /**
   * Assert that a trait's kind is read from its directory.
   */
  public function testCollectReadsBothDirectories(): void {
    $this->writeFixture('src/Steps/Web/PathTrait.php', "<?php\n\ntrait PathTrait {}\n");
    $this->writeFixture('src/Steps/Web/README.md', 'not code');
    $this->writeFixture('src/Helper/StringTrait.php', "<?php\n\ntrait StringTrait {}\n");
    $this->writeFixture('src/Backend/Ignored.php', "<?php\n\ntrait Ignored {}\n");

    $collected = traits_collect(static::$tmp);

    $this->assertSame(['PathTrait', 'StringTrait'], array_keys($collected));
    $this->assertSame('steps', $collected['PathTrait']['kind']);
    $this->assertSame('helper', $collected['StringTrait']['kind']);
  }

  /**
   * Assert that collection skips a missing directory and reads the rest.
   */
  public function testCollectSkipsMissingDirectory(): void {
    $this->writeFixture('src/Helper/StringTrait.php', "<?php\n\ntrait StringTrait {}\n");

    $this->assertSame(['StringTrait'], array_keys(traits_collect(static::$tmp)));
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

    $this->assertSame($expected, traits_file_facts($file));
  }

  /**
   * Fixture rows for the fact reader.
   */
  public static function dataProviderFileFacts(): array {
    return [
      'bare trait' => [
        "<?php\n\ntrait PathTrait {}\n",
        ['composed' => [], 'members' => []],
      ],
      'composed trait' => [
        "<?php\n\ntrait PathTrait {\n  use StringTrait;\n}\n",
        ['composed' => ['StringTrait'], 'members' => []],
      ],
      'composed trait with a conflict resolution block' => [
        "<?php\n\ntrait PathTrait {\n  use StringTrait {\n    slug as protected rawSlug;\n  }\n}\n",
        ['composed' => ['StringTrait'], 'members' => []],
      ],
      'imported composition' => [
        "<?php\n\nuse DrevOps\\BehatSteps\\Helper\\StringTrait;\n\ntrait PathTrait {\n  use StringTrait;\n}\n",
        ['composed' => ['StringTrait'], 'members' => []],
      ],
      'closure binding a variable' => [
        "<?php\n\ntrait PathTrait {\n  public function run(): callable {\n    \$value = 1;\n\n    return function () use (\$value) {\n      return \$value;\n    };\n  }\n}\n",
        ['composed' => [], 'members' => []],
      ],
      'member attribute with arguments' => [
        "<?php\n\ntrait PathTrait {\n  #[Then('the path should be :path')]\n  public function assertPath(string \$path): void {}\n}\n",
        ['composed' => [], 'members' => ['Then']],
      ],
      'member attribute with an array argument' => [
        "<?php\n\ntrait PathTrait {\n  #[Then(['a', 'b'])]\n  public function assertPath(): void {}\n}\n",
        ['composed' => [], 'members' => ['Then']],
      ],
      'member attribute naming a constant' => [
        "<?php\n\ntrait PathTrait {\n  #[Then(self::PATTERN)]\n  public function assertPath(): void {}\n}\n",
        ['composed' => [], 'members' => ['Then']],
      ],
      'grouped member attributes' => [
        "<?php\n\ntrait PathTrait {\n  #[Given('a'), Then('b')]\n  public function assertPath(): void {}\n}\n",
        ['composed' => [], 'members' => ['Given', 'Then']],
      ],
      'attribute above the trait is not a member' => [
        "<?php\n\n#[Deprecated]\ntrait PathTrait {}\n",
        ['composed' => [], 'members' => []],
      ],
    ];
  }

  /**
   * Assert that the composition rules are reported against the traits.
   *
   * @param array<string, array{kind: string, composed: array<int, string>, members: array<int, string>}> $traits
   *   The traits to check.
   * @param array<int, string> $expected
   *   The expected violations.
   */
  #[DataProvider('dataProviderViolations')]
  public function testViolations(array $traits, array $expected): void {
    $this->assertSame($expected, traits_violations($traits));
  }

  /**
   * Fixture rows for the violation check.
   */
  public static function dataProviderViolations(): array {
    $steps = ['kind' => 'steps', 'composed' => [], 'members' => []];
    $helper = ['kind' => 'helper', 'composed' => [], 'members' => []];

    return [
      'a clean pair' => [
        ['PathTrait' => $steps, 'StringTrait' => $helper],
        [],
      ],
      'step trait composing a step trait' => [
        [
          'PathTrait' => ['kind' => 'steps', 'composed' => ['LinkTrait'], 'members' => []],
          'LinkTrait' => $steps,
        ],
        ['PathTrait composes the step trait LinkTrait'],
      ],
      'step trait composing a helper' => [
        [
          'PathTrait' => ['kind' => 'steps', 'composed' => ['StringTrait'], 'members' => []],
          'StringTrait' => $helper,
        ],
        [],
      ],
      'step trait composing an unknown trait' => [
        [
          'PathTrait' => ['kind' => 'steps', 'composed' => ['SomeVendorTrait'], 'members' => []],
        ],
        [],
      ],
      'helper composing a helper' => [
        [
          'AuthTrait' => ['kind' => 'helper', 'composed' => ['EntityLifecycleTrait'], 'members' => []],
          'EntityLifecycleTrait' => $helper,
        ],
        [],
      ],
      'helper registering a step' => [
        ['StringTrait' => ['kind' => 'helper', 'composed' => [], 'members' => ['Then']]],
        ['StringTrait registers Gherkin through #[Then]'],
      ],
      'helper registering a transformation' => [
        ['StringTrait' => ['kind' => 'helper', 'composed' => [], 'members' => ['Transform']]],
        ['StringTrait registers Gherkin through #[Transform]'],
      ],
      'helper registering a hook' => [
        ['EntityLifecycleTrait' => ['kind' => 'helper', 'composed' => [], 'members' => ['AfterScenario']]],
        [],
      ],
      'step trait registering anything' => [
        ['PathTrait' => ['kind' => 'steps', 'composed' => [], 'members' => ['Then', 'BeforeScenario']]],
        [],
      ],
    ];
  }

}
