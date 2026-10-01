<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use Behat\Step\Then;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Asserts that trait method names follow the library's naming conventions.
 *
 * Traits are mixed into a single consumer context, so an unprefixed method
 * name can collide with a method of the same name from another trait.
 *
 * The remaining conventions keep one shape per idea, so that a consumer can
 * derive a name rather than look it up. CONTRIBUTING.md states them.
 */
#[CoversNothing]
class TraitMethodNamingTest extends UnitTestCase {

  /**
   * Words that open a qualifier narrowing the subject of an assertion.
   *
   * `To` is absent: in `SentToAddress` it completes the verb rather than
   * narrowing the subject.
   */
  protected const QUALIFIERS = ['At', 'By', 'Containing', 'For', 'From', 'In', 'Of', 'On', 'With', 'Within', 'Without'];

  /**
   * Words that open the predicate of an assertion.
   */
  protected const PREDICATES = ['Contains', 'Equals', 'Exist', 'Exists', 'Matches', 'Not'];

  /**
   * Assert that every method a trait declares carries the trait's prefix.
   *
   * @param class-string $trait
   *   The trait to check.
   * @param string $file
   *   The absolute path to the file declaring the trait.
   */
  #[DataProvider('dataProviderMethodsArePrefixed')]
  public function testMethodsArePrefixed(string $trait, string $file): void {
    $reflection = new \ReflectionClass($trait);
    $prefix = self::traitPrefix($reflection->getShortName());

    $violations = [];
    foreach (self::traitOwnMethodNames($trait, $file) as $name) {
      if (self::hasPrefix($name, $prefix)) {
        continue;
      }

      $violations[] = $name;
    }

    $this->assertSame([], $violations, sprintf('Methods in %s must be prefixed with "%s".', $reflection->getShortName(), $prefix));
  }

  public static function dataProviderMethodsArePrefixed(): array {
    return static::discoverTraitFiles();
  }

  /**
   * Assert that a negative name reads `Assert<Subject>Not<Predicate>`.
   *
   * `Not` is the only negation particle, so neither the determiner `No` nor a
   * `DoesNot` or `DoNot` auxiliary opens a negated word. A negative name is
   * then its positive counterpart with `Not` inserted and nothing else
   * changed.
   *
   * @param class-string $trait
   *   The trait to check.
   * @param string $file
   *   The absolute path to the file declaring the trait.
   */
  #[DataProvider('dataProviderNegationSpelledNot')]
  public function testNegationSpelledNot(string $trait, string $file): void {
    $violations = array_values(array_filter(self::traitOwnMethodNames($trait, $file), fn(string $name): bool => preg_match('/(?:No|DoesNot|DoNot)[A-Z]/', $name) === 1));

    $this->assertSame([], $violations, 'Negate with a bare "Not" placed before the predicate: "userAssertNotHasRoles", not "userAssertHasNoRoles" or "userAssertDoesNotHaveRoles".');
  }

  public static function dataProviderNegationSpelledNot(): array {
    return static::discoverTraitFiles();
  }

  /**
   * Assert that a qualifier follows the predicate it narrows.
   *
   * The subject comes first, so `Not` lands directly after it: a qualifier
   * placed before the predicate takes that slot, as in
   * `cookieAssertWithNameNotExists`. `Not` is also never followed by a
   * qualifier, which leaves the negation with no predicate to negate.
   *
   * @param class-string $trait
   *   The trait to check.
   * @param string $file
   *   The absolute path to the file declaring the trait.
   */
  #[DataProvider('dataProviderQualifiersFollowPredicate')]
  public function testQualifiersFollowPredicate(string $trait, string $file): void {
    $qualifiers = implode('|', static::QUALIFIERS);
    $predicates = implode('|', static::PREDICATES);
    $pattern = sprintf('/Assert(?:(?!%2$s)[A-Z][a-z0-9]*)*?(?:%1$s)(?=[A-Z])[A-Za-z0-9]*?(?:%2$s)(?![a-z])|Assert[A-Za-z0-9]*?Not(?:%1$s)(?=[A-Z])/', $qualifiers, $predicates);

    $violations = array_values(array_filter(self::traitOwnMethodNames($trait, $file), fn(string $name): bool => preg_match($pattern, $name) === 1));

    $this->assertSame([], $violations, 'Place a qualifier after the predicate so "Not" sits directly after the subject: "cookieAssertNotExistsWithName", not "cookieAssertWithNameNotExists", and "tableAssertLinkNotExistsInRow", not "tableAssertLinkNotInRow".');
  }

  public static function dataProviderQualifiersFollowPredicate(): array {
    return static::discoverTraitFiles();
  }

  /**
   * Assert that a negative step is named as its positive with `Not` added.
   *
   * A pair of steps whose text differs only by "should" and "should not"
   * reaches 2 methods whose names differ only by `Not`.
   *
   * @param class-string $trait
   *   The trait to check.
   * @param string $file
   *   The absolute path to the file declaring the trait.
   */
  #[DataProvider('dataProviderNegativeMirrorsPositive')]
  public function testNegativeMirrorsPositive(string $trait, string $file): void {
    $names = [];
    foreach (self::traitOwnMethods($trait, $file) as $method) {
      foreach ($method->getAttributes(Then::class) as $attribute) {
        $names[(string) $attribute->newInstance()->getPattern()] = $method->getName();
      }
    }

    $violations = [];
    foreach ($names as $step => $name) {
      $positive = $names[preg_replace('/ should not /', ' should ', $step, 1)] ?? NULL;

      if (!str_contains($step, ' should not ') || $positive === NULL || self::insertsNot($positive, $name)) {
        continue;
      }

      $violations[] = sprintf('%s() for %s()', $name, $positive);
    }

    $this->assertSame([], $violations, 'Name a negative step as its positive with "Not" inserted and nothing else changed: "elementAssertNotVisible" for "elementAssertVisible".');
  }

  public static function dataProviderNegativeMirrorsPositive(): array {
    return static::discoverTraitFiles();
  }

  /**
   * Assert that an assertion name carries no copula.
   *
   * `Assert` already states that the subject is something, so a further `Is`
   * only moves the negation particle out of its one slot.
   *
   * @param class-string $trait
   *   The trait to check.
   * @param string $file
   *   The absolute path to the file declaring the trait.
   */
  #[DataProvider('dataProviderAssertionsCarryNoCopula')]
  public function testAssertionsCarryNoCopula(string $trait, string $file): void {
    $violations = array_values(array_filter(self::traitOwnMethodNames($trait, $file), fn(string $name): bool => preg_match('/Assert[A-Za-z0-9]*Is[A-Z]/', $name) === 1));

    $this->assertSame([], $violations, 'Drop the "Is" copula from assertion names: "elementAssertVisible" and "elementAssertNotVisible", not "elementAssertIsVisible" and "elementAssertIsNotVisible".');
  }

  public static function dataProviderAssertionsCarryNoCopula(): array {
    return static::discoverTraitFiles();
  }

  /**
   * Assert that `Has` names something the subject holds.
   *
   * A value compared against reads `Equals` or `Contains`, so in an assertion
   * `Has` is followed by neither `Content`, `Value` or `Text`, nor by a
   * `With` or `Containing` value qualifier.
   *
   * @param class-string $trait
   *   The trait to check.
   * @param string $file
   *   The absolute path to the file declaring the trait.
   */
  #[DataProvider('dataProviderHasNamesWhatSubjectHolds')]
  public function testHasNamesWhatSubjectHolds(string $trait, string $file): void {
    $violations = array_values(array_filter(self::traitOwnMethodNames($trait, $file), fn(string $name): bool => preg_match('/Assert[A-Za-z0-9]*Has(?:(?:Content|Value|Text)(?![a-z])|[A-Z][A-Za-z0-9]*?(?:With|Containing)(?=[A-Z]))/', $name) === 1));

    $this->assertSame([], $violations, 'Keep "Has" for something the subject holds, and compare a value with "Equals" or "Contains": "stateAssertValueEquals", not "stateAssertHasValue", and "elementAssertCssPropertyEquals", not "elementAssertHasCssPropertyWithValue".');
  }

  public static function dataProviderHasNamesWhatSubjectHolds(): array {
    return static::discoverTraitFiles();
  }

  /**
   * Assert that names spell normalisation the American way.
   *
   * @param class-string $trait
   *   The trait to check.
   * @param string $file
   *   The absolute path to the file declaring the trait.
   */
  #[DataProvider('dataProviderSpellingIsAmerican')]
  public function testSpellingIsAmerican(string $trait, string $file): void {
    $violations = array_values(array_filter(self::traitOwnMethodNames($trait, $file), fn(string $name): bool => stripos($name, 'normalise') !== FALSE));

    $this->assertSame([], $violations, 'Spell it "Normalize", not "Normalise".');
  }

  public static function dataProviderSpellingIsAmerican(): array {
    return static::discoverTraitFiles();
  }

  /**
   * Assert that a `Find` method declares a nullable return type.
   *
   * `Find` returns NULL when nothing matches, so a caller checks the result
   * instead of catching an exception.
   *
   * @param class-string $trait
   *   The trait to check.
   * @param string $file
   *   The absolute path to the file declaring the trait.
   */
  #[DataProvider('dataProviderFindReturnsNullable')]
  public function testFindReturnsNullable(string $trait, string $file): void {
    $violations = [];
    foreach (self::traitOwnMethodsWithVerb($trait, $file, 'Find') as $method) {
      $type = $method->getReturnType();

      if ($type instanceof \ReflectionType && $type->allowsNull()) {
        continue;
      }

      $violations[] = self::describeReturnType($method);
    }

    $this->assertSame([], $violations, 'A "Find" method returns NULL when nothing matches, so its return type allows NULL. Rename a method that throws on a miss to "Get": "tableGet", not "tableFind".');
  }

  public static function dataProviderFindReturnsNullable(): array {
    return static::discoverTraitFiles();
  }

  /**
   * Assert that a `Get` method declares a return type without NULL.
   *
   * `Get` throws when nothing matches, so a caller uses the result without a
   * NULL check. `mixed` declares no type to check, so it is not reported.
   *
   * @param class-string $trait
   *   The trait to check.
   * @param string $file
   *   The absolute path to the file declaring the trait.
   */
  #[DataProvider('dataProviderGetNeverReturnsNull')]
  public function testGetNeverReturnsNull(string $trait, string $file): void {
    $violations = [];
    foreach (self::traitOwnMethodsWithVerb($trait, $file, 'Get') as $method) {
      $type = $method->getReturnType();

      if ($type instanceof \ReflectionType && (!$type->allowsNull() || ($type instanceof \ReflectionNamedType && $type->getName() === 'mixed'))) {
        continue;
      }

      $violations[] = self::describeReturnType($method);
    }

    $this->assertSame([], $violations, 'A "Get" method throws when nothing matches and never returns NULL, so its return type excludes NULL. Rename a method that returns NULL on a miss to "Find": "cookieFindByName", not "cookieGetByName".');
  }

  public static function dataProviderGetNeverReturnsNull(): array {
    return static::discoverTraitFiles();
  }

  /**
   * Assert that a `Load` method returns an array or nothing.
   *
   * `Load` loads a set, or loads data into the trait's own state. A lookup
   * for 1 item is a `Find` or a `Get`, so its name states what a miss does.
   *
   * @param class-string $trait
   *   The trait to check.
   * @param string $file
   *   The absolute path to the file declaring the trait.
   */
  #[DataProvider('dataProviderLoadReturnsSet')]
  public function testLoadReturnsSet(string $trait, string $file): void {
    $violations = [];
    foreach (self::traitOwnMethodsWithVerb($trait, $file, 'Load') as $method) {
      $type = $method->getReturnType();

      if ($type instanceof \ReflectionNamedType && in_array($type->getName(), ['array', 'void'], TRUE) && !$type->allowsNull()) {
        continue;
      }

      $violations[] = self::describeReturnType($method);
    }

    $this->assertSame([], $violations, 'A "Load" method loads a set or loads into the trait\'s own state, so it returns array or void. Name a lookup for 1 item "Find" when a miss returns NULL, or "Get" when a miss throws: "blockFindByLabel", not "blockLoadByLabel".');
  }

  public static function dataProviderLoadReturnsSet(): array {
    return static::discoverTraitFiles();
  }

  /**
   * Pair every trait under `src/` with the file that declares it.
   *
   * @return array<string, array{string, string}>
   *   Trait name and absolute file path, keyed by the path relative to `src/`.
   */
  protected static function discoverTraitFiles(): array {
    $root = realpath(__DIR__ . '/../../../src');
    $files = [];

    $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator((string) $root));
    foreach ($iterator as $file) {
      if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
        continue;
      }

      $path = (string) $file->getRealPath();
      $relative = substr($path, strlen((string) $root) + 1);

      if (!static::isComposedTraitPath($relative)) {
        continue;
      }

      $trait = 'DrevOps\\BehatSteps\\' . str_replace(DIRECTORY_SEPARATOR, '\\', substr($relative, 0, -strlen('.php')));

      $files[$relative] = [$trait, $path];
    }

    ksort($files);

    return $files;
  }

  /**
   * Collect the methods a trait declares in its own file.
   *
   * @param class-string $trait
   *   The trait to read.
   * @param string $file
   *   The absolute path to the file declaring the trait.
   *
   * @return array<int, \ReflectionMethod>
   *   The methods.
   */
  protected static function traitOwnMethods(string $trait, string $file): array {
    $methods = (new \ReflectionClass($trait))->getMethods();

    $own = [];
    foreach ($methods as $method) {
      // A trait that composes another trait reports the composed methods too,
      // so only the methods declared in this file are the trait's own.
      if (realpath((string) $method->getFileName()) !== $file) {
        continue;
      }

      $own[] = $method;
    }

    return $own;
  }

  /**
   * Collect the names of the methods a trait declares in its own file.
   *
   * @param class-string $trait
   *   The trait to read.
   * @param string $file
   *   The absolute path to the file declaring the trait.
   *
   * @return array<int, string>
   *   The method names.
   */
  protected static function traitOwnMethodNames(string $trait, string $file): array {
    return array_map(static fn(\ReflectionMethod $method): string => $method->getName(), self::traitOwnMethods($trait, $file));
  }

  /**
   * Collect the methods a trait declares whose name opens with a verb.
   *
   * The verb is the word right after the trait prefix, so 'tableGetRows'
   * opens with 'Get' and a name such as 'tableGetter' opens with no verb.
   *
   * @param class-string $trait
   *   The trait to read.
   * @param string $file
   *   The absolute path to the file declaring the trait.
   * @param string $verb
   *   The verb, capitalized as it appears in a method name.
   *
   * @return array<int, \ReflectionMethod>
   *   The methods.
   */
  protected static function traitOwnMethodsWithVerb(string $trait, string $file, string $verb): array {
    $prefix = self::traitPrefix((new \ReflectionClass($trait))->getShortName());

    $matched = [];
    foreach (self::traitOwnMethods($trait, $file) as $method) {
      $name = $method->getName();

      if (!self::hasPrefix($name, $prefix) || preg_match('/^' . $verb . '(?![a-z])/', substr($name, strlen($prefix))) !== 1) {
        continue;
      }

      $matched[] = $method;
    }

    return $matched;
  }

  /**
   * Describe a method by its name and declared return type.
   *
   * @param \ReflectionMethod $method
   *   The method to describe.
   *
   * @return string
   *   The name followed by the declared return type.
   */
  protected static function describeReturnType(\ReflectionMethod $method): string {
    $type = $method->getReturnType();

    return sprintf('%s(): %s', $method->getName(), $type instanceof \ReflectionType ? (string) $type : 'no return type');
  }

  /**
   * Derive the method prefix a trait requires from its short name.
   */
  protected static function traitPrefix(string $short_name): string {
    return lcfirst(substr($short_name, 0, -strlen('Trait')));
  }

  /**
   * Check that a method name opens with the prefix as a whole word.
   *
   * The character after the prefix must be uppercase so that a name which
   * merely starts with the same letters, such as 'waiting' against 'wait',
   * is not accepted.
   */
  protected static function hasPrefix(string $method, string $prefix): bool {
    if ($method === $prefix) {
      return TRUE;
    }

    return str_starts_with($method, $prefix) && ctype_upper($method[strlen($prefix)]);
  }

  /**
   * Check that a negative name is the positive name with one `Not` added.
   */
  protected static function insertsNot(string $positive, string $negative): bool {
    $offset = 0;

    while (($position = strpos($negative, 'Not', $offset)) !== FALSE) {
      if (substr($negative, 0, $position) . substr($negative, $position + strlen('Not')) === $positive) {
        return TRUE;
      }

      $offset = $position + 1;
    }

    return FALSE;
  }

}
