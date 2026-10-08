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
use DrevOps\BehatSteps\Behat\Context\DrupalContext;
use DrevOps\BehatSteps\Behat\Context\WebContext;
use DrevOps\BehatSteps\Behat\Hook\Attribute\BeforeNodeCreate;
use DrevOps\BehatSteps\Behat\Hook\Scope\BeforeNodeCreateScope;
use DrevOps\BehatSteps\Tests\Fixtures\RedeclaredMethodTrait;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for the shape of the library's public surface.
 *
 * Visibility marks the API. A public method that Behat does not register is
 * the toolbox, published in HELPERS.md and covered by semantic versioning; a
 * protected one carries no guarantee.
 *
 * A method cannot be narrowed again before the next major, so these tests
 * hold the 5 conventions below.
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
   * Constants whose name does not start with the trait prefix, and why.
   */
  protected const ALLOWED_CONSTANTS = [];

  /**
   * The classes naming the contracts a composed trait satisfies.
   */
  protected const CONTEXTS = [WebContext::class, DrupalContext::class];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // The documentation check holds what HELPERS.md publishes, so it reads a
    // docblock through the generator's own resolution rather than a copy of it.
    require_once dirname(__DIR__, 3) . '/docs.php';
  }

  #[DataProvider('dataProviderPublicMethodsAreDocumented')]
  public function testPublicMethodsAreDocumented(string $trait): void {
    $methods = static::collectTraitOwnMethods($trait);
    $violations = [];

    foreach ($methods as $method) {
      if (!$method->isPublic() || static::readMethodBehatAttributes($method) !== []) {
        continue;
      }

      $comment = resolve_inherited_comment($method, helper_trait_contracts(static::reflect($trait), static::CONTEXTS));

      if ($comment === '' || preg_match('/^\s*\*\s+[A-Z]/m', $comment) !== 1) {
        $violations[] = $trait . '::' . $method->getName();
      }
    }

    $this->assertSame([], $violations, 'A public method that is neither a step nor a hook is published in HELPERS.md as part of the toolbox, so it needs a docblock summary. Write one, or make the method protected.');
  }

  public static function dataProviderPublicMethodsAreDocumented(): array {
    return static::discoverTraits();
  }

  /**
   * Assert that a method whose docblock invites an override is public.
   *
   * An override relies on the package calling the method with the same
   * signature, which only a published method promises.
   *
   * @param string $trait
   *   Fully qualified trait name.
   */
  #[DataProvider('dataProviderOverridePointsArePublic')]
  public function testOverridePointsArePublic(string $trait): void {
    $violations = [];

    foreach (static::collectTraitOwnMethods($trait) as $method) {
      if ($method->isPublic() || !static::isOverrideInvitation((string) $method->getDocComment())) {
        continue;
      }

      $violations[] = $trait . '::' . $method->getName();
    }

    $this->assertSame([], $violations, 'A docblock inviting a project to override a method promises that the package keeps calling it, so the method is public and published in HELPERS.md. Make it public, or reword the docblock.');
  }

  public static function dataProviderOverridePointsArePublic(): array {
    return static::discoverTraits();
  }

  /**
   * Assert that a docblock inviting an override is told apart from others.
   *
   * @param string $comment
   *   A method docblock.
   * @param bool $expected
   *   Whether the docblock invites an override of its own method.
   */
  #[DataProvider('dataProviderOverrideInvitationsAreDetected')]
  public function testOverrideInvitationsAreDetected(string $comment, bool $expected): void {
    $this->assertSame($expected, static::isOverrideInvitation($comment));
  }

  public static function dataProviderOverrideInvitationsAreDetected(): array {
    return [
      'imperative naming a purpose' => ["/**\n   * Override to brand the report.\n   */", TRUE],
      'imperative naming a condition' => ["/**\n   * Override when wiring a different engine.\n   */", TRUE],
      'imperative naming the method' => ["/**\n   * Override this method in the context class.\n   */", TRUE],
      'consumer overriding the method' => ["/**\n   * A consuming context overrides this method.\n   */", TRUE],
      'phrase wrapped across 2 lines' => ["/**\n   * Default: the bundled engine. Override\n   * to point at a mirror.\n   */", TRUE],
      'override of another method' => ["/**\n   * For branding, override accessibilityRenderHtmlPage() instead.\n   */", FALSE],
      'tag overriding a tag' => ["/**\n   * A scenario tag overrides a feature tag.\n   */", FALSE],
      'noun' => ["/**\n   * Threshold override resolved from tags.\n   */", FALSE],
      'no docblock' => ['', FALSE],
    ];
  }

  /**
   * Assert that a trait's own methods include a composed method it redeclares.
   */
  public function testTraitOwnMethodsIncludeRedeclaredMethod(): void {
    $names = array_map(static fn(\ReflectionMethod $method): string => $method->getName(), static::collectTraitOwnMethods(RedeclaredMethodTrait::class));
    sort($names);

    $this->assertSame(['redeclaredGet', 'redeclaredGetLabel'], $names);
  }

  #[DataProvider('dataProviderHookMethodsDeclareTheirScope')]
  public function testHookMethodsDeclareTheirScope(string $trait): void {
    $methods = static::collectTraitOwnMethods($trait);
    $violations = [];

    foreach ($methods as $method) {
      $attributes = static::readMethodBehatAttributes($method);

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
    $composed = static::collectComposedPropertyNames($reflection);
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
    $composed = static::collectComposedConstantNames($reflection);
    $prefix = static::resolveTraitConstantPrefix($trait);
    $constants = $reflection->getReflectionConstants();
    $violations = [];

    foreach ($constants as $constant) {
      if (in_array($constant->getName(), $composed, TRUE)) {
        continue;
      }

      $identifier = $trait . '::' . $constant->getName();

      if (preg_match('/(^|\s)(public|protected|private)\s+const\s/', static::readConstantDeclaration($reflection, $constant->getName())) !== 1) {
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
   * Assert that the line declaring a constant is told apart from other lines.
   *
   * @param string $line
   *   A source line.
   * @param bool $expected
   *   Whether the line declares the constant 'PREFIX_NAME'.
   */
  #[DataProvider('dataProviderConstantDeclarationsAreDetected')]
  public function testConstantDeclarationsAreDetected(string $line, bool $expected): void {
    $this->assertSame($expected, preg_match(static::buildConstantDeclarationPattern('PREFIX_NAME'), $line) === 1);
  }

  public static function dataProviderConstantDeclarationsAreDetected(): array {
    return [
      'untyped' => ["  protected const PREFIX_NAME = 'value';", TRUE],
      'typed' => ["  protected const string PREFIX_NAME = 'value';", TRUE],
      'nullable type' => ['  public const ?string PREFIX_NAME = NULL;', TRUE],
      'union type' => ['  public const int|string PREFIX_NAME = 1;', TRUE],
      'final and typed' => ["  final public const string PREFIX_NAME = 'value';", TRUE],
      'no visibility' => ["  const PREFIX_NAME = 'value';", TRUE],
      'longer name' => ["  protected const string PREFIX_NAME_LONGER = 'value';", FALSE],
      'name ending in it' => ["  protected const string OTHER_PREFIX_NAME = 'value';", FALSE],
      'reference' => ['    return self::PREFIX_NAME;', FALSE],
    ];
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
  protected static function collectTraitOwnMethods(string $trait): array {
    $reflection = static::reflect($trait);
    $file = $reflection->getFileName();

    // A trait reports the methods of the traits it composes as its own, so a
    // method counts only when this trait's file declares it. A redeclared
    // composed method is declared here too.
    return array_values(array_filter($reflection->getMethods(), static fn(\ReflectionMethod $method): bool => $method->getFileName() === $file));
  }

  /**
   * Check whether a docblock invites a project to override its method.
   *
   * The line breaks are collapsed first, so a phrase wrapped across 2 lines
   * still matches.
   *
   * @param string $comment
   *   A method docblock.
   */
  protected static function isOverrideInvitation(string $comment): bool {
    $text = (string) preg_replace('/\s*\n\s*\*\s*/', ' ', $comment);

    return preg_match('/\b(?:Override (?:to|when)|[Oo]verrides? this method)\b/', $text) === 1;
  }

  /**
   * Return the names of properties a trait receives from composed traits.
   *
   * Reflection flattens a composed trait's members into the composing trait
   * and reports the composing trait as their declaring class. The origin is
   * resolved by name against the composed traits instead.
   *
   * @param \ReflectionClass<object> $reflection
   *   Reflection of the trait.
   *
   * @return array<int, string>
   *   Property names that originate in a composed trait.
   */
  protected static function collectComposedPropertyNames(\ReflectionClass $reflection): array {
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
  protected static function collectComposedConstantNames(\ReflectionClass $reflection): array {
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
  protected static function readMethodBehatAttributes(\ReflectionMethod $method): array {
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
  protected static function resolveTraitConstantPrefix(string $trait): string {
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
  protected static function readConstantDeclaration(\ReflectionClass $reflection, string $name): string {
    $file = $reflection->getFileName();

    // @codeCoverageIgnoreStart
    if ($file === FALSE) {
      return '';
    }
    // @codeCoverageIgnoreEnd
    $lines = file($file) ?: [];

    foreach ($lines as $line) {
      if (preg_match(static::buildConstantDeclarationPattern($name), $line) === 1) {
        return $line;
      }
    }

    // @codeCoverageIgnoreStart
    return '';
    // @codeCoverageIgnoreEnd
  }

}
