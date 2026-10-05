<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Context;

use DrevOps\BehatSteps\Behat\Config\ConfigSchemaReader;
use DrevOps\BehatSteps\Behat\Config\TagOverrides;
use DrevOps\BehatSteps\Behat\Config\TraitOptionResolverFactory;
use DrevOps\BehatSteps\Behat\Context\WebRawContext;
use DrevOps\BehatSteps\Behat\Registry\ScenarioTagRegistry;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\BareConfigContext;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\ConfigurableContext;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\ConfigurableSubContext;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\UnforwardedConfigContext;
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

  public function testContextThatDoesNotForwardItsConstructorSaysSo(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage(UnforwardedConfigContext::class . ' declares a constructor that does not call parent::__construct(), so its "config" argument was never set.');

    (new UnforwardedConfigContext())->getOption('sample', 'label');
  }

  public function testContextComposingNoDeclaringTraitHasNoOptionToRead(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('declares the option "sample.enabled". Declared options: none.');

    (new BareConfigContext())->getOption('sample', 'enabled');
  }

  /**
   * Tests whether the tags and the configuration switch a trait's hooks off.
   *
   * @param string $trait
   *   The trait name a hook passes, fully qualified or short.
   * @param list<string> $tags
   *   Tags on the scenario.
   * @param array<string, mixed> $config
   *   The context's config argument.
   * @param bool $expected
   *   Whether the hook is expected to be skipped.
   */
  #[DataProvider('dataProviderSkipTag')]
  public function testSkipTag(string $trait, array $tags, array $config, bool $expected): void {
    $context = new ConfigurableContext($config);
    $registry = new ScenarioTagRegistry();
    $registry->setTags($tags);

    $context->setOptionResolverFactory(new TraitOptionResolverFactory(new ConfigSchemaReader(), $registry, new TagOverrides()));

    $this->assertSame($expected, $context->callSkipTag($trait, $this->createBeforeScenarioScope($tags)));
  }

  public static function dataProviderSkipTag(): \Iterator {
    yield 'no tag and no config runs the hook' => ['SampleTrait', [], [], FALSE];
    yield 'the trait tag skips the hook' => ['SampleTrait', ['behat-steps-skip:SampleTrait'], [], TRUE];
    yield 'a fully qualified trait reads the short tag' => ['Acme\\Behat\\SampleTrait', ['behat-steps-skip:SampleTrait'], [], TRUE];
    yield 'a disabled group skips the hook' => ['SampleTrait', [], ['sample' => ['enabled' => FALSE]], TRUE];
    yield 'a hook tag does not skip the hook' => ['SampleTrait', ['behat-steps-skip:sampleBeforeScenario'], [], FALSE];
    yield 'another trait tag does not skip the hook' => ['SampleTrait', ['behat-steps-skip:SampleExtraTrait'], [], FALSE];

    // 'SampleExtraTrait' starts with 'Sample', and each trait maps to its own
    // group in both directions.
    yield 'a disabled group skips its own trait' => ['SampleExtraTrait', [], ['sample_extra' => ['enabled' => FALSE]], TRUE];
    yield 'a disabled group leaves a trait extending its name running' => ['SampleExtraTrait', [], ['sample' => ['enabled' => FALSE]], FALSE];

    // A group without an 'enabled' option contributes no switch.
    yield 'a trait whose group has no enabled option is tag-only' => ['OtherSampleTrait', ['behat-steps-skip:OtherSampleTrait'], [], TRUE];

    yield 'a trait with no group runs without its tag' => ['NonexistentTrait', [], [], FALSE];
    yield 'a trait with no group is skipped by its tag' => ['NonexistentTrait', ['behat-steps-skip:NonexistentTrait'], [], TRUE];
  }

}
