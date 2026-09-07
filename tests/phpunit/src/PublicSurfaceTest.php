<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use Behat\Behat\Hook\Scope\AfterFeatureScope;
use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Behat\Hook\Scope\BeforeFeatureScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Hook\Scope\BeforeStepScope;
use Behat\Hook\AfterFeature;
use Behat\Hook\AfterScenario;
use Behat\Hook\AfterStep;
use Behat\Hook\AfterSuite;
use Behat\Hook\BeforeFeature;
use Behat\Hook\BeforeScenario;
use Behat\Hook\BeforeStep;
use Behat\Hook\BeforeSuite;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Behat\Testwork\Hook\Scope\AfterSuiteScope;
use Behat\Testwork\Hook\Scope\BeforeSuiteScope;
use Behat\Transformation\Transform;
use DrevOps\BehatSteps\DateTrait;
use DrevOps\BehatSteps\Drupal\OverrideTrait;
use DrevOps\BehatSteps\Drupal\TaxonomyTrait;
use DrevOps\BehatSteps\ResponsiveTrait;
use Drupal\DrupalExtension\Hook\Attribute\BeforeNodeCreate;
use Drupal\DrupalExtension\Hook\Scope\BeforeNodeCreateScope;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for the shape of the library's public surface.
 *
 * Every trait member reachable from a consuming context is API, so a member
 * that exposes more than the surrounding code intends cannot be narrowed
 * before the next major. These tests hold the four conventions that keep the
 * surface deliberate.
 *
 * Each loop reads its subject into a variable first: a foreach directly over a
 * method call is rewritten by Rector to a camel case value variable, which the
 * snake case coding standard then rewrites back.
 */
#[CoversNothing]
class PublicSurfaceTest extends UnitTestCase {

  /**
   * Attributes that mark a method as a step or a value transformation.
   */
  protected const STEP_ATTRIBUTES = [
    Given::class,
    Then::class,
    Transform::class,
    When::class,
  ];

  /**
   * Hook attributes mapped to the scope class the hook method receives.
   */
  protected const HOOK_SCOPES = [
    AfterFeature::class => AfterFeatureScope::class,
    AfterScenario::class => AfterScenarioScope::class,
    AfterStep::class => AfterStepScope::class,
    AfterSuite::class => AfterSuiteScope::class,
    BeforeFeature::class => BeforeFeatureScope::class,
    BeforeScenario::class => BeforeScenarioScope::class,
    BeforeStep::class => BeforeStepScope::class,
    BeforeSuite::class => BeforeSuiteScope::class,
    BeforeNodeCreate::class => BeforeNodeCreateScope::class,
  ];

  /**
   * Public methods that carry no step or hook attribute, and why.
   */
  protected const ALLOWED_PUBLIC_METHODS = [
    DateTrait::class . '::dateRelativeProcessValue' => 'Documented utility that resolves a relative date token outside a step.',
    OverrideTrait::class . '::createNodes' => 'Overrides a public Drupal Extension method.',
    OverrideTrait::class . '::createUsers' => 'Overrides a public Drupal Extension method.',
    OverrideTrait::class . '::iAmLoggedInAsUserWithRole' => 'Overrides a public Drupal Extension method.',
    TaxonomyTrait::class . '::createTerms' => 'Overrides a public Drupal Extension method.',
    ResponsiveTrait::class . '::responsiveSetBreakpoints' => 'Documented utility that registers breakpoints outside a step.',
  ];

  /**
   * Constants whose name does not start with the trait prefix, and why.
   */
  protected const ALLOWED_CONSTANTS = [];

  #[DataProvider('dataProviderPublicMethodsAreStepsOrHooks')]
  public function testPublicMethodsAreStepsOrHooks(string $trait): void {
    $methods = static::traitOwnMethods($trait);
    $violations = [];

    foreach ($methods as $method) {
      $identifier = $trait . '::' . $method->getName();

      if (!$method->isPublic() || array_key_exists($identifier, static::ALLOWED_PUBLIC_METHODS)) {
        continue;
      }

      if (static::methodBehatAttributes($method) === []) {
        $violations[] = $identifier;
      }
    }

    $this->assertSame([], $violations, 'A public method that is neither a step nor a hook is API by accident. Make it protected, or add it to ALLOWED_PUBLIC_METHODS with the reason it is API.');
  }

  public static function dataProviderPublicMethodsAreStepsOrHooks(): array {
    return static::discoverTraits();
  }

  #[DataProvider('dataProviderHookMethodsDeclareTheirScope')]
  public function testHookMethodsDeclareTheirScope(string $trait): void {
    $methods = static::traitOwnMethods($trait);
    $violations = [];

    foreach ($methods as $method) {
      $attributes = static::methodBehatAttributes($method);

      foreach ($attributes as $attribute) {
        if (in_array($attribute, static::STEP_ATTRIBUTES, TRUE)) {
          continue;
        }

        $expected = static::HOOK_SCOPES[$attribute];
        $parameters = $method->getParameters();
        $type = count($parameters) === 1 ? $parameters[0]->getType() : NULL;

        if (!$type instanceof \ReflectionNamedType || $type->getName() !== $expected) {
          $violations[] = sprintf('%s::%s() must declare one parameter typed %s.', $trait, $method->getName(), $expected);
        }
      }
    }

    $this->assertSame([], $violations, 'Every hook declares its scope parameter, used or not, so a subclass overriding one has a single signature to match.');
  }

  public static function dataProviderHookMethodsDeclareTheirScope(): array {
    return static::discoverTraits();
  }

  #[DataProvider('dataProviderPropertiesDeclareNativeTypes')]
  public function testPropertiesDeclareNativeTypes(string $trait): void {
    $reflection = static::reflect($trait);
    $composed = static::composedPropertyNames($reflection);
    $properties = $reflection->getProperties();
    $violations = [];

    foreach ($properties as $property) {
      if (in_array($property->getName(), $composed, TRUE)) {
        continue;
      }

      if (!$property->hasType()) {
        $violations[] = $trait . '::$' . $property->getName();
      }
    }

    $this->assertSame([], $violations, 'A docblock alone does not constrain what a subclass may assign, so every property declares a native type.');
  }

  public static function dataProviderPropertiesDeclareNativeTypes(): array {
    return static::discoverTraits();
  }

  #[DataProvider('dataProviderConstantsDeclareVisibilityAndPrefix')]
  public function testConstantsDeclareVisibilityAndPrefix(string $trait): void {
    $reflection = static::reflect($trait);
    $composed = static::composedConstantNames($reflection);
    $prefix = static::traitConstantPrefix($trait);
    $constants = $reflection->getReflectionConstants();
    $violations = [];

    foreach ($constants as $constant) {
      if (in_array($constant->getName(), $composed, TRUE)) {
        continue;
      }

      $identifier = $trait . '::' . $constant->getName();

      if (preg_match('/(^|\s)(public|protected|private)\s+const\s/', static::constantDeclaration($reflection, $constant->getName())) !== 1) {
        $violations[] = $identifier . ' declares no visibility.';
      }

      if (!array_key_exists($identifier, static::ALLOWED_CONSTANTS) && !str_starts_with($constant->getName(), $prefix . '_')) {
        $violations[] = $identifier . ' is not prefixed with "' . $prefix . '_".';
      }
    }

    $this->assertSame([], $violations, 'A context composing two traits that declare the same constant name fails to compile, so every constant carries its trait prefix and an explicit visibility. Document an exception in ALLOWED_CONSTANTS.');
  }

  public static function dataProviderConstantsDeclareVisibilityAndPrefix(): array {
    return static::discoverTraits();
  }

  /**
   * Return every trait shipped in the library, keyed by name.
   *
   * @return array<string, array{string}>
   *   Fully qualified trait names, as data provider rows.
   */
  protected static function discoverTraits(): array {
    $root = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'src';
    $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

    $traits = [];
    foreach ($files as $file) {
      if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
        continue;
      }

      $relative = substr($file->getPathname(), strlen($root) + 1, -strlen('.php'));
      $trait = 'DrevOps\BehatSteps\\' . str_replace(DIRECTORY_SEPARATOR, '\\', $relative);
      $traits[$trait] = [$trait];
    }

    ksort($traits);

    return $traits;
  }

  /**
   * Reflect a trait discovered by path.
   *
   * @param string $trait
   *   Fully qualified trait name.
   *
   * @return \ReflectionClass<object>
   *   Reflection of the trait.
   */
  protected static function reflect(string $trait): \ReflectionClass {
    /** @var class-string $trait */
    return new \ReflectionClass($trait);
  }

  /**
   * Return the methods a trait declares itself.
   *
   * @param string $trait
   *   Fully qualified trait name.
   *
   * @return array<int, \ReflectionMethod>
   *   Methods declared by the trait, excluding those it composes.
   */
  protected static function traitOwnMethods(string $trait): array {
    $reflection = static::reflect($trait);
    $used_traits = $reflection->getTraits();
    $own = $reflection->getMethods();

    $composed = [];
    foreach ($used_traits as $used_trait) {
      $methods = $used_trait->getMethods();

      foreach ($methods as $method) {
        $composed[] = $method->getName();
      }
    }

    return array_values(array_filter($own, static fn(\ReflectionMethod $method): bool => !in_array($method->getName(), $composed, TRUE)));
  }

  /**
   * Return the names of properties a trait receives from composed traits.
   *
   * Reflection flattens a composed trait's members into the composing trait
   * and reports the composing trait as their declaring class, so the origin is
   * resolved by name against the composed traits instead.
   *
   * @param \ReflectionClass<object> $reflection
   *   Reflection of the trait.
   *
   * @return array<int, string>
   *   Property names that originate in a composed trait.
   */
  protected static function composedPropertyNames(\ReflectionClass $reflection): array {
    $used_traits = $reflection->getTraits();
    $names = [];

    foreach ($used_traits as $used_trait) {
      $properties = $used_trait->getProperties();

      foreach ($properties as $property) {
        $names[] = $property->getName();
      }
    }

    return $names;
  }

  /**
   * Return the names of constants a trait receives from composed traits.
   *
   * @param \ReflectionClass<object> $reflection
   *   Reflection of the trait.
   *
   * @return array<int, string>
   *   Constant names that originate in a composed trait.
   */
  protected static function composedConstantNames(\ReflectionClass $reflection): array {
    $used_traits = $reflection->getTraits();
    $names = [];

    foreach ($used_traits as $used_trait) {
      $constants = $used_trait->getReflectionConstants();

      foreach ($constants as $constant) {
        $names[] = $constant->getName();
      }
    }

    return $names;
  }

  /**
   * Return the Behat step and hook attributes a method carries.
   *
   * @param \ReflectionMethod $method
   *   Method to inspect.
   *
   * @return array<int, string>
   *   Fully qualified attribute names.
   *
   * @throws \RuntimeException
   *   If the method carries a hook attribute absent from HOOK_SCOPES.
   */
  protected static function methodBehatAttributes(\ReflectionMethod $method): array {
    $attributes = $method->getAttributes();
    $found = [];

    foreach ($attributes as $attribute) {
      $name = $attribute->getName();

      if (in_array($name, static::STEP_ATTRIBUTES, TRUE) || array_key_exists($name, static::HOOK_SCOPES)) {
        $found[] = $name;
        continue;
      }

      // @codeCoverageIgnoreStart
      if (str_contains($name, '\\Hook\\')) {
        throw new \RuntimeException(sprintf('Hook attribute "%s" on %s::%s() is missing from HOOK_SCOPES.', $name, $method->getDeclaringClass()->getName(), $method->getName()));
      }
      // @codeCoverageIgnoreEnd
    }

    return $found;
  }

  /**
   * Return the constant prefix derived from the trait name.
   *
   * @param string $trait
   *   Fully qualified trait name.
   *
   * @return string
   *   Upper snake case form of the trait name without its "Trait" suffix.
   */
  protected static function traitConstantPrefix(string $trait): string {
    $short = (string) preg_replace('/Trait$/', '', static::reflect($trait)->getShortName());

    return strtoupper((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $short));
  }

  /**
   * Return the source line that declares a constant.
   *
   * Reflection reports an implicitly public constant and an explicitly public
   * one identically, so the modifier is read from the source.
   *
   * @param \ReflectionClass<object> $reflection
   *   Reflection of the trait.
   * @param string $name
   *   Constant name.
   *
   * @return string
   *   The declaring line, or an empty string when it cannot be located.
   */
  protected static function constantDeclaration(\ReflectionClass $reflection, string $name): string {
    $file = $reflection->getFileName();

    // @codeCoverageIgnoreStart
    if ($file === FALSE) {
      return '';
    }
    // @codeCoverageIgnoreEnd
    $lines = file($file) ?: [];

    foreach ($lines as $line) {
      if (preg_match('/(^|\s)const\s+' . preg_quote($name, '/') . '\s*=/', $line) === 1) {
        return $line;
      }
    }

    // @codeCoverageIgnoreStart
    return '';
    // @codeCoverageIgnoreEnd
  }

}
