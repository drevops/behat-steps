<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
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
   * Exceptions that report a failed assertion rather than an error.
   */
  protected const ASSERTION_EXCEPTIONS = ['AssertionException', 'ElementNotFoundException', 'ExpectationException'];

  /**
   * Verbs the published helpers name their action with.
   */
  protected const VERBS = [
    'Apply',
    'Assert',
    'Assess',
    'Assign',
    'Attach',
    'Build',
    'Create',
    'Decode',
    'Delete',
    'Disable',
    'Enable',
    'Execute',
    'Exists',
    'Expand',
    'Extract',
    'Fetch',
    'Find',
    'Generate',
    'Get',
    'Has',
    'Is',
    'Load',
    'Login',
    'Logout',
    'Normalize',
    'Open',
    'Parse',
    'Process',
    'Query',
    'Read',
    'Register',
    'Resize',
    'Resolve',
    'Run',
    'Set',
    'Substitute',
    'Transpose',
    'Validate',
    'Visit',
    'Wait',
  ];

  /**
   * Verbs that create, delete or load an entity.
   */
  protected const LIFECYCLE_VERBS = ['Create', 'Delete', 'Load'];

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
   * Assert that an assertion carries `Assert` directly after the prefix.
   *
   * A `Then` step is an assertion, and so is a helper whose docblock opens
   * with "Assert". A hook is named for its event even when it asserts.
   *
   * `Assert` appears nowhere else in a name, so the shape is always
   * `<prefix>Assert<Subject><Predicate>`.
   *
   * @param class-string $trait
   *   The trait to check.
   * @param string $file
   *   The absolute path to the file declaring the trait.
   */
  #[DataProvider('dataProviderAssertionsOpenWithAssert')]
  public function testAssertionsOpenWithAssert(string $trait, string $file): void {
    $prefix = self::traitPrefix((new \ReflectionClass($trait))->getShortName());

    $violations = [];
    foreach (self::traitOwnMethods($trait, $file) as $method) {
      $name = $method->getName();
      $is_assertion = $method->getAttributes(Then::class) !== [] || (!self::isRegistered($method) && str_starts_with(self::docblockSummary($method), 'Assert'));

      if ((!$is_assertion && preg_match('/Assert(?![a-z])/', $name) !== 1) || str_starts_with($name, $prefix . 'Assert')) {
        continue;
      }

      $violations[] = $name;
    }

    $this->assertSame([], $violations, 'Name an assertion "<prefix>Assert<Subject><Predicate>", with "Assert" nowhere else: "cookieAssertExists", not "cookieExists".');
  }

  public static function dataProviderAssertionsOpenWithAssert(): array {
    return static::discoverTraitFiles();
  }

  /**
   * Assert that `Assert` is followed by what the assertion asserts.
   *
   * @param class-string $trait
   *   The trait to check.
   * @param string $file
   *   The absolute path to the file declaring the trait.
   */
  #[DataProvider('dataProviderAssertionsNameWhatTheyAssert')]
  public function testAssertionsNameWhatTheyAssert(string $trait, string $file): void {
    $violations = array_values(array_filter(self::traitOwnMethodNames($trait, $file), static fn(string $name): bool => preg_match('/Assert(?:Not)?$/', $name) === 1));

    $this->assertSame([], $violations, 'Follow "Assert" with the subject or the predicate it asserts: "messageAssertExistsOfType", not "messageAssert".');
  }

  public static function dataProviderAssertionsNameWhatTheyAssert(): array {
    return static::discoverTraitFiles();
  }

  /**
   * Assert that an `Assert` method fails with an assertion exception.
   *
   * A method that throws only `\RuntimeException` guards an argument or a
   * precondition, so it is named for what it does rather than as an
   * assertion.
   *
   * @param class-string $trait
   *   The trait to check.
   * @param string $file
   *   The absolute path to the file declaring the trait.
   */
  #[DataProvider('dataProviderAssertionsFailWithAssertionException')]
  public function testAssertionsFailWithAssertionException(string $trait, string $file): void {
    $violations = [];
    foreach (self::traitOwnMethods($trait, $file) as $method) {
      $thrown = self::thrownExceptionNames($method);

      if (preg_match('/Assert(?![a-z])/', $method->getName()) !== 1 || $thrown === [] || array_intersect($thrown, static::ASSERTION_EXCEPTIONS) !== []) {
        continue;
      }

      $violations[] = $method->getName();
    }

    $this->assertSame([], $violations, 'An "Assert" method fails with ExpectationException, ElementNotFoundException or AssertionException. Name a check that throws only \RuntimeException for what it does: "commandParseInteger", not "commandAssertInteger".');
  }

  public static function dataProviderAssertionsFailWithAssertionException(): array {
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
    $violations = array_values(array_filter(self::traitOwnMethodNames($trait, $file), static fn(string $name): bool => preg_match('/(?:No|DoesNot|DoNot)[A-Z]/', $name) === 1));

    $this->assertSame([], $violations, 'Negate with a bare "Not" placed before the predicate: "userAssertNotHasRoles", not "userAssertHasNoRoles" or "userAssertDoesNotHaveRoles".');
  }

  public static function dataProviderNegationSpelledNot(): array {
    return static::discoverTraitFiles();
  }

  /**
   * Assert that a qualifier follows the predicate it narrows.
   *
   * `Not` sits directly after the subject, so a qualifier placed before the
   * predicate, as in `cookieAssertWithNameNotExists`, is reported. A
   * qualifier right after `Not` is reported too, because `Not` negates a
   * predicate.
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

    $violations = array_values(array_filter(self::traitOwnMethodNames($trait, $file), static fn(string $name): bool => preg_match($pattern, $name) === 1));

    $this->assertSame([], $violations, 'Place a qualifier after the predicate so "Not" sits directly after the subject: "cookieAssertNotExistsWithName", not "cookieAssertWithNameNotExists", and "tableAssertLinkNotExistsInRow", not "tableAssertLinkNotInRow".');
  }

  public static function dataProviderQualifiersFollowPredicate(): array {
    return static::discoverTraitFiles();
  }

  /**
   * Assert that a negative step is named as its positive with `Not` added.
   *
   * 2 steps whose text differs only by "should" and "should not" map to 2
   * methods whose names differ only by `Not`.
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
   * Assert that a navigation step is named for the visit and its page.
   *
   * A step reading "I visit" only opens a page, so its method opens with
   * `Visit` after the prefix: `userVisitProfileEditPage`, not
   * `userEditProfile`. A step naming a page or a link carries that noun
   * ahead of its first qualifier, as the step text does:
   * `mediaVisitEditPageWithName`, not `mediaVisitEditWithNamePage`.
   *
   * @param class-string $trait
   *   The trait to check.
   * @param string $file
   *   The absolute path to the file declaring the trait.
   */
  #[DataProvider('dataProviderNavigationStepsOpenWithVisit')]
  public function testNavigationStepsOpenWithVisit(string $trait, string $file): void {
    $prefix = self::traitPrefix((new \ReflectionClass($trait))->getShortName());

    $violations = [];
    foreach (self::traitOwnMethods($trait, $file) as $method) {
      foreach ($method->getAttributes(When::class) as $attribute) {
        $step = (string) $attribute->newInstance()->getPattern();

        if (!str_starts_with($step, 'I visit ')) {
          continue;
        }

        $name = $method->getName();
        $destination = preg_match('/ (page|link)\b/', $step, $matches) === 1 ? ucfirst($matches[1]) : '';
        $words = $destination === '' ? '' : sprintf('(?:(?!(?:%s)(?![a-z]))[A-Z][a-z0-9]*)*?%s', implode('|', static::QUALIFIERS), $destination);

        if (preg_match(sprintf('/^Visit%s(?![a-z])/', $words), substr($name, strlen($prefix))) !== 1) {
          $violations[] = sprintf('%s() for "%s"', $name, $step);
        }
      }
    }

    $this->assertSame([], $violations, 'Name a navigation step for the visit and the page it opens, ahead of any qualifier: "mediaVisitEditPageWithName" for "I visit the :media_type media edit page with the name :name", not "mediaEditWithName" or "mediaVisitEditWithNamePage".');
  }

  public static function dataProviderNavigationStepsOpenWithVisit(): array {
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
    $violations = array_values(array_filter(self::traitOwnMethodNames($trait, $file), static fn(string $name): bool => preg_match('/Assert[A-Za-z0-9]*Is[A-Z]/', $name) === 1));

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
    $violations = array_values(array_filter(self::traitOwnMethodNames($trait, $file), static fn(string $name): bool => preg_match('/Assert[A-Za-z0-9]*Has(?:(?:Content|Value|Text)(?![a-z])|[A-Z][A-Za-z0-9]*?(?:With|Containing)(?=[A-Z]))/', $name) === 1));

    $this->assertSame([], $violations, 'Keep "Has" for something the subject holds, and compare a value with "Equals" or "Contains": "stateAssertValueEquals", not "stateAssertHasValue", and "elementAssertCssPropertyEquals", not "elementAssertHasCssPropertyWithValue".');
  }

  public static function dataProviderHasNamesWhatSubjectHolds(): array {
    return static::discoverTraitFiles();
  }

  /**
   * Assert that existence and containment carry their documented words.
   *
   * Existence is `Exists` or `Exist` and containment is `Contains`, so an
   * assertion names neither with a synonym.
   *
   * @param class-string $trait
   *   The trait to check.
   * @param string $file
   *   The absolute path to the file declaring the trait.
   */
  #[DataProvider('dataProviderPredicatesSpelledExistsAndContains')]
  public function testPredicatesSpelledExistsAndContains(string $trait, string $file): void {
    $violations = array_values(array_filter(self::traitOwnMethodNames($trait, $file), static fn(string $name): bool => preg_match('/Assert[A-Za-z0-9]*(?:Absent|Includes?|Including|Missing|Presence|Present)(?![a-z])/', $name) === 1));

    $this->assertSame([], $violations, 'Spell existence "Exists" or "Exist" and containment "Contains": "metatagAssertRobotsContains", not "metatagAssertRobotsIncludes", and "metatagAssertMetaSetExists", not "metatagAssertMetaSetPresent".');
  }

  public static function dataProviderPredicatesSpelledExistsAndContains(): array {
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
    $violations = array_values(array_filter(self::traitOwnMethodNames($trait, $file), static fn(string $name): bool => stripos($name, 'normalise') !== FALSE));

    $this->assertSame([], $violations, 'Spell it "Normalize", not "Normalise".');
  }

  public static function dataProviderSpellingIsAmerican(): array {
    return static::discoverTraitFiles();
  }

  /**
   * Assert that names spell logging in and out as `Login` and `Logout`.
   *
   * @param class-string $trait
   *   The trait to check.
   * @param string $file
   *   The absolute path to the file declaring the trait.
   */
  #[DataProvider('dataProviderLoginSpelledAsNoun')]
  public function testLoginSpelledAsNoun(string $trait, string $file): void {
    $violations = array_values(array_filter(self::traitOwnMethodNames($trait, $file), static fn(string $name): bool => preg_match('/Log(?:In|Out)(?![a-z])/', $name) === 1));

    $this->assertSame([], $violations, 'Spell it "Login" and "Logout", not "LogIn" and "LogOut": "userLoginAs", not "userLogInAs".');
  }

  public static function dataProviderLoginSpelledAsNoun(): array {
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
   * Assert that a published helper names what it does with a verb.
   *
   * A step takes its verb from its step text and a hook is named for its
   * event, so only the public helpers are read. A verb in the prefix counts,
   * as `query` does in `queryEntityIds`.
   *
   * @param class-string $trait
   *   The trait to check.
   * @param string $file
   *   The absolute path to the file declaring the trait.
   */
  #[DataProvider('dataProviderHelpersCarryVerb')]
  public function testHelpersCarryVerb(string $trait, string $file): void {
    $violations = [];
    foreach (self::traitOwnMethods($trait, $file) as $method) {
      if (!$method->isPublic() || self::isRegistered($method)) {
        continue;
      }

      preg_match_all('/[A-Z][a-z0-9]*/', ucfirst($method->getName()), $words);

      if (array_intersect($words[0], static::VERBS) === []) {
        $violations[] = $method->getName();
      }
    }

    $this->assertSame([], $violations, 'Name a published helper with a verb: "messageGetSelector", not "messageSelector", and "authIsLoggedIn", not "authLoggedIn". Add a new verb to VERBS.');
  }

  public static function dataProviderHelpersCarryVerb(): array {
    return static::discoverTraitFiles();
  }

  /**
   * Assert that a hook is named for the event it runs on.
   *
   * A hook reads `<prefix><Event>`. 2 methods cannot share a name, so a trait
   * registers 1 hook per event and calls a helper for each further job.
   *
   * @param class-string $trait
   *   The trait to check.
   * @param string $file
   *   The absolute path to the file declaring the trait.
   */
  #[DataProvider('dataProviderHooksAreNamedForTheirEvent')]
  public function testHooksAreNamedForTheirEvent(string $trait, string $file): void {
    $prefix = self::traitPrefix((new \ReflectionClass($trait))->getShortName());

    $violations = [];
    foreach (self::traitOwnMethods($trait, $file) as $method) {
      foreach ($method->getAttributes() as $attribute) {
        $name = $attribute->getName();

        if (!str_contains($name, '\\Hook\\')) {
          continue;
        }

        $expected = $prefix . preg_replace('/^.*\\\\/', '', $name);

        if ($method->getName() !== $expected) {
          $violations[] = sprintf('%s() for %s()', $method->getName(), $expected);
        }
      }
    }

    $this->assertSame([], $violations, 'Name a hook "<prefix><Event>" for the event it runs on, and merge 2 hooks on 1 event into 1 that calls a helper for each job: "authAfterScenario", not "authCleanUsers" and "authCleanRoles".');
  }

  public static function dataProviderHooksAreNamedForTheirEvent(): array {
    return static::discoverTraitFiles();
  }

  /**
   * Assert that `Create`, `Delete` and `Load` sit directly after the prefix.
   *
   * The verb comes before the entity it acts on, so a name carrying one of
   * them opens with it. A hook is named for its event, as
   * `contentBeforeNodeCreate()` is, and a `Visit` method names the page it
   * opens, as `contentVisitDeletePageWithTitle()` does, so neither is read.
   *
   * @param class-string $trait
   *   The trait to check.
   * @param string $file
   *   The absolute path to the file declaring the trait.
   */
  #[DataProvider('dataProviderLifecycleVerbsFollowPrefix')]
  public function testLifecycleVerbsFollowPrefix(string $trait, string $file): void {
    $prefix = self::traitPrefix((new \ReflectionClass($trait))->getShortName());

    $violations = [];
    foreach (self::traitOwnMethods($trait, $file) as $method) {
      $words = self::wordsAfterPrefix($method->getName(), $prefix);

      if (self::isHook($method) || in_array($words[0] ?? '', [...static::LIFECYCLE_VERBS, 'Visit'], TRUE) || array_intersect($words, static::LIFECYCLE_VERBS) === []) {
        continue;
      }

      $violations[] = $method->getName();
    }

    $this->assertSame([], $violations, 'Place "Create", "Delete" or "Load" directly after the prefix, before the entity it acts on: "entityLifecycleCreateNode", not "entityLifecycleNodeCreate".');
  }

  public static function dataProviderLifecycleVerbsFollowPrefix(): array {
    return static::discoverTraitFiles();
  }

  /**
   * Assert that a method acting on several entities carries `Multiple`.
   *
   * A create or delete step over a `the following` table and a `Load`
   * returning a set act on several entities. The method for 1 entity is the
   * same name without `Multiple`, so no lifecycle method carries `Single`.
   *
   * @param class-string $trait
   *   The trait to check.
   * @param string $file
   *   The absolute path to the file declaring the trait.
   */
  #[DataProvider('dataProviderBatchMethodsCarryMultiple')]
  public function testBatchMethodsCarryMultiple(string $trait, string $file): void {
    $prefix = self::traitPrefix((new \ReflectionClass($trait))->getShortName());

    $violations = [];
    foreach (self::traitOwnMethods($trait, $file) as $method) {
      $words = self::wordsAfterPrefix($method->getName(), $prefix);
      $verb = $words[0] ?? '';

      if (!in_array($verb, static::LIFECYCLE_VERBS, TRUE)) {
        continue;
      }

      $type = $method->getReturnType();
      $is_batch = $verb === 'Load' ? ($type instanceof \ReflectionNamedType && $type->getName() === 'array') : self::stepOpensWith($method, 'the following ');

      $carries_single = in_array('Single', $words, TRUE);
      $lacks_multiple = $is_batch && !in_array('Multiple', $words, TRUE);

      if (!$carries_single && !$lacks_multiple) {
        continue;
      }

      $violations[] = $method->getName();
    }

    $this->assertSame([], $violations, 'Name a method acting on several entities with "Multiple" and its 1-entity counterpart without it: "contentCreateMultiple" for "the following :content_type content exist:", "userLoadMultiple" for a set, and "mediaCreate", not "mediaCreateSingle".');
  }

  public static function dataProviderBatchMethodsCarryMultiple(): array {
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
   * Check whether Behat registers a method as a step, transform or hook.
   */
  protected static function isRegistered(\ReflectionMethod $method): bool {
    foreach ($method->getAttributes() as $attribute) {
      $name = $attribute->getName();

      if (str_starts_with($name, 'Behat\\') || str_contains($name, '\\Hook\\')) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Check whether Behat registers a method as a hook.
   */
  protected static function isHook(\ReflectionMethod $method): bool {
    foreach ($method->getAttributes() as $attribute) {
      if (str_contains($attribute->getName(), '\\Hook\\')) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Check whether the text of a method's `Given` step opens with a phrase.
   */
  protected static function stepOpensWith(\ReflectionMethod $method, string $phrase): bool {
    foreach ($method->getAttributes(Given::class) as $attribute) {
      if (str_starts_with((string) $attribute->newInstance()->getPattern(), $phrase)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Split the part of a method name after the trait prefix into words.
   *
   * @return array<int, string>
   *   The words, or an empty array when the name lacks the prefix.
   */
  protected static function wordsAfterPrefix(string $method, string $prefix): array {
    if (!self::hasPrefix($method, $prefix)) {
      return [];
    }

    preg_match_all('/[A-Z][a-z0-9]*/', substr($method, strlen($prefix)), $words);

    return $words[0];
  }

  /**
   * Read the first line of text in a method's docblock.
   *
   * @param \ReflectionMethod $method
   *   The method to read.
   *
   * @return string
   *   The summary line, or an empty string when the method has no docblock.
   */
  protected static function docblockSummary(\ReflectionMethod $method): string {
    foreach (explode("\n", (string) $method->getDocComment()) as $line) {
      $text = trim((string) preg_replace('#^\s*/?\*+/?#', '', $line));

      if ($text !== '') {
        return $text;
      }
    }

    return '';
  }

  /**
   * Collect the short names of the exceptions a method throws itself.
   *
   * @param \ReflectionMethod $method
   *   The method to read.
   *
   * @return array<int, string>
   *   The class names after each 'throw new' in the method body, without
   *   their namespace.
   */
  protected static function thrownExceptionNames(\ReflectionMethod $method): array {
    $lines = explode("\n", (string) file_get_contents((string) $method->getFileName()));
    $body = implode("\n", array_slice($lines, (int) $method->getStartLine() - 1, (int) $method->getEndLine() - (int) $method->getStartLine() + 1));

    preg_match_all('/throw new \\\\?(?:\w+\\\\)*(\w+)/', $body, $matches);

    return $matches[1];
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
   * The character after the prefix must be uppercase, so a name that only
   * starts with the same letters ('waiting' against 'wait') is not accepted.
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
