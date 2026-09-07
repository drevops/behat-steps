<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use DrevOps\BehatSteps\Drupal\OverrideTrait;
use DrevOps\BehatSteps\Drupal\TaxonomyTrait;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Asserts that trait method names follow the library's naming conventions.
 *
 * Traits are mixed into a single consumer context, so an unprefixed method
 * name can collide with a method of the same name from another trait.
 *
 * The remaining conventions keep one shape per idea across 690 methods, so
 * that a consumer can derive a name rather than look it up. They are stated
 * in CONTRIBUTING.md.
 */
#[CoversNothing]
class TraitMethodNamingTest extends UnitTestCase {

  /**
   * Methods that override a Drupal Extension context method.
   *
   * An override binds by name, so these cannot carry the trait prefix.
   *
   * @var array<class-string, array<int, string>>
   */
  const PARENT_OVERRIDES = [
    OverrideTrait::class => ['createNodes', 'createUsers', 'iAmLoggedInAsUserWithRole'],
    TaxonomyTrait::class => ['createTerms'],
  ];

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
    $allowed = self::PARENT_OVERRIDES[$trait] ?? [];

    $violations = [];
    foreach (self::traitOwnMethodNames($trait, $file) as $name) {
      if (in_array($name, $allowed, TRUE) || self::hasPrefix($name, $prefix)) {
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
   * `Not` is the only negation particle, so the determiner `No` never opens a
   * negated noun. A negative name is then its positive counterpart with `Not`
   * inserted and nothing else changed.
   *
   * @param class-string $trait
   *   The trait to check.
   * @param string $file
   *   The absolute path to the file declaring the trait.
   */
  #[DataProvider('dataProviderNegationSpelledNot')]
  public function testNegationSpelledNot(string $trait, string $file): void {
    $violations = array_values(array_filter(self::traitOwnMethodNames($trait, $file), fn(string $name): bool => preg_match('/No[A-Z]/', $name) === 1));

    $this->assertSame([], $violations, 'Negate with "Not" placed before the predicate, not with "No" before a noun: "userAssertNotHasRoles", not "userAssertHasNoRoles".');
  }

  public static function dataProviderNegationSpelledNot(): array {
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
   * The allowlist is not a place to retire a method that no longer exists.
   */
  public function testParentOverrideAllowlistIsCurrent(): void {
    foreach (self::PARENT_OVERRIDES as $trait => $methods) {
      $reflection = new \ReflectionClass($trait);

      foreach ($methods as $method) {
        $this->assertTrue($reflection->hasMethod($method), sprintf('%s::%s() is allowlisted but does not exist.', $reflection->getShortName(), $method));
        $this->assertFalse(self::hasPrefix($method, self::traitPrefix($reflection->getShortName())), sprintf('%s::%s() is allowlisted but already conforms; remove the entry.', $reflection->getShortName(), $method));
      }
    }
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
      $trait = 'DrevOps\\BehatSteps\\' . str_replace(DIRECTORY_SEPARATOR, '\\', substr($relative, 0, -strlen('.php')));

      $files[$relative] = [$trait, $path];
    }

    ksort($files);

    return $files;
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
    $methods = (new \ReflectionClass($trait))->getMethods();

    $names = [];
    foreach ($methods as $method) {
      // A trait that composes another trait reports the composed methods
      // too; the file each method is declared in tells them apart.
      if (realpath((string) $method->getFileName()) !== $file) {
        continue;
      }

      $names[] = $method->getName();
    }

    return $names;
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

}
