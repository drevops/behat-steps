<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use DrevOps\BehatSteps\Behat\Context\DrupalContext;
use DrevOps\BehatSteps\Behat\Prerequisite\PrerequisiteReader;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests that every trait states its prerequisites the same way.
 *
 * A trait declares what it needs in '<prefix>Prerequisites()', through the
 * backend capabilities, and checks it with 'assertPrerequisites(__TRAIT__)' or
 * 'prerequisitesMet(__TRAIT__)'. Module state is asked of
 * 'ModuleCapabilityInterface', never of Drupal's module handler.
 */
#[CoversNothing]
class PrerequisiteDeclarationsTest extends UnitTestCase {

  /**
   * Namespace every capability a shipped trait declares belongs to.
   */
  protected const CAPABILITY_NAMESPACE = 'DrevOps\BehatSteps\Backend\Capability\\';

  /**
   * The methods that evaluate a trait's prerequisites.
   */
  protected const CHECKS = ['assertPrerequisites', 'prerequisitesMet'];

  /**
   * Tests that a trait's declarations are well-formed.
   *
   * @param string $trait
   *   Fully qualified trait name.
   */
  #[DataProvider('dataProviderDeclarationsAreWellFormed')]
  public function testDeclarationsAreWellFormed(string $trait): void {
    $context = (new \ReflectionClass(DrupalContext::class))->newInstanceWithoutConstructor();
    $prerequisites = (new PrerequisiteReader())->read($context, $trait);

    $this->assertNotSame([], $prerequisites, sprintf('%s declares an empty list of prerequisites.', $trait));

    foreach ($prerequisites as $prerequisite) {
      $this->assertStringStartsWith(static::CAPABILITY_NAMESPACE, $prerequisite->capability, sprintf('%s declares a prerequisite on "%s", which is not a backend capability.', $trait, $prerequisite->capability));
      $this->assertMatchesRegularExpression('/^[a-z].*[^.]$/', $prerequisite->description, sprintf('%s describes a prerequisite as "%s". A description is a clause completing "requires that", so it starts in lower case and carries no closing period.', $trait, $prerequisite->description));
    }
  }

  public static function dataProviderDeclarationsAreWellFormed(): array {
    return array_filter(static::discoverTraits(), static fn(array $row): bool => static::declaresPrerequisites($row[0]));
  }

  /**
   * Tests that a trait declaring prerequisites checks them, and vice versa.
   *
   * @param string $trait
   *   Fully qualified trait name.
   */
  #[DataProvider('dataProviderDeclaringAndCheckingGoTogether')]
  public function testDeclaringAndCheckingGoTogether(string $trait): void {
    $declares = static::declaresPrerequisites($trait);
    $checks = FALSE;

    foreach (static::significantTokens((string) static::reflect($trait)->getFileName()) as $token) {
      if (is_array($token) && $token[0] === T_STRING && in_array($token[1], static::CHECKS, TRUE)) {
        $checks = TRUE;
      }
    }

    $method = PrerequisiteReader::methodFor($trait);

    $this->assertSame($declares, $checks, $declares
      ? sprintf('%s declares its prerequisites in %s() but never checks them. Call "$this->assertPrerequisites(__TRAIT__)" where it starts acting.', $trait, $method)
      : sprintf('%s checks prerequisites it does not declare. Declare them in %s().', $trait, $method));
  }

  public static function dataProviderDeclaringAndCheckingGoTogether(): array {
    return static::discoverTraits();
  }

  /**
   * Tests that a prerequisite check names its own trait.
   *
   * @param string $trait
   *   Fully qualified trait name.
   */
  #[DataProvider('dataProviderChecksNameTheirTrait')]
  public function testChecksNameTheirTrait(string $trait): void {
    $violations = [];
    $tokens = static::significantTokens((string) static::reflect($trait)->getFileName());

    foreach ($tokens as $index => $token) {
      $argument = $tokens[$index + 2] ?? NULL;

      if (is_array($token) && $token[0] === T_STRING && in_array($token[1], static::CHECKS, TRUE) && (!is_array($argument) || $argument[0] !== T_TRAIT_C)) {
        $violations[] = sprintf('Line %d passes %s() a name other than __TRAIT__.', $token[2], $token[1]);
      }
    }

    $this->assertSame([], $violations, 'A trait checks its own prerequisites, so it passes "__TRAIT__".');
  }

  public static function dataProviderChecksNameTheirTrait(): array {
    return static::discoverTraits();
  }

  /**
   * Tests that a trait asks for module state through the module capability.
   *
   * @param string $trait
   *   Fully qualified trait name.
   */
  #[DataProvider('dataProviderModuleStateGoesThroughTheCapability')]
  public function testModuleStateGoesThroughTheCapability(string $trait): void {
    $violations = [];

    foreach (static::significantTokens((string) static::reflect($trait)->getFileName()) as $token) {
      if (is_array($token) && $token[0] === T_STRING && $token[1] === 'moduleExists') {
        $violations[] = sprintf("Line %d asks Drupal's module handler whether a module is enabled.", $token[2]);
      }
    }

    $this->assertSame([], $violations, 'Declare a module the trait needs in its <prefix>Prerequisites(), or ask ModuleCapabilityInterface::moduleIsEnabled() for a module it only adapts to.');
  }

  public static function dataProviderModuleStateGoesThroughTheCapability(): array {
    return static::discoverTraits();
  }

  /**
   * Determines whether a trait declares prerequisites in its own file.
   *
   * @param string $trait
   *   Fully qualified trait name.
   */
  protected static function declaresPrerequisites(string $trait): bool {
    $reflection = static::reflect($trait);
    $method = PrerequisiteReader::methodFor($trait);

    return $reflection->hasMethod($method) && $reflection->getMethod($method)->getFileName() === $reflection->getFileName();
  }

}
