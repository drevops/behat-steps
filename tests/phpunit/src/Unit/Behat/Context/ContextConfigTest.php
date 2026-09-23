<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Context;

use DrevOps\BehatSteps\Behat\Context\RawContext;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\BareConfigContext;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\ConfigurableContext;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\ConfigurableSubContext;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\MalformedConfigContext;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\MistaggedConfigContext;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\UndocumentedConfigContext;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\UntypedConfigContext;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * Tests the option resolution the base context owns.
 */
#[CoversClass(RawContext::class)]
class ContextConfigTest extends UnitTestCase {

  public function testDeclarationsAreDiscoveredByReflection(): void {
    $context = new ConfigurableContext();

    $this->assertSame('a default', $context->getOption('sample', 'label'));
    $this->assertSame(7, $context->getOption('sample', 'limit'));
    $this->assertSame(['.one', '.two'], $context->getOption('other_sample', 'selectors'));
  }

  public function testSubclassInheritsTheConstructor(): void {
    $context = new ConfigurableSubContext(['sample' => ['label' => 'inherited']]);

    $this->assertSame('inherited', $context->getOption('sample', 'label'));
  }

  public function testAnUndeclaredOptionIsRejectedOnRead(): void {
    $context = new ConfigurableContext();

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('declares the option "sample.missing". Declared options: other_sample.selectors, sample.enabled, sample.label, sample.limit');

    $context->getOption('sample', 'missing');
  }

  /**
   * Tests the precedence of the layers an option resolves through.
   *
   * @param array<string, mixed> $steps
   *   The extension's steps section.
   * @param array<string, mixed> $config
   *   The context's config argument.
   * @param list<string> $feature_tags
   *   Tags on the feature.
   * @param list<string> $scenario_tags
   *   Tags on the scenario.
   * @param mixed $expected
   *   The value the chain is expected to resolve to.
   */
  #[DataProvider('dataProviderPrecedence')]
  public function testPrecedence(array $steps, array $config, array $feature_tags, array $scenario_tags, mixed $expected): void {
    $context = new ConfigurableContext($config);
    $context->setParameters(['steps' => $steps]);

    $scope = $this->createBeforeScenarioScope($scenario_tags, $feature_tags);

    $this->assertSame($expected, $context->getOption('sample', 'enabled', $scope));
  }

  public static function dataProviderPrecedence(): \Iterator {
    yield 'the declaration default' => [[], [], [], [], TRUE];

    yield 'the steps section beats the default' => [
      ['sample' => ['enabled' => FALSE]],
      [],
      [],
      [],
      FALSE,
    ];

    yield 'the context argument beats the steps section' => [
      ['sample' => ['enabled' => FALSE]],
      ['sample' => ['enabled' => TRUE]],
      [],
      [],
      TRUE,
    ];

    yield 'a feature tag beats the context argument' => [
      [],
      ['sample' => ['enabled' => TRUE]],
      ['behat-steps-skip:SampleTrait'],
      [],
      FALSE,
    ];

    yield 'a scenario tag beats a feature tag' => [
      [],
      [],
      ['sample-off'],
      ['sample-on'],
      TRUE,
    ];

    yield 'a feature tag applies without a scenario tag' => [
      [],
      [],
      ['sample-off'],
      [],
      FALSE,
    ];
  }

  public function testTagsAreIgnoredWhenNoScopeIsPassed(): void {
    $context = new ConfigurableContext(['sample' => ['enabled' => TRUE]]);

    $this->assertTrue($context->getOption('sample', 'enabled'));
  }

  public function testAnOptionWithNoTagBindingIgnoresTags(): void {
    $context = new ConfigurableContext();
    $scope = $this->createBeforeScenarioScope(['sample-off']);

    $this->assertSame('a default', $context->getOption('sample', 'label', $scope));
  }

  /**
   * Tests that a context argument names what it accepts when it is wrong.
   *
   * @param array<string, mixed> $config
   *   The context's config argument.
   * @param string $expected_message
   *   The message the constructor is expected to throw with.
   */
  #[DataProvider('dataProviderStrictValidation')]
  public function testStrictValidation(array $config, string $expected_message): void {
    $this->expectException(InvalidConfigurationException::class);
    $this->expectExceptionMessage($expected_message);

    new ConfigurableContext($config);
  }

  public static function dataProviderStrictValidation(): \Iterator {
    yield 'unknown group' => [
      ['nonexistent' => ['enabled' => FALSE]],
      'Unknown option group "nonexistent" for context "' . ConfigurableContext::class . '". This context accepts: other_sample, sample.',
    ];

    yield 'unknown option' => [
      ['sample' => ['nonexistent' => FALSE]],
      'Unknown option "sample.nonexistent" for context "' . ConfigurableContext::class . '". The "sample" group accepts: enabled, label, limit.',
    ];

    yield 'a group that is not a map' => [
      ['sample' => 'off'],
      'The "sample" option group holds a map of options, but a string was given.',
    ];

    yield 'a value of the wrong type' => [
      ['sample' => ['enabled' => 'yes']],
      'The "sample.enabled" option expects a boolean, but a string was given.',
    ];

    yield 'a non-numeric value where an integer is declared' => [
      ['sample' => ['limit' => 'many']],
      'The "sample.limit" option expects an integer, but a string was given.',
    ];

    yield 'a scalar where a map is declared' => [
      ['other_sample' => ['selectors' => '.one']],
      'The "other_sample.selectors" option expects a map, but a string was given.',
    ];
  }

  /**
   * Tests that a value is read as the type its declaration defaults to.
   *
   * @param array<string, mixed> $config
   *   The context's config argument.
   * @param string $key
   *   The option to read back.
   * @param mixed $expected
   *   The value the option is expected to hold.
   */
  #[DataProvider('dataProviderValuesAreCast')]
  public function testValuesAreCast(array $config, string $key, mixed $expected): void {
    $this->assertSame($expected, (new ConfigurableContext($config))->getOption('sample', $key));
  }

  public static function dataProviderValuesAreCast(): \Iterator {
    yield 'a numeric string reads as an integer' => [['sample' => ['limit' => '12']], 'limit', 12];
    yield 'a negative numeric string reads as an integer' => [['sample' => ['limit' => '-3']], 'limit', -3];
    yield 'an integer reads as a string' => [['sample' => ['label' => 42]], 'label', '42'];
  }

  /**
   * Tests that the steps section tolerates what a context cannot serve.
   *
   * @param array<string, mixed> $steps
   *   The extension's steps section.
   */
  #[DataProvider('dataProviderPermissiveSteps')]
  public function testPermissiveSteps(array $steps): void {
    $context = new ConfigurableContext();
    $context->setParameters(['steps' => $steps]);

    $this->assertSame('a default', $context->getOption('sample', 'label'));
  }

  public static function dataProviderPermissiveSteps(): \Iterator {
    yield 'an unknown group is ignored' => [['nonexistent' => ['enabled' => FALSE]]];
    yield 'an unknown option is ignored' => [['sample' => ['nonexistent' => FALSE]]];
    yield 'an unknown group holding a scalar is ignored' => [['nonexistent' => 'off']];
  }

  /**
   * Tests that a declaration missing a required key is rejected.
   *
   * @param string $context_class
   *   The context whose declaration is malformed.
   * @param string $expected_message
   *   The message the discovery is expected to throw with.
   */
  #[DataProvider('dataProviderMalformedDeclarationIsRejected')]
  public function testMalformedDeclarationIsRejected(string $context_class, string $expected_message): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage($expected_message);

    new $context_class();
  }

  public static function dataProviderMalformedDeclarationIsRejected(): \Iterator {
    yield 'no default' => [
      MalformedConfigContext::class,
      'The "broken.label" declaration in ' . MalformedConfigContext::class . '::brokenConfigSchema() needs a "default" and a "description".',
    ];

    yield 'no description' => [
      UndocumentedConfigContext::class,
      'The "undocumented.label" declaration in ' . UndocumentedConfigContext::class . '::undocumentedConfigSchema() needs a "default" and a "description".',
    ];

    yield 'tags that are not a map' => [
      MistaggedConfigContext::class,
      'The "mistagged.enabled" declaration in ' . MistaggedConfigContext::class . '::mistaggedConfigSchema() lists its tags as a map of tag name to the value it sets.',
    ];
  }

  public function testDeclarationMethodMustReturnAnArray(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage(UntypedConfigContext::class . '::untypedConfigSchema() must return an array of option declarations.');

    new UntypedConfigContext();
  }

  public function testContextComposingNoDeclaringTraitSaysSo(): void {
    $this->expectException(InvalidConfigurationException::class);
    $this->expectExceptionMessage('Unknown option group "sample" for context "' . BareConfigContext::class . '". This context accepts: nothing.');

    new BareConfigContext(['sample' => ['enabled' => FALSE]]);
  }

  public function testContextComposingNoDeclaringTraitHasNoOptionToRead(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('declares the option "sample.enabled". Declared options: none.');

    (new BareConfigContext())->getOption('sample', 'enabled');
  }

  public function testScalarStepsSectionIsIgnored(): void {
    $context = new ConfigurableContext();
    $context->setParameters(['steps' => 'off']);

    $this->assertSame('a default', $context->getOption('sample', 'label'));
  }

  public function testMalformedGroupInStepsIsRejected(): void {
    $context = new ConfigurableContext();
    $context->setParameters(['steps' => ['sample' => 'off']]);

    $this->expectException(InvalidConfigurationException::class);
    $this->expectExceptionMessage('The "sample" option group holds a map of options, but a string was given.');

    $context->getOption('sample', 'label');
  }

  /**
   * Tests that a skip name resolves to the group that owns it.
   *
   * @param string $name
   *   The hook method name or trait name a skip tag would carry.
   * @param list<string> $tags
   *   Tags on the scenario.
   * @param array<string, mixed> $config
   *   The context's config argument.
   * @param bool $expected
   *   Whether the hook is expected to be skipped.
   */
  #[DataProvider('dataProviderSkipTag')]
  public function testSkipTag(string $name, array $tags, array $config, bool $expected): void {
    $context = new ConfigurableContext($config);

    $this->assertSame($expected, $context->callSkipTag($name, $this->createBeforeScenarioScope($tags)));
  }

  public static function dataProviderSkipTag(): \Iterator {
    yield 'no tag and no config runs the hook' => ['sampleBeforeScenario', [], [], FALSE];
    yield 'a method tag skips the hook' => ['sampleBeforeScenario', ['behat-steps-skip:sampleBeforeScenario'], [], TRUE];
    yield 'a trait tag skips the hook' => ['SampleTrait', ['behat-steps-skip:SampleTrait'], [], TRUE];
    yield 'a disabled group skips a method-named hook' => ['sampleBeforeScenario', [], ['sample' => ['enabled' => FALSE]], TRUE];
    yield 'a disabled group skips a trait-named hook' => ['SampleTrait', [], ['sample' => ['enabled' => FALSE]], TRUE];

    // The longer prefix owns the name, so the shorter group does not claim it.
    yield 'the longest matching prefix wins' => ['otherSampleBeforeScenario', [], ['sample' => ['enabled' => FALSE]], FALSE];

    // A group without an 'enabled' option contributes no switch.
    yield 'a group with no enabled option is tag-only' => ['otherSampleBeforeScenario', ['behat-steps-skip:otherSampleBeforeScenario'], [], TRUE];

    yield 'a trait with no group is tag-only' => ['NonexistentTrait', [], [], FALSE];
    yield 'a name matching no group is tag-only' => ['cleanEntities', [], [], FALSE];

    // A prefix has to be followed by a word boundary in the method name.
    yield 'a prefix that is not followed by a capital does not match' => ['sampledBeforeScenario', [], ['sample' => ['enabled' => FALSE]], FALSE];
  }

}
