<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use DrevOps\BehatSteps\Tests\Fixtures\EarlyBoundMembers;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Asserts that the test suite follows the settled test conventions.
 *
 * Each rule has 1 check: the base a test extends, where a test covering
 * nothing sits, how a test reaches a static member, how a test helper is
 * named, how a test double names its test-only methods and itself, and how a
 * unit test writes a fixture and reflects a class.
 *
 * CONTRIBUTING.md states the rules.
 */
#[CoversNothing]
class TestConventionTest extends UnitTestCase {

  /**
   * Verbs a test helper names its action with.
   */
  protected const HELPER_VERBS = [
    'assert',
    'attach',
    'build',
    'call',
    'capture',
    'collect',
    'copy',
    'count',
    'create',
    'declare',
    'derive',
    'describe',
    'discover',
    'find',
    'get',
    'has',
    'initialize',
    'install',
    'invoke',
    'is',
    'label',
    'list',
    'load',
    'locate',
    'mock',
    'process',
    'read',
    'reflect',
    'render',
    'reset',
    'resolve',
    'run',
    'set',
    'skip',
    'split',
    'tokenize',
    'write',
  ];

  /**
   * Methods PHPUnit runs around a test by name.
   */
  protected const LIFECYCLE_METHODS = ['setUp', 'setUpBeforeClass', 'tearDown', 'tearDownAfterClass'];

  /**
   * Functions that write a file, which a unit test leaves to 'writeFixture()'.
   */
  protected const FILE_WRITERS = ['copy', 'file_put_contents', 'touch'];

  /**
   * Assert that a test class extends the base its suite runs on.
   *
   * @param class-string $class
   *   The test class to check.
   */
  #[DataProvider('dataProviderTestsExtendTheirSuiteBase')]
  public function testTestsExtendTheirSuiteBase(string $class): void {
    $base = str_starts_with($class, __NAMESPACE__ . '\\Kernel\\') ? KernelTestBase::class : UnitTestCase::class;

    $this->assertTrue(is_subclass_of($class, $base), sprintf('%s extends neither %s nor a class that does, against the convention in CONTRIBUTING.md.', $class, $base));
  }

  public static function dataProviderTestsExtendTheirSuiteBase(): array {
    return array_diff_key(static::discoverTestClasses(), [UnitTestCase::class => TRUE]);
  }

  /**
   * Assert that a test covering nothing sits at the root of the suite tree.
   *
   * The suite directories mirror 'src/', so a test with no counterpart there
   * has no directory under them.
   *
   * @param class-string $class
   *   The test class to check.
   */
  #[DataProvider('dataProviderTestsCoveringNothingSitAtTheRoot')]
  public function testTestsCoveringNothingSitAtTheRoot(string $class): void {
    $is_at_root = !str_contains(substr($class, strlen(__NAMESPACE__) + 1), '\\');

    $this->assertTrue($is_at_root || static::reflect($class)->getAttributes(CoversNothing::class) === [], sprintf('%s covers nothing but sits in a suite directory, against the convention in CONTRIBUTING.md. Move it to the root of tests/phpunit/src/.', $class));
  }

  public static function dataProviderTestsCoveringNothingSitAtTheRoot(): array {
    return array_diff_key(static::discoverTestClasses(), [UnitTestCase::class => TRUE]);
  }

  /**
   * Assert that a run-time static reference goes through 'static::'.
   *
   * 'self::' stays where late static binding has nothing to resolve. PHP
   * rejects 'static::' in a constant expression, and Rector requires 'self::'
   * on a final class, a final member or a private member.
   *
   * @param string $file
   *   The file to check.
   */
  #[DataProvider('dataProviderStaticMembersAreReachedThroughStatic')]
  public function testStaticMembersAreReachedThroughStatic(string $file): void {
    $violations = [];

    foreach (static::collectRuntimeSelfReferences((string) file_get_contents($file)) as $reference) {
      if (static::isLateBindable($reference['class'], $reference['member'], $reference['kind'])) {
        $violations[] = sprintf('Line %d reaches the %s "%s" through "self::".', $reference['line'], $reference['kind'], $reference['member']);
      }
    }

    $this->assertSame([], $violations, sprintf('%s reaches a static member against the convention in CONTRIBUTING.md.', basename($file)));
  }

  public static function dataProviderStaticMembersAreReachedThroughStatic(): array {
    return static::discoverTestFiles();
  }

  /**
   * Assert that a test helper names its action with a verb.
   *
   * A test, a data provider, a lifecycle method and an override take their
   * names from PHPUnit or from the method they override. Only the helpers a
   * class declares itself are read.
   *
   * @param class-string $class
   *   The test class to check.
   */
  #[DataProvider('dataProviderHelperNamesOpenWithVerb')]
  public function testHelperNamesOpenWithVerb(string $class): void {
    $violations = [];

    foreach (static::collectOwnHelpers(static::reflect($class)) as $method) {
      $word = static::readFirstWord($method->getName());

      if (!in_array($word, static::HELPER_VERBS, TRUE)) {
        $violations[] = sprintf('%s() opens with "%s", which is not in HELPER_VERBS.', $method->getName(), $word);
      }
    }

    $this->assertSame([], $violations, sprintf('%s names a helper against the convention in CONTRIBUTING.md.', $class));
  }

  public static function dataProviderHelperNamesOpenWithVerb(): array {
    return static::discoverTestClasses();
  }

  /**
   * Assert that a double prefixes the methods only a test calls.
   *
   * A method that runs a protected method opens with 'call', and one that
   * reads or writes protected state opens with 'test'. Any other public method
   * overrides or implements a production method, or carries an attribute that
   * registers it, as a fixture step does.
   *
   * @param class-string $class
   *   The double to check.
   */
  #[DataProvider('dataProviderDoublesPrefixTheirTestOnlyMethods')]
  public function testDoublesPrefixTheirTestOnlyMethods(string $class): void {
    $violations = [];

    foreach (static::collectOwnPublicMethods(static::reflect($class)) as $method) {
      if (preg_match('/^(?:call|test)[A-Z]/', $method->getName()) === 1 || $method->getAttributes() !== [] || static::isOverride($method)) {
        continue;
      }

      $violations[] = sprintf('%s() overrides nothing and opens with neither "call" nor "test".', $method->getName());
    }

    $this->assertSame([], $violations, sprintf('%s names a test-only method against the convention in CONTRIBUTING.md.', $class));
  }

  public static function dataProviderDoublesPrefixTheirTestOnlyMethods(): array {
    return array_filter(static::discoverFixtureClasses(), static function (array $row): bool {
      $reflection = static::reflect($row[0]);

      return $reflection->getParentClass() !== FALSE || $reflection->getTraitNames() !== [];
    });
  }

  /**
   * Assert that a double's name says what sets it apart.
   *
   * 'Test' appears only in the 'TestImplementation' suffix of a context that
   * composes the trait under test, and that name opens with the trait's name.
   * 'Testable' describes nothing a reader can use, so it is never the
   * qualifier.
   *
   * @param class-string $class
   *   The double or fixture class to check.
   */
  #[DataProvider('dataProviderDoublesAreNamedForWhatSetsThemApart')]
  public function testDoublesAreNamedForWhatSetsThemApart(string $class): void {
    $reflection = static::reflect($class);
    $name = $reflection->getShortName();
    $words = static::splitWords($name);
    $violations = [];

    foreach ($words as $index => $word) {
      $is_suffix = $word === 'Test' && $index === count($words) - 2 && $words[$index + 1] === 'Implementation';

      if ($word === 'Testable' || ($word === 'Test' && !$is_suffix)) {
        $violations[] = sprintf('"%s" qualifies the name.', $word);
      }
    }

    if (str_ends_with($name, 'TestImplementation')) {
      $traits = [];

      for ($ancestor = $reflection; $ancestor instanceof \ReflectionClass; $ancestor = $ancestor->getParentClass()) {
        $traits = array_merge($traits, array_map(static fn(\ReflectionClass $trait): string => $trait->getShortName(), $ancestor->getTraits()));
      }

      if (array_filter($traits, static fn(string $trait): bool => str_starts_with($name, $trait)) === []) {
        $violations[] = 'The name opens with no trait the class composes.';
      }
    }

    $this->assertSame([], $violations, sprintf('%s is named against the convention in CONTRIBUTING.md.', $class));
  }

  public static function dataProviderDoublesAreNamedForWhatSetsThemApart(): array {
    return static::discoverFixtureClasses();
  }

  /**
   * Assert that a unit test writes its fixtures through 'writeFixture()'.
   *
   * The helper writes into the per-test workspace 'tearDown()' removes, so a
   * fixture lasts no longer than its test, whether it passes or fails.
   *
   * @param string $file
   *   The file to check.
   */
  #[DataProvider('dataProviderUnitTestsWriteFixturesThroughWriteFixture')]
  public function testUnitTestsWriteFixturesThroughWriteFixture(string $file): void {
    $violations = array_map(static fn(array $write): string => sprintf('Line %d calls %s().', $write['line'], $write['function']), static::collectFileWrites((string) file_get_contents($file)));

    $this->assertSame([], $violations, sprintf('%s writes a file against the convention in CONTRIBUTING.md.', basename($file)));
  }

  public static function dataProviderUnitTestsWriteFixturesThroughWriteFixture(): array {
    return static::discoverUnitTestFiles();
  }

  /**
   * Assert that a unit test reflects a class in the form its argument needs.
   *
   * A class held in a variable goes through 'reflect()', which narrows a
   * string to a class string. A '::class' constant goes to
   * 'new \ReflectionClass()', which keeps the class type PHPStan reads.
   *
   * @param string $file
   *   The file to check.
   */
  #[DataProvider('dataProviderUnitTestsReflectInTheMatchingForm')]
  public function testUnitTestsReflectInTheMatchingForm(string $file): void {
    $violations = array_map(static fn(array $reflection): string => sprintf('Line %d %s.', $reflection['line'], $reflection['reason']), static::collectMisroutedReflections((string) file_get_contents($file)));

    $this->assertSame([], $violations, sprintf('%s reflects a class against the convention in CONTRIBUTING.md.', basename($file)));
  }

  public static function dataProviderUnitTestsReflectInTheMatchingForm(): array {
    return static::discoverUnitTestFiles();
  }

  /**
   * Assert which run-time 'self::' references are collected from code.
   *
   * @param string $code
   *   The PHP code to scan.
   * @param array<int, array{line: int, class: string|null, member: string, kind: string}> $expected
   *   The references, in source order.
   */
  #[DataProvider('dataProviderCollectRuntimeSelfReferences')]
  public function testCollectRuntimeSelfReferences(string $code, array $expected): void {
    $this->assertSame($expected, static::collectRuntimeSelfReferences($code));
  }

  public static function dataProviderCollectRuntimeSelfReferences(): \Iterator {
    yield 'a call, a constant and a property in a method body' => [
      "<?php\nnamespace A;\nclass B {\n  public function run() {\n    self::go(self::X, self::\$y);\n  }\n}\n",
      [
        ['line' => 5, 'class' => 'A\B', 'member' => 'go', 'kind' => 'method'],
        ['line' => 5, 'class' => 'A\B', 'member' => 'X', 'kind' => 'constant'],
        ['line' => 5, 'class' => 'A\B', 'member' => 'y', 'kind' => 'property'],
      ],
    ];
    yield 'a property default' => ["<?php\nclass B {\n  protected static \$x = self::Y;\n}\n", []];
    yield 'a constant value' => ["<?php\nclass B {\n  const X = self::Y;\n}\n", []];
    yield 'a parameter default' => ["<?php\nclass B {\n  public function run(\$x = self::Y) {}\n}\n", []];
    yield 'a closure parameter default' => ["<?php\nclass B {\n  public function run() {\n    return function (\$x = self::Y) {};\n  }\n}\n", []];
    yield 'an attribute argument' => ["<?php\nclass B {\n  #[A(self::Y)]\n  public function run() {}\n}\n", []];
    yield 'a closure body' => [
      "<?php\nclass B {\n  public function run() {\n    return static function () {\n      return self::Y;\n    };\n  }\n}\n",
      [['line' => 5, 'class' => 'B', 'member' => 'Y', 'kind' => 'constant']],
    ];
    yield 'an arrow function body' => [
      "<?php\nclass B {\n  public function run() {\n    return fn() => self::go();\n  }\n}\n",
      [['line' => 4, 'class' => 'B', 'member' => 'go', 'kind' => 'method']],
    ];
    yield 'the class name constant' => ["<?php\nclass B {\n  public function run() {\n    return self::class;\n  }\n}\n", []];
    yield 'static' => ["<?php\nclass B {\n  public function run() {\n    return static::go();\n  }\n}\n", []];
    yield 'an anonymous class body' => [
      "<?php\nclass B {\n  public function run() {\n    return new class {\n      protected \$x = self::Y;\n\n      public function go() {\n        return self::Y;\n      }\n    };\n  }\n}\n",
      [['line' => 8, 'class' => NULL, 'member' => 'Y', 'kind' => 'constant']],
    ];
    yield 'a trait' => [
      "<?php\nnamespace A;\ntrait T {\n  public function run() {\n    return self::Y;\n  }\n}\n",
      [['line' => 5, 'class' => 'A\T', 'member' => 'Y', 'kind' => 'constant']],
    ];
    yield 'an abstract method before a body' => [
      "<?php\nabstract class B {\n  abstract public function run(\$x = self::Y);\n\n  public function go() {\n    return self::Y;\n  }\n}\n",
      [['line' => 6, 'class' => 'B', 'member' => 'Y', 'kind' => 'constant']],
    ];
  }

  /**
   * Assert whether late static binding can change what a member resolves to.
   *
   * @param class-string|null $class
   *   The class the reference sits in, or NULL inside an anonymous class.
   * @param string $member
   *   The member name.
   * @param string $kind
   *   One of 'method', 'property' or 'constant'.
   * @param bool $expected
   *   Whether a subclass could redeclare the member.
   */
  #[DataProvider('dataProviderIsLateBindable')]
  public function testIsLateBindable(?string $class, string $member, string $kind, bool $expected): void {
    $this->assertSame($expected, static::isLateBindable($class, $member, $kind));
  }

  public static function dataProviderIsLateBindable(): \Iterator {
    yield 'an overridable method' => [UnitTestCase::class, 'reflect', 'method', TRUE];
    yield 'an overridable constant' => [UnitTestCase::class, 'COMPOSED_TRAIT_ROOTS', 'constant', TRUE];
    yield 'an overridable property' => [UnitTestCase::class, 'tmp', 'property', TRUE];
    yield 'an unknown member' => [UnitTestCase::class, 'absent', 'method', TRUE];
    yield 'a final method' => [Assert::class, 'fail', 'method', FALSE];
    yield 'a final constant' => [EarlyBoundMembers::class, 'FIXED', 'constant', FALSE];
    yield 'a final method of a class that is not final' => [EarlyBoundMembers::class, 'fixed', 'method', FALSE];
    yield 'a member of a final class' => [\Closure::class, 'bind', 'method', FALSE];
    yield 'an anonymous class' => [NULL, 'anything', 'method', FALSE];
  }

  /**
   * Assert which file-writing calls are collected from code.
   *
   * @param string $code
   *   The PHP code to scan.
   * @param array<int, array{line: int, function: string}> $expected
   *   The calls, in source order.
   */
  #[DataProvider('dataProviderCollectFileWrites')]
  public function testCollectFileWrites(string $code, array $expected): void {
    $this->assertSame($expected, static::collectFileWrites($code));
  }

  public static function dataProviderCollectFileWrites(): \Iterator {
    yield 'each writer' => [
      "<?php\nfile_put_contents(\$a, 'x');\n\\touch(\$a);\ncopy(\$a, \$b);\n",
      [
        ['line' => 2, 'function' => 'file_put_contents'],
        ['line' => 3, 'function' => 'touch'],
        ['line' => 4, 'function' => 'copy'],
      ],
    ];
    yield 'a method of the same name' => ["<?php\n\$a->copy(\$b);\nB::touch(\$c);\n", []];
    yield 'a declaration of the same name' => ["<?php\nfunction copy(\$a) {}\n", []];
    yield 'a mention in a comment or a string' => ["<?php\n// file_put_contents(\$a);\n\$a = 'touch(\$b)';\n", []];
    yield 'the helper' => ["<?php\n\$this->writeFixture('a.txt', 'x');\n", []];
  }

  /**
   * Assert which reflections are collected as written in the wrong form.
   *
   * @param string $code
   *   The PHP code to scan.
   * @param array<int, array{line: int, reason: string}> $expected
   *   The reflections, in source order.
   */
  #[DataProvider('dataProviderCollectMisroutedReflections')]
  public function testCollectMisroutedReflections(string $code, array $expected): void {
    $this->assertSame($expected, static::collectMisroutedReflections($code));
  }

  public static function dataProviderCollectMisroutedReflections(): \Iterator {
    yield 'a class constant reflected directly' => ["<?php\n\$a = new \\ReflectionClass(B::class);\n\$b = (new \\ReflectionClass(static::class))->getName();\n", []];
    yield 'an object class constant reflected directly' => ["<?php\n\$a = new \\ReflectionClass(\$b::class);\n", []];
    yield 'a variable reflected through the helper' => ["<?php\n\$a = static::reflect(\$b);\n", []];
    yield 'a variable reflected directly' => [
      "<?php\n\$a = new \\ReflectionClass(\$b);\n\$c = (new \\ReflectionClass(\$d))->getName();\n",
      [
        ['line' => 2, 'reason' => 'reflects a variable without reflect()'],
        ['line' => 3, 'reason' => 'reflects a variable without reflect()'],
      ],
    ];
    yield 'a class constant reflected through the helper' => [
      "<?php\n\$a = static::reflect(B::class);\n",
      [['line' => 2, 'reason' => 'reflects a ::class constant through reflect()']],
    ];
    yield 'another class of the same name' => ["<?php\n\$a = new ReflectionClassFactory(\$b);\n\$c = \$d->reflect(B::class);\n", []];
  }

  /**
   * Assert which named classes are collected from code.
   *
   * @param string $code
   *   The PHP code to scan.
   * @param array<int, string> $expected
   *   Fully qualified class names, in source order.
   */
  #[DataProvider('dataProviderCollectDeclaredClasses')]
  public function testCollectDeclaredClasses(string $code, array $expected): void {
    $this->assertSame($expected, static::collectDeclaredClasses($code));
  }

  public static function dataProviderCollectDeclaredClasses(): \Iterator {
    yield 'classes in a namespace' => ["<?php\nnamespace A\\B;\nclass C {}\nfinal class D extends C {}\n", ['A\B\C', 'A\B\D']];
    yield 'a class without a namespace' => ["<?php\nabstract class C {}\n", ['C']];
    yield 'an anonymous class and a class constant' => ["<?php\nclass C {\n  public function run() {\n    return [new class {}, C::class];\n  }\n}\n", ['C']];
    yield 'a trait, an interface and an enum' => ["<?php\ntrait T {}\ninterface I {}\nenum E {}\n", []];
  }

  /**
   * Assert how a method name splits into words.
   *
   * @param string $name
   *   The name to split.
   * @param array<int, string> $expected
   *   The words, in order.
   */
  #[DataProvider('dataProviderSplitWords')]
  public function testSplitWords(string $name, array $expected): void {
    $this->assertSame($expected, static::splitWords($name));
  }

  public static function dataProviderSplitWords(): \Iterator {
    yield 'a verb and a noun' => ['collectStepTexts', ['collect', 'Step', 'Texts']];
    yield 'a class name' => ['TestmodeTraitTestImplementation', ['Testmode', 'Trait', 'Test', 'Implementation']];
    yield 'a single word' => ['reflect', ['reflect']];
  }

  public function testCollectOwnHelpers(): void {
    $names = array_map(static fn(\ReflectionMethod $method): string => $method->getName(), static::collectOwnHelpers(static::reflect(static::loadConventionSubject())));
    sort($names);

    $this->assertSame(['attributed', 'bare', 'callRun', 'createThing', 'thing'], $names);
  }

  public function testCollectOwnPublicMethods(): void {
    $names = array_map(static fn(\ReflectionMethod $method): string => $method->getName(), static::collectOwnPublicMethods(static::reflect(static::loadConventionSubject())));
    sort($names);

    $this->assertSame(['attributed', 'bare', 'callRun', 'count', 'dataProviderSomething', 'redeclared', 'testSomething'], $names);
  }

  /**
   * Assert whether a method overrides or implements one declared elsewhere.
   *
   * @param string $method
   *   A method of the convention subject.
   * @param bool $expected
   *   Whether the method overrides or implements another.
   */
  #[DataProvider('dataProviderIsOverride')]
  public function testIsOverride(string $method, bool $expected): void {
    $this->assertSame($expected, static::isOverride(static::reflect(static::loadConventionSubject())->getMethod($method)));
  }

  public static function dataProviderIsOverride(): \Iterator {
    yield 'a parent method' => ['inherited', TRUE];
    yield 'an interface method' => ['count', TRUE];
    yield 'a trait method' => ['redeclared', TRUE];
    yield 'a method of its own' => ['createThing', FALSE];
  }

  /**
   * Load the class holding 1 method of each kind the checks tell apart.
   *
   * @return string
   *   The fully qualified class name.
   */
  protected static function loadConventionSubject(): string {
    require_once __DIR__ . '/../fixtures/conventions/ConventionSubject.php';

    return 'DrevOps\BehatSteps\Tests\Fixtures\Conventions\ConventionSubject';
  }

  /**
   * Return every PHP file under `tests/phpunit/src`, keyed by its path there.
   *
   * Each file's own class is loaded, so a double declared beside it can be
   * reflected by name.
   *
   * @return array<string, array{string}>
   *   Absolute file paths, as data provider rows.
   */
  protected static function discoverTestFiles(): array {
    $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__, \FilesystemIterator::SKIP_DOTS));
    $paths = [];

    foreach ($files as $file) {
      if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
        continue;
      }

      static::loadFileClass($file->getPathname());
      $paths[substr($file->getPathname(), strlen(__DIR__) + 1)] = [$file->getPathname()];
    }

    ksort($paths);

    return $paths;
  }

  /**
   * Return the files of the unit suite, which runs on 'UnitTestCase'.
   *
   * @return array<string, array{string}>
   *   Absolute file paths, as data provider rows.
   */
  protected static function discoverUnitTestFiles(): array {
    return array_filter(static::discoverTestFiles(), static fn(string $relative): bool => !str_starts_with($relative, 'Kernel' . DIRECTORY_SEPARATOR) && $relative !== 'UnitTestCase.php', ARRAY_FILTER_USE_KEY);
  }

  /**
   * Return every class under `tests/phpunit/src` that is not a test.
   *
   * @return array<string, array{class-string}>
   *   Fully qualified class names, as data provider rows.
   */
  protected static function discoverFixtureClasses(): array {
    $classes = [];

    foreach (static::discoverTestFiles() as [$file]) {
      foreach (static::collectDeclaredClasses((string) file_get_contents($file)) as $class) {
        if (class_exists($class, FALSE) && !is_subclass_of($class, TestCase::class)) {
          $classes[$class] = [$class];
        }
      }
    }

    ksort($classes);

    return $classes;
  }

  /**
   * Load the class, interface or trait a file is named after.
   *
   * Loading it declares every other class the file holds.
   *
   * @param string $file
   *   The absolute path to a file under `tests/phpunit/src`.
   */
  protected static function loadFileClass(string $file): void {
    $name = __NAMESPACE__ . '\\' . str_replace(DIRECTORY_SEPARATOR, '\\', substr($file, strlen(__DIR__) + 1, -strlen('.php')));

    if (!class_exists($name) && !interface_exists($name) && !trait_exists($name)) {
      throw new \RuntimeException(sprintf('%s does not declare %s.', $file, $name));
    }
  }

  /**
   * Collect the run-time 'self::' references in PHP code.
   *
   * A reference is run-time when the innermost class or function scope around
   * it is a function body, outside the function's header. A property default,
   * a constant value, a parameter default and an attribute argument are
   * constant expressions, where PHP rejects 'static::'.
   *
   * @param string $code
   *   The PHP code, opening tag included.
   *
   * @return array<int, array{line: int, class: string|null, member: string, kind: string}>
   *   1 entry per reference, in source order. The class is the one the
   *   reference sits in, or NULL inside an anonymous class.
   */
  protected static function collectRuntimeSelfReferences(string $code): array {
    $tokens = static::tokenizeSignificant($code);
    $namespace = '';
    $scopes = [];
    $opening = NULL;
    $in_header = FALSE;
    $references = [];

    foreach ($tokens as $index => $token) {
      $id = is_array($token) ? $token[0] : $token;
      $previous = $index > 0 ? (is_array($tokens[$index - 1]) ? $tokens[$index - 1][0] : $tokens[$index - 1]) : NULL;
      $next = $tokens[$index + 1] ?? NULL;

      if ($id === T_NAMESPACE && is_array($next)) {
        $namespace = $next[1];
      }
      elseif ($id === T_FUNCTION) {
        $opening = ['function', NULL];
        $in_header = TRUE;
      }
      elseif ($id === T_FN) {
        $in_header = TRUE;
      }
      elseif ($id === T_DOUBLE_ARROW && $in_header && $opening === NULL) {
        $in_header = FALSE;
      }
      elseif (in_array($id, [T_CLASS, T_TRAIT, T_INTERFACE, T_ENUM], TRUE) && $previous !== T_DOUBLE_COLON) {
        $name = $previous !== T_NEW && is_array($next) && $next[0] === T_STRING ? ltrim($namespace . '\\' . $next[1], '\\') : NULL;
        $opening = ['class', $name];
      }
      elseif ($id === ';' && $opening !== NULL && $opening[0] === 'function') {
        $opening = NULL;
        $in_header = FALSE;
      }
      elseif ($id === '{') {
        $scopes[] = $opening ?? ['other', NULL];
        $opening = NULL;
        $in_header = FALSE;
      }
      elseif ($id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES) {
        $scopes[] = ['other', NULL];
      }
      elseif ($id === '}') {
        array_pop($scopes);
      }
      elseif ($id === T_STRING && strtolower($token[1]) === 'self' && is_array($next) && $next[0] === T_DOUBLE_COLON && !$in_header) {
        $reference = static::readSelfReference($tokens, $index, $scopes);

        if ($reference !== NULL) {
          $references[] = $reference;
        }
      }
    }

    return $references;
  }

  /**
   * Read the member a 'self::' token reaches, when it reaches one at run time.
   *
   * @param array<int, array{int, string, int}|string> $tokens
   *   Tokens without whitespace and comments.
   * @param int $index
   *   The index of the 'self' token.
   * @param array<int, array{string, string|null}> $scopes
   *   The open scopes, innermost last, each a type and a class name.
   *
   * @return array{line: int, class: string|null, member: string, kind: string}|null
   *   The reference, or NULL when it is not one.
   */
  protected static function readSelfReference(array $tokens, int $index, array $scopes): ?array {
    $class = NULL;
    $in_function = NULL;

    foreach (array_reverse($scopes) as [$type, $name]) {
      if ($type === 'function' && $in_function === NULL) {
        $in_function = TRUE;
      }

      if ($type === 'class') {
        $in_function ??= FALSE;
        $class = $name;
        break;
      }
    }

    $member = $tokens[$index + 2] ?? NULL;

    if ($in_function !== TRUE || !is_array($member) || !in_array($member[0], [T_STRING, T_VARIABLE], TRUE)) {
      return NULL;
    }

    $kind = match (TRUE) {
      $member[0] === T_VARIABLE => 'property',
      ($tokens[$index + 3] ?? NULL) === '(' => 'method',
      default => 'constant',
    };

    $line = is_array($tokens[$index]) ? $tokens[$index][2] : 0;

    return ['line' => $line, 'class' => $class, 'member' => ltrim($member[1], '$'), 'kind' => $kind];
  }

  /**
   * Determine whether late static binding can change what a member resolves to.
   *
   * It cannot for a member of a final or anonymous class, a final member or a
   * private member, so those are reached through 'self::'.
   *
   * @param string|null $class
   *   The class the reference sits in, or NULL inside an anonymous class.
   * @param string $member
   *   The member name.
   * @param string $kind
   *   One of 'method', 'property' or 'constant'.
   */
  protected static function isLateBindable(?string $class, string $member, string $kind): bool {
    if ($class === NULL) {
      return FALSE;
    }

    $reflection = static::reflect($class);

    if ($reflection->isFinal()) {
      return FALSE;
    }

    if ($kind === 'method' && $reflection->hasMethod($member)) {
      $method = $reflection->getMethod($member);

      return !$method->isFinal() && !$method->isPrivate();
    }

    if ($kind === 'property' && $reflection->hasProperty($member)) {
      return !$reflection->getProperty($member)->isPrivate();
    }

    $constant = $kind === 'constant' ? $reflection->getReflectionConstant($member) : FALSE;

    return !$constant instanceof \ReflectionClassConstant || (!$constant->isFinal() && !$constant->isPrivate());
  }

  /**
   * Collect the helpers a test class declares itself.
   *
   * @param \ReflectionClass<object> $reflection
   *   The test class.
   *
   * @return array<int, \ReflectionMethod>
   *   Methods declared in the class's own file that are not a test, a data
   *   provider, a lifecycle method, a magic method or an override.
   */
  protected static function collectOwnHelpers(\ReflectionClass $reflection): array {
    $helpers = [];

    foreach ($reflection->getMethods() as $method) {
      $name = $method->getName();

      if (!static::isDeclaredIn($method, $reflection)) {
        continue;
      }

      if (str_starts_with($name, 'test') || str_starts_with($name, 'dataProvider') || str_starts_with($name, '__') || in_array($name, static::LIFECYCLE_METHODS, TRUE) || static::isOverride($method)) {
        continue;
      }

      $helpers[] = $method;
    }

    return $helpers;
  }

  /**
   * Collect the public methods a class declares itself.
   *
   * @param \ReflectionClass<object> $reflection
   *   The class.
   *
   * @return array<int, \ReflectionMethod>
   *   Public methods declared in the class's own file, the constructor and
   *   magic methods aside.
   */
  protected static function collectOwnPublicMethods(\ReflectionClass $reflection): array {
    return array_values(array_filter($reflection->getMethods(\ReflectionMethod::IS_PUBLIC), static fn(\ReflectionMethod $method): bool => static::isDeclaredIn($method, $reflection) && !str_starts_with($method->getName(), '__')));
  }

  /**
   * Determine whether a method's source sits inside a class body.
   *
   * A method a trait supplies reports the composing class as its declarer. A
   * trait declared in the same file shares its file name too, so the lines
   * tell them apart.
   *
   * @param \ReflectionMethod $method
   *   The method.
   * @param \ReflectionClass<object> $reflection
   *   The class.
   */
  protected static function isDeclaredIn(\ReflectionMethod $method, \ReflectionClass $reflection): bool {
    return $method->getDeclaringClass()->getName() === $reflection->getName() && $method->getFileName() === $reflection->getFileName() && $method->getStartLine() >= $reflection->getStartLine() && $method->getEndLine() <= $reflection->getEndLine();
  }

  /**
   * Determine whether a method overrides or implements one declared elsewhere.
   *
   * @param \ReflectionMethod $method
   *   The method.
   */
  protected static function isOverride(\ReflectionMethod $method): bool {
    $class = $method->getDeclaringClass();
    $name = $method->getName();
    $parent = $class->getParentClass();

    if ($parent instanceof \ReflectionClass && $parent->hasMethod($name)) {
      return TRUE;
    }

    foreach ($class->getInterfaces() as $interface) {
      if ($interface->hasMethod($name)) {
        return TRUE;
      }
    }

    foreach ($class->getTraits() as $trait) {
      if ($trait->hasMethod($name)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Collect the calls in PHP code that write a file directly.
   *
   * @param string $code
   *   The PHP code, opening tag included.
   *
   * @return array<int, array{line: int, function: string}>
   *   1 entry per call, in source order.
   */
  protected static function collectFileWrites(string $code): array {
    $tokens = static::tokenizeSignificant($code);
    $writes = [];

    foreach ($tokens as $index => $token) {
      if (!is_array($token) || !in_array($token[0], [T_STRING, T_NAME_FULLY_QUALIFIED], TRUE)) {
        continue;
      }

      $function = ltrim($token[1], '\\');
      $previous = $index > 0 && is_array($tokens[$index - 1]) ? $tokens[$index - 1][0] : NULL;

      if (in_array($function, static::FILE_WRITERS, TRUE) && ($tokens[$index + 1] ?? NULL) === '(' && !in_array($previous, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW], TRUE)) {
        $writes[] = ['line' => $token[2], 'function' => $function];
      }
    }

    return $writes;
  }

  /**
   * Collect the reflections in PHP code written in the wrong form.
   *
   * A variable passed to 'new \ReflectionClass()' skips the narrowing
   * 'reflect()' does. A '::class' constant passed to 'reflect()' loses the
   * class type 'new \ReflectionClass()' keeps.
   *
   * @param string $code
   *   The PHP code, opening tag included.
   *
   * @return array<int, array{line: int, reason: string}>
   *   1 entry per reflection, in source order.
   */
  protected static function collectMisroutedReflections(string $code): array {
    $tokens = static::tokenizeSignificant($code);
    $misrouted = [];

    foreach ($tokens as $index => $token) {
      if (!is_array($token)) {
        continue;
      }

      $is_constructor = $token[0] === T_NEW && is_array($tokens[$index + 1] ?? NULL) && ltrim($tokens[$index + 1][1], '\\') === 'ReflectionClass' && ($tokens[$index + 2] ?? NULL) === '(';
      $is_helper = $token[0] === T_STATIC && is_array($tokens[$index + 1] ?? NULL) && $tokens[$index + 1][0] === T_DOUBLE_COLON && is_array($tokens[$index + 2] ?? NULL) && $tokens[$index + 2][1] === 'reflect' && ($tokens[$index + 3] ?? NULL) === '(';

      if (!$is_constructor && !$is_helper) {
        continue;
      }

      $is_constant = static::isClassConstantArgument($tokens, $index + ($is_constructor ? 2 : 3));

      if ($is_constructor && !$is_constant) {
        $misrouted[] = ['line' => $token[2], 'reason' => 'reflects a variable without reflect()'];
      }

      if ($is_helper && $is_constant) {
        $misrouted[] = ['line' => $token[2], 'reason' => 'reflects a ::class constant through reflect()'];
      }
    }

    return $misrouted;
  }

  /**
   * Determine whether a call's single argument ends in '::class'.
   *
   * @param array<int, array{int, string, int}|string> $tokens
   *   Tokens without whitespace and comments.
   * @param int $open
   *   The index of the call's opening parenthesis.
   */
  protected static function isClassConstantArgument(array $tokens, int $open): bool {
    $depth = 0;
    $count = count($tokens);

    for ($index = $open; $index < $count; $index++) {
      $depth += match ($tokens[$index]) {
        '(' => 1,
        ')' => -1,
        default => 0,
      };

      if ($depth === 0) {
        $last = $tokens[$index - 1];
        $separator = $tokens[$index - 2] ?? NULL;

        return is_array($last) && $last[0] === T_CLASS && is_array($separator) && $separator[0] === T_DOUBLE_COLON;
      }
    }

    return FALSE;
  }

  /**
   * Collect the named classes declared in PHP code.
   *
   * @param string $code
   *   The PHP code, opening tag included.
   *
   * @return array<int, string>
   *   Fully qualified class names, in source order. Anonymous classes,
   *   traits, interfaces and enums are left out.
   */
  protected static function collectDeclaredClasses(string $code): array {
    $tokens = static::tokenizeSignificant($code);
    $namespace = '';
    $classes = [];

    foreach ($tokens as $index => $token) {
      if (!is_array($token)) {
        continue;
      }

      $next = $tokens[$index + 1] ?? NULL;
      $previous = $index > 0 && is_array($tokens[$index - 1]) ? $tokens[$index - 1][0] : NULL;

      if ($token[0] === T_NAMESPACE && is_array($next)) {
        $namespace = $next[1];
      }

      if ($token[0] === T_CLASS && !in_array($previous, [T_DOUBLE_COLON, T_NEW], TRUE) && is_array($next) && $next[0] === T_STRING) {
        $classes[] = ltrim($namespace . '\\' . $next[1], '\\');
      }
    }

    return $classes;
  }

  /**
   * Split a camel-cased name into its words.
   *
   * @param string $name
   *   The name to split.
   *
   * @return array<int, string>
   *   The words, in order.
   */
  protected static function splitWords(string $name): array {
    $words = preg_split('/(?=[A-Z])/', $name, -1, PREG_SPLIT_NO_EMPTY);

    return $words === FALSE ? [] : $words;
  }

  /**
   * Read the first word of a camel-cased name.
   *
   * @param string $name
   *   The name to read.
   */
  protected static function readFirstWord(string $name): string {
    return (string) (static::splitWords($name)[0] ?? '');
  }

}
