<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use Behat\MinkExtension\Context\RawMinkContext;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use DrevOps\BehatSteps\DrupalContext;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests the skill that writes a step of a project's own.
 *
 * The skill shows its example trait only as a code block, so the test loads
 * it from the skill and composes it onto a context extending 'DrupalContext',
 * as a project's 'FeatureContext' would. The skill tells an agent to call
 * only published helpers, so the test holds its own code to that rule.
 */
#[CoversNothing]
class CreateBehatStepSkillTest extends UnitTestCase {

  /**
   * The skill, relative to the repository root.
   */
  protected const SKILL = 'skills/create-behat-step/SKILL.md';

  /**
   * The helper reference, relative to the repository root.
   */
  protected const HELPERS = 'HELPERS.md';

  /**
   * The declaration that identifies the example among the skill's code blocks.
   */
  protected const DECLARATION = 'trait PublishingTrait {';

  /**
   * The example trait.
   */
  protected const EXAMPLE_TRAIT = 'PublishingTrait';

  /**
   * The prefix every member of the example trait carries.
   */
  protected const PREFIX = 'publishing';

  /**
   * The context the test composes the example onto.
   */
  protected const CONTEXT = 'CreateBehatStepExampleContext';

  /**
   * The protected methods the skill warns a step against calling.
   */
  protected const PROTECTED_METHODS = ['stringParseInteger', 'stringParseNumber'];

  /**
   * The shipped step methods the skill warns a step against calling.
   */
  protected const STEP_METHODS = ['pathVisit'];

  public function testTheExampleRegistersItsSteps(): void {
    $this->assertSame([
      'publishingAssertPageAuthoredBy' => [Then::class, 'the page titled :title should be authored by the user :name'],
      'publishingCreatePage' => [Given::class, 'a page titled :title exists'],
      'publishingLoginAsEditor' => [Given::class, 'I am logged in as an editor'],
      'publishingVisitEditPage' => [When::class, 'I edit the page titled :title'],
    ], $this->collectExampleSteps());
  }

  public function testTheExampleComposesOntoDrupalContext(): void {
    $context = $this->loadExample();

    $this->assertTrue(is_subclass_of($context, DrupalContext::class));
    $this->assertContains(static::EXAMPLE_TRAIT, class_uses($context));
  }

  public function testTheExampleMembersCarryItsPrefixAndShadowNothing(): void {
    $this->loadExample();

    foreach (static::reflect(static::EXAMPLE_TRAIT)->getMethods() as $method) {
      $this->assertStringStartsWith(static::PREFIX, $method->getName(), sprintf('%s() carries the prefix of %s.', $method->getName(), static::EXAMPLE_TRAIT));
      $this->assertFalse(method_exists(DrupalContext::class, $method->getName()), sprintf('%s() replaces no method DrupalContext declares or inherits.', $method->getName()));
    }
  }

  public function testTheExampleStepParametersMatchTheirPlaceholders(): void {
    foreach ($this->collectExampleSteps() as $method_name => [, $pattern]) {
      preg_match_all('/:([a-z][a-z0-9_]*)/', (string) $pattern, $matches);

      $parameters = [];

      foreach (static::reflect(static::EXAMPLE_TRAIT)->getMethod($method_name)->getParameters() as $parameter) {
        $type = $parameter->getType();
        $parameters[] = [$parameter->getName(), $type instanceof \ReflectionNamedType ? $type->getName() : NULL, $type?->allowsNull(), $parameter->isDefaultValueAvailable()];
      }

      $expected = array_map(static fn(string $placeholder): array => [$placeholder, 'string', FALSE, FALSE], $matches[1]);
      $this->assertSame($expected, $parameters, sprintf('%s() takes a required string per placeholder, named as the placeholder.', $method_name));
    }
  }

  public function testTheSkillCallsOnlyPublishedHelpersAndMink(): void {
    $published = $this->collectPublishedHelpers();

    preg_match_all('/\$this->([A-Za-z_][A-Za-z0-9_]*)\(/', implode("\n", $this->collectCodeBlocks()), $matches);
    $calls = array_values(array_unique($matches[1]));

    $this->assertNotEmpty($calls, sprintf('%s holds code that calls helpers.', static::SKILL));

    $unpublished = array_values(array_filter($calls, fn(string $method): bool => !in_array($method, $published, TRUE) && !$this->isMinkMethod($method)));
    $this->assertSame([], $unpublished, sprintf("The code in %s calls only the helpers %s publishes and the public methods of Mink's RawMinkContext.", static::SKILL, static::HELPERS));
  }

  public function testTheSkillNamesOnlyHelpersAndWarnings(): void {
    $this->loadExample();

    $known = array_merge($this->collectPublishedHelpers(), static::PROTECTED_METHODS, static::STEP_METHODS, array_map(static fn(\ReflectionMethod $method): string => $method->getName(), static::reflect(static::EXAMPLE_TRAIT)->getMethods()));

    preg_match_all('/`(?:\$this->)?([a-z][A-Za-z0-9]*)\(\)`/', $this->readSkill(), $matches);

    $unknown = array_values(array_filter(array_unique($matches[1]), fn(string $method): bool => !in_array($method, $known, TRUE) && !$this->isMinkMethod($method)));
    $this->assertSame([], $unknown, sprintf("%s names only the helpers %s publishes, the public methods of Mink's RawMinkContext, the methods of its example and the methods it warns against.", static::SKILL, static::HELPERS));
  }

  #[DataProvider('dataProviderNamedProtectedMethodIsUnpublished')]
  public function testNamedProtectedMethodIsUnpublished(string $method): void {
    $this->assertStringContainsString('`' . $method . '()`', $this->readSkill(), sprintf('%s names %s().', static::SKILL, $method));
    $this->assertTrue((new \ReflectionMethod(DrupalContext::class, $method))->isProtected(), sprintf('%s() is protected, as %s says.', $method, static::SKILL));
    $this->assertNotContains($method, $this->collectPublishedHelpers(), sprintf('%s does not publish %s(), as %s says.', static::HELPERS, $method, static::SKILL));
  }

  public static function dataProviderNamedProtectedMethodIsUnpublished(): array {
    return static::buildRows(static::PROTECTED_METHODS);
  }

  #[DataProvider('dataProviderNamedStepMethodIsShippedStep')]
  public function testNamedStepMethodIsShippedStep(string $method): void {
    $this->assertStringContainsString('`' . $method . '()`', $this->readSkill(), sprintf('%s names %s().', static::SKILL, $method));

    $kinds = array_map(static fn(\ReflectionAttribute $attribute): string => $attribute->getName(), (new \ReflectionMethod(DrupalContext::class, $method))->getAttributes());
    $this->assertNotSame([], array_intersect($kinds, [Given::class, When::class, Then::class]), sprintf('%s() is a shipped step, as %s says.', $method, static::SKILL));
  }

  public static function dataProviderNamedStepMethodIsShippedStep(): array {
    return static::buildRows(static::STEP_METHODS);
  }

  /**
   * Build data provider rows of method names, keyed by name.
   *
   * @param list<string> $methods
   *   The method names.
   *
   * @return array<string, array{string}>
   *   1 row per method.
   */
  protected static function buildRows(array $methods): array {
    return array_combine($methods, array_map(static fn(string $method): array => [$method], $methods));
  }

  /**
   * Load the example trait from the skill, composed onto a context.
   *
   * @return class-string<\DrevOps\BehatSteps\DrupalContext>
   *   The context composing the example.
   */
  protected function loadExample(): string {
    if (!class_exists(static::CONTEXT, FALSE)) {
      $blocks = array_values(array_filter($this->collectCodeBlocks(), static fn(string $block): bool => str_contains($block, static::DECLARATION)));

      $this->assertCount(1, $blocks, sprintf('%s holds 1 code block declaring the example trait.', static::SKILL));

      require_once $this->writeFixture(static::EXAMPLE_TRAIT . '.php', $blocks[0]);
      require_once $this->writeFixture(static::CONTEXT . '.php', sprintf("<?php\n\nclass %s extends \\DrevOps\\BehatSteps\\DrupalContext {\n\n  use %s;\n\n}\n", static::CONTEXT, static::EXAMPLE_TRAIT));
    }

    /** @var class-string<\DrevOps\BehatSteps\DrupalContext> $class */
    $class = static::CONTEXT;

    return $class;
  }

  /**
   * Collect the steps the example trait registers.
   *
   * @return array<string, array{string, mixed}>
   *   The attribute class and the step text, keyed by method name.
   */
  protected function collectExampleSteps(): array {
    $this->loadExample();

    $kinds = [Given::class, When::class, Then::class];
    $steps = [];

    foreach (static::reflect(static::EXAMPLE_TRAIT)->getMethods() as $method) {
      foreach ($method->getAttributes() as $attribute) {
        if (in_array($attribute->getName(), $kinds, TRUE)) {
          $steps[$method->getName()] = [$attribute->getName(), $attribute->getArguments()[0] ?? NULL];
        }
      }
    }

    ksort($steps);

    return $steps;
  }

  /**
   * Read the skill.
   */
  protected function readSkill(): string {
    return (string) file_get_contents(dirname(__DIR__, 3) . '/' . static::SKILL);
  }

  /**
   * Collect the PHP code blocks of the skill.
   *
   * @return list<string>
   *   The code of each block.
   */
  protected function collectCodeBlocks(): array {
    preg_match_all('/^```php\n(.*?)^```$/ms', $this->readSkill(), $matches);

    return $matches[1];
  }

  /**
   * Collect the names of the helpers the helper reference publishes.
   *
   * @return list<string>
   *   The method names.
   */
  protected function collectPublishedHelpers(): array {
    preg_match_all('/<code>public function ([A-Za-z_][A-Za-z0-9_]*)\(/', (string) file_get_contents(dirname(__DIR__, 3) . '/' . static::HELPERS), $matches);

    return $matches[1];
  }

  /**
   * Check whether a method is public API of Mink's RawMinkContext.
   *
   * @param string $method
   *   The method name.
   */
  protected function isMinkMethod(string $method): bool {
    return method_exists(RawMinkContext::class, $method) && (new \ReflectionMethod(RawMinkContext::class, $method))->isPublic();
  }

}
