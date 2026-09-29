<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Context;

use DrevOps\BehatSteps\Behat\Config\ConfigSchemaReader;
use DrevOps\BehatSteps\Behat\Config\TagOverrides;
use DrevOps\BehatSteps\Behat\Config\TraitOptionResolverFactory;
use DrevOps\BehatSteps\Behat\Context\WebRawContext;
use DrevOps\BehatSteps\Behat\Manager\ScenarioTagRegistry;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\BareConfigContext;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\ConfigurableContext;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\ConfigurableSubContext;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * Tests the option reading a context delegates to its resolver.
 */
#[CoversClass(WebRawContext::class)]
class ContextConfigTest extends UnitTestCase {

  public function testEachTypedReaderReturnsItsDeclaredDefault(): void {
    $context = new ConfigurableContext();

    $this->assertTrue($context->getOptionBool('sample', 'enabled'));
    $this->assertSame('a default', $context->getOptionString('sample', 'label'));
    $this->assertSame(7, $context->getOptionInt('sample', 'limit'));
    $this->assertSame(0.5, $context->getOptionFloat('sample', 'ratio'));
    $this->assertSame(['.one', '.two'], $context->getOptionArray('other_sample', 'selectors'));
    $this->assertNull($context->getOption('sample', 'anything'));
  }

  public function testSubclassInheritsTheConstructor(): void {
    $context = new ConfigurableSubContext(['sample' => ['label' => 'inherited']]);

    $this->assertSame('inherited', $context->getOptionString('sample', 'label'));
  }

  public function testTheConfigArgumentIsValidatedWhileTheContextIsBuilt(): void {
    $this->expectException(InvalidConfigurationException::class);
    $this->expectExceptionMessage('Unknown option group "nonexistent" for context "' . ConfigurableContext::class . '".');

    new ConfigurableContext(['nonexistent' => ['enabled' => FALSE]]);
  }

  public function testAnUndeclaredOptionIsRejectedOnRead(): void {
    $context = new ConfigurableContext();

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('No trait in ' . ConfigurableContext::class . ' declares the option "sample.missing".');

    $context->getOption('sample', 'missing');
  }

  public function testTheStepsSectionIsReadWhenTheParametersArrive(): void {
    $context = new ConfigurableContext();

    $this->assertSame('a default', $context->getOptionString('sample', 'label'));

    $context->setParameters(['steps' => ['sample' => ['label' => 'from the profile']]]);

    $this->assertSame('from the profile', $context->getOptionString('sample', 'label'));
  }

  public function testScalarStepsSectionIsIgnored(): void {
    $context = new ConfigurableContext();
    $context->setParameters(['steps' => 'off']);

    $this->assertSame('a default', $context->getOptionString('sample', 'label'));
  }

  public function testTheContextArgumentBeatsTheStepsSection(): void {
    $context = new ConfigurableContext(['sample' => ['label' => 'from the argument']]);
    $context->setParameters(['steps' => ['sample' => ['label' => 'from the profile']]]);

    $this->assertSame('from the argument', $context->getOptionString('sample', 'label'));
  }

  public function testAnInjectedFactoryReplacesTheResolver(): void {
    $context = new ConfigurableContext();
    $registry = new ScenarioTagRegistry();

    $context->setOptionResolverFactory(new TraitOptionResolverFactory(new ConfigSchemaReader(), $registry, new TagOverrides()));
    $registry->setTags(['sample-off']);

    $this->assertFalse($context->getOptionBool('sample', 'enabled'));
  }

  public function testTheInjectedFactoryKeepsTheParametersAlreadySet(): void {
    $context = new ConfigurableContext();
    $context->setParameters(['steps' => ['sample' => ['label' => 'from the profile']]]);

    $context->setOptionResolverFactory(new TraitOptionResolverFactory());

    $this->assertSame('from the profile', $context->getOptionString('sample', 'label'));
  }

  public function testContextComposingNoDeclaringTraitHasNoOptionToRead(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('declares the option "sample.enabled". Declared options: none.');

    (new BareConfigContext())->getOption('sample', 'enabled');
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
    $registry = new ScenarioTagRegistry();
    $registry->setTags($tags);

    $context->setOptionResolverFactory(new TraitOptionResolverFactory(new ConfigSchemaReader(), $registry, new TagOverrides()));

    $this->assertSame($expected, $context->callSkipTag($name, $this->createBeforeScenarioScope($tags)));
  }

  public static function dataProviderSkipTag(): \Iterator {
    yield 'no tag and no config runs the hook' => ['sampleBeforeScenario', [], [], FALSE];
    yield 'a method tag skips the hook' => ['sampleBeforeScenario', ['behat-steps-skip:sampleBeforeScenario'], [], TRUE];
    yield 'a trait tag skips the hook' => ['SampleTrait', ['behat-steps-skip:SampleTrait'], [], TRUE];
    yield 'a trait tag skips a method-named hook of the same trait' => ['sampleBeforeScenario', ['behat-steps-skip:SampleTrait'], [], TRUE];
    yield 'a disabled group skips a method-named hook' => ['sampleBeforeScenario', [], ['sample' => ['enabled' => FALSE]], TRUE];
    yield 'a disabled group skips a trait-named hook' => ['SampleTrait', [], ['sample' => ['enabled' => FALSE]], TRUE];

    // 'sampleExtraBeforeScenario' matches both 'sample' and 'sample_extra', so
    // the longer group owns the name and the shorter one does not.
    yield 'the longest matching prefix owns the hook' => ['sampleExtraBeforeScenario', [], ['sample_extra' => ['enabled' => FALSE]], TRUE];
    yield 'the shorter prefix does not claim a longer group' => ['sampleExtraBeforeScenario', [], ['sample' => ['enabled' => FALSE]], FALSE];

    // A group without an 'enabled' option contributes no switch.
    yield 'a group with no enabled option is tag-only' => ['otherSampleBeforeScenario', ['behat-steps-skip:otherSampleBeforeScenario'], [], TRUE];

    yield 'a trait with no group is tag-only' => ['NonexistentTrait', [], [], FALSE];
    yield 'a name matching no group is tag-only' => ['entityLifecycleCleanAll', [], [], FALSE];
  }

}
