<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Listener;

use Behat\Behat\Context\Environment\ContextEnvironment;
use Behat\Behat\EventDispatcher\Event\BeforeScenarioTested;
use Behat\Behat\EventDispatcher\Event\ExampleTested;
use Behat\Behat\EventDispatcher\Event\ScenarioTested;
use Behat\Gherkin\Node\FeatureNode;
use Behat\Gherkin\Node\ScenarioNode;
use Behat\Testwork\Environment\Environment;
use Behat\Testwork\Suite\Suite;
use DrevOps\BehatSteps\Behat\Context\DrupalContext;
use DrevOps\BehatSteps\Behat\Listener\SkipTagListener;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\ConfigurableSubContext;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests that a skip tag naming anything but a composed trait fails the start.
 */
#[CoversClass(SkipTagListener::class)]
class SkipTagListenerTest extends UnitTestCase {

  /**
   * The context classes the suite under test registers.
   */
  protected const CONTEXT_CLASSES = [DrupalContext::class, ConfigurableSubContext::class];

  public function testItSubscribesToScenariosAndExamples(): void {
    $events = SkipTagListener::getSubscribedEvents();

    $this->assertSame(['validateSkipTags', 12], $events[ScenarioTested::BEFORE]);
    $this->assertSame(['validateSkipTags', 12], $events[ExampleTested::BEFORE]);
  }

  /**
   * Tests that a scenario whose skip tags name composed traits starts.
   *
   * @param list<string> $feature_tags
   *   Tags declared on the feature.
   * @param list<string> $scenario_tags
   *   Tags declared on the scenario.
   */
  #[DataProvider('dataProviderValidSkipTags')]
  public function testValidSkipTags(array $feature_tags, array $scenario_tags): void {
    $this->expectNotToPerformAssertions();

    (new SkipTagListener())->validateSkipTags($this->createEvent($feature_tags, $scenario_tags));
  }

  public static function dataProviderValidSkipTags(): \Iterator {
    yield 'no tag' => [[], []];
    yield 'tags that are not skip tags' => [['api'], ['javascript', 'email:default', 'behat-steps-entity-cleanup-skip:node']];
    yield 'a trait on the scenario' => [[], ['behat-steps-skip:EmailTrait']];
    yield 'a trait on the feature' => [['behat-steps-skip:JavascriptTrait'], []];
    yield 'several traits' => [['behat-steps-skip:WatchdogTrait'], ['behat-steps-skip:EntityLifecycleTrait', 'behat-steps-skip:AuthTrait']];
    yield 'a trait another trait composes' => [[], ['behat-steps-skip:JavascriptErrorTrait']];
    yield 'a trait a parent class composes' => [[], ['behat-steps-skip:StringTrait']];
    yield 'a trait the second context composes' => [[], ['behat-steps-skip:SampleConfigTrait']];
    yield 'a tag written with its leading "@"' => [[], ['@behat-steps-skip:EmailTrait']];
  }

  /**
   * Tests that a skip tag carrying anything but a trait name is rejected.
   *
   * @param list<string> $feature_tags
   *   Tags declared on the feature.
   * @param list<string> $scenario_tags
   *   Tags declared on the scenario.
   * @param string $expected_tag
   *   The tag the failure is expected to name, without the leading '@'.
   */
  #[DataProvider('dataProviderInvalidSkipTags')]
  public function testInvalidSkipTags(array $feature_tags, array $scenario_tags, string $expected_tag): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage(sprintf('The "@%s" tag does not name a trait. A skip tag takes the name of the trait whose hooks it switches off, as in "@behat-steps-skip:JavascriptTrait".', $expected_tag));

    (new SkipTagListener())->validateSkipTags($this->createEvent($feature_tags, $scenario_tags));
  }

  public static function dataProviderInvalidSkipTags(): \Iterator {
    yield 'a hook method on the scenario' => [[], ['behat-steps-skip:emailAfterScenario'], 'behat-steps-skip:emailAfterScenario'];
    yield 'a hook method on the feature' => [['behat-steps-skip:entityLifecycleAfterScenario'], [], 'behat-steps-skip:entityLifecycleAfterScenario'];
    yield 'a hook method beside a valid tag' => [[], ['behat-steps-skip:EmailTrait', 'behat-steps-skip:watchdogAfterStep'], 'behat-steps-skip:watchdogAfterStep'];
    yield 'a hook method written with its leading "@"' => [[], ['@behat-steps-skip:authAfterScenario'], 'behat-steps-skip:authAfterScenario'];
    yield 'a trait without its suffix' => [[], ['behat-steps-skip:Email'], 'behat-steps-skip:Email'];
    yield 'a fully qualified trait' => [[], ['behat-steps-skip:Steps\Drupal\EmailTrait'], 'behat-steps-skip:Steps\Drupal\EmailTrait'];
    yield 'a negated trait' => [[], ['behat-steps-skip:!JavascriptTrait'], 'behat-steps-skip:!JavascriptTrait'];
    yield 'the suffix alone' => [[], ['behat-steps-skip:Trait'], 'behat-steps-skip:Trait'];
    yield 'an empty value' => [[], ['behat-steps-skip:'], 'behat-steps-skip:'];
    yield 'no value at all' => [[], ['behat-steps-skip'], 'behat-steps-skip'];
  }

  /**
   * Tests that a skip tag naming a trait no context composes is rejected.
   *
   * @param list<string> $feature_tags
   *   Tags declared on the feature.
   * @param list<string> $scenario_tags
   *   Tags declared on the scenario.
   * @param string $expected_tag
   *   The tag the failure is expected to name, without the leading '@'.
   */
  #[DataProvider('dataProviderUncomposedSkipTags')]
  public function testUncomposedSkipTags(array $feature_tags, array $scenario_tags, string $expected_tag): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage(sprintf('The "@%s" tag names no trait a context of the "default" suite composes, so it would switch nothing off. Check the trait name for a typo, or remove the tag.', $expected_tag));

    (new SkipTagListener())->validateSkipTags($this->createEvent($feature_tags, $scenario_tags));
  }

  public static function dataProviderUncomposedSkipTags(): \Iterator {
    yield 'a misspelled trait on the scenario' => [[], ['behat-steps-skip:EmialTrait'], 'behat-steps-skip:EmialTrait'];
    yield 'a misspelled trait on the feature' => [['behat-steps-skip:JavascripTrait'], [], 'behat-steps-skip:JavascripTrait'];
    yield 'a misspelled trait beside a composed one' => [[], ['behat-steps-skip:EmailTrait', 'behat-steps-skip:WatchdgTrait'], 'behat-steps-skip:WatchdgTrait'];
    yield 'a trait in another case' => [[], ['behat-steps-skip:EMAILTrait'], 'behat-steps-skip:EMAILTrait'];
    yield 'a trait named with an acronym' => [[], ['behat-steps-skip:APIClientTrait'], 'behat-steps-skip:APIClientTrait'];
  }

  public function testEnvironmentWithoutContextsChecksTheShapeOnly(): void {
    $this->expectNotToPerformAssertions();

    (new SkipTagListener())->validateSkipTags($this->createEvent([], ['behat-steps-skip:EmialTrait'], $this->createStub(Environment::class)));
  }

  public function testListenerChecksAnotherScenarioOfTheSameSuite(): void {
    $listener = new SkipTagListener();
    $listener->validateSkipTags($this->createEvent([], ['behat-steps-skip:EmailTrait']));

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The "@behat-steps-skip:EmialTrait" tag names no trait a context of the "default" suite composes');

    $listener->validateSkipTags($this->createEvent([], ['behat-steps-skip:EmialTrait']));
  }

  /**
   * Builds the event Behat dispatches before a scenario or an example.
   *
   * @param list<string> $feature_tags
   *   Tags declared on the feature.
   * @param list<string> $scenario_tags
   *   Tags declared on the scenario.
   * @param \Behat\Testwork\Environment\Environment|null $environment
   *   The environment, or NULL for one whose "default" suite registers the
   *   context classes in 'CONTEXT_CLASSES'.
   */
  protected function createEvent(array $feature_tags, array $scenario_tags, ?Environment $environment = NULL): BeforeScenarioTested {
    $scenario = new ScenarioNode('Scenario', $scenario_tags, [], 'Scenario', 2);
    $feature = new FeatureNode('Feature', NULL, $feature_tags, NULL, [$scenario], 'Feature', 'en', NULL, 1);

    return new BeforeScenarioTested($environment ?? $this->createContextEnvironment(), $feature, $scenario);
  }

  /**
   * Builds an environment whose "default" suite registers 'CONTEXT_CLASSES'.
   */
  protected function createContextEnvironment(): ContextEnvironment {
    $suite = $this->createStub(Suite::class);
    $suite->method('getName')->willReturn('default');

    $environment = $this->createStub(ContextEnvironment::class);
    $environment->method('getContextClasses')->willReturn(static::CONTEXT_CLASSES);
    $environment->method('getSuite')->willReturn($suite);

    return $environment;
  }

}
