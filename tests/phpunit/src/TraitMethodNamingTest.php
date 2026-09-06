<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use DrevOps\BehatSteps\Drupal\OverrideTrait;
use DrevOps\BehatSteps\Drupal\TaxonomyTrait;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Asserts that every trait method is prefixed with its trait's name.
 *
 * Traits are mixed into a single consumer context, so an unprefixed method
 * name can collide with a method of the same name from another trait.
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

  #[DataProvider('dataProviderTraits')]
  public function testMethodNamesArePrefixedWithTraitName(string $trait, string $file): void {
    $reflection = new \ReflectionClass($trait);
    $prefix = self::traitPrefix($reflection->getShortName());
    $allowed = self::PARENT_OVERRIDES[$trait] ?? [];

    $violations = [];
    foreach ($reflection->getMethods() as $method) {
      // A trait that composes another trait reports the composed methods
      // too; the file each method is declared in tells them apart.
      if (realpath((string) $method->getFileName()) !== $file) {
        continue;
      }

      $name = $method->getName();

      if (in_array($name, $allowed, TRUE) || self::hasPrefix($name, $prefix)) {
        continue;
      }

      $violations[] = $name;
    }

    $this->assertSame([], $violations, sprintf('Methods in %s must be prefixed with "%s".', $reflection->getShortName(), $prefix));
  }

  public static function dataProviderTraits(): array {
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
