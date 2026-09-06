<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

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
use Behat\Transformation\Transform;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for the shape of the library's public surface.
 *
 * Every trait member reachable from a consuming context is API, so a member
 * that exposes more than the surrounding code intends cannot be narrowed
 * before the next major. These tests hold the four conventions that keep the
 * surface deliberate.
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
    AfterFeature::class => 'Behat\Behat\Hook\Scope\AfterFeatureScope',
    AfterScenario::class => 'Behat\Behat\Hook\Scope\AfterScenarioScope',
    AfterStep::class => 'Behat\Behat\Hook\Scope\AfterStepScope',
    AfterSuite::class => 'Behat\Testwork\Hook\Scope\AfterSuiteScope',
    BeforeFeature::class => 'Behat\Behat\Hook\Scope\BeforeFeatureScope',
    BeforeScenario::class => 'Behat\Behat\Hook\Scope\BeforeScenarioScope',
    BeforeStep::class => 'Behat\Behat\Hook\Scope\BeforeStepScope',
    BeforeSuite::class => 'Behat\Testwork\Hook\Scope\BeforeSuiteScope',
    'Drupal\DrupalExtension\Hook\Attribute\BeforeNodeCreate' => 'Drupal\DrupalExtension\Hook\Scope\BeforeNodeCreateScope',
  ];

  /**
   * Public methods that carry no step or hook attribute, and why.
   */
  protected const ALLOWED_PUBLIC_METHODS = [
    'DrevOps\BehatSteps\DateTrait::dateRelativeProcessValue' => 'Documented utility that resolves a relative date token outside a step.',
    'DrevOps\BehatSteps\Drupal\OverrideTrait::createNodes' => 'Overrides a public Drupal Extension method.',
    'DrevOps\BehatSteps\Drupal\OverrideTrait::createUsers' => 'Overrides a public Drupal Extension method.',
    'DrevOps\BehatSteps\Drupal\OverrideTrait::iAmLoggedInAsUserWithRole' => 'Overrides a public Drupal Extension method.',
    'DrevOps\BehatSteps\Drupal\TaxonomyTrait::createTerms' => 'Overrides a public Drupal Extension method.',
    'DrevOps\BehatSteps\ResponsiveTrait::responsiveSetBreakpoints' => 'Documented utility that registers breakpoints outside a step.',
  ];

  /**
   * Constants whose name does not start with the trait prefix, and why.
   */
  protected const ALLOWED_CONSTANTS = [
    'DrevOps\BehatSteps\Drupal\HelperTrait::ENTITY_CLEANUP_EXCLUDED_TYPES' => 'Named for the entityCleanup* member family it configures.',
  ];

  #[DataProvider('dataProviderPublicMethodsAreStepsOrHooks')]
  public function testPublicMethodsAreStepsOrHooks(string $trait): void {
    $violations = [];

    foreach (static::traitOwnMethods($trait) as $method) {
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
    $violations = [];

    foreach (static::traitOwnMethods($trait) as $method) {
      foreach (static::methodBehatAttributes($method) as $attribute) {
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
    $violations = [];

    foreach ($reflection->getProperties() as $property) {
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
    $violations = [];

    foreach ($reflection->getReflectionConstants() as $constant) {
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

    $composed = [];
    foreach ($reflection->getTraits() as $used) {
      foreach ($used->getMethods() as $method) {
        $composed[] = $method->getName();
      }
    }

    return array_values(array_filter($reflection->getMethods(), static fn(\ReflectionMethod $method): bool => !in_array($method->getName(), $composed, TRUE)));
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
    $names = [];

    foreach ($reflection->getTraits() as $used) {
      foreach ($used->getProperties() as $property) {
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
    $names = [];

    foreach ($reflection->getTraits() as $used) {
      foreach ($used->getReflectionConstants() as $constant) {
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
    $found = [];

    foreach ($method->getAttributes() as $attribute) {
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
    foreach (file($file) ?: [] as $line) {
      if (preg_match('/(^|\s)const\s+' . preg_quote($name, '/') . '\s*=/', $line) === 1) {
        return $line;
      }
    }

    // @codeCoverageIgnoreStart
    return '';
    // @codeCoverageIgnoreEnd
  }

}
