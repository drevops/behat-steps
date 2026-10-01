<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat;

use Behat\Behat\EventDispatcher\Event\BeforeScenarioTested;
use Behat\Behat\Hook\Scope\ScenarioScope;
use Behat\Gherkin\Node\FeatureNode;
use Behat\Gherkin\Node\ScenarioLikeInterface;
use Behat\Gherkin\Node\ScenarioNode;
use Behat\Testwork\Environment\Environment;
use DrevOps\BehatSteps\Behat\Tag;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests reading tags as Behat 3 and Behat 4 report them.
 */
#[CoversClass(Tag::class)]
class TagTest extends UnitTestCase {

  /**
   * Tests the form a list of tags is reduced to.
   *
   * @param list<string> $tags
   *   Tags as a parser would produce them.
   * @param list<string> $expected
   *   The tags expected after normalization.
   */
  #[DataProvider('dataProviderNormalize')]
  public function testNormalize(array $tags, array $expected): void {
    $this->assertSame($expected, Tag::normalize($tags));
  }

  public static function dataProviderNormalize(): \Iterator {
    yield 'no tags' => [[], []];
    yield 'Behat 3 tags are unchanged' => [['api', 'javascript'], ['api', 'javascript']];
    yield 'Behat 4 tags lose the prefix' => [['@api', '@javascript'], ['api', 'javascript']];
    yield 'both forms in one list' => [['@api', 'javascript'], ['api', 'javascript']];
    yield 'only the first prefix is removed' => [['@@api'], ['@api']];
    yield 'a bare prefix leaves an empty tag' => [['@'], ['']];
    yield 'a prefix inside the tag is kept' => [['@email:user@example.com'], ['email:user@example.com']];
    yield 'keys are discarded' => [['first' => '@api'], ['api']];
  }

  /**
   * Tests the form a single node's tags are read in.
   *
   * @param list<string> $tags
   *   Tags declared on the node.
   * @param list<string> $expected
   *   The tags expected after normalization.
   */
  #[DataProvider('dataProviderOn')]
  public function testOn(array $tags, array $expected): void {
    $this->assertSame($expected, Tag::on(new ScenarioNode('Scenario', $tags, [], 'Scenario', 2)));
  }

  public static function dataProviderOn(): \Iterator {
    yield 'an untagged node' => [[], []];
    yield 'Behat 3 tags are unchanged' => [['api', 'email'], ['api', 'email']];
    yield 'Behat 4 tags lose the prefix' => [['@api', '@email'], ['api', 'email']];
  }

  /**
   * Tests whether a node is found to carry a tag.
   *
   * @param list<string> $tags
   *   Tags declared on the node.
   * @param string $tag
   *   The tag to look for.
   * @param bool $expected
   *   Whether the node is expected to carry the tag.
   */
  #[DataProvider('dataProviderHas')]
  public function testHas(array $tags, string $tag, bool $expected): void {
    $this->assertSame($expected, Tag::has(new ScenarioNode('Scenario', $tags, [], 'Scenario', 2), $tag));
  }

  public static function dataProviderHas(): \Iterator {
    yield 'a Behat 3 tag is found' => [['email'], 'email', TRUE];
    yield 'a Behat 4 tag is found' => [['@email'], 'email', TRUE];
    yield 'an absent tag is not found' => [['@email'], 'download', FALSE];
    yield 'a prefixed lookup is not found' => [['@email'], '@email', FALSE];
    yield 'a tag carrying a value is matched whole' => [['@email:default'], 'email:default', TRUE];
  }

  /**
   * Tests whether a scenario scope is found to carry a tag on either line.
   *
   * @param list<string> $scenario_tags
   *   Tags on the scenario.
   * @param list<string> $feature_tags
   *   Tags on the feature.
   * @param string $tag
   *   The tag to look for.
   * @param bool $expected
   *   Whether the scenario or its feature is expected to carry the tag.
   */
  #[DataProvider('dataProviderHasOnScope')]
  public function testHasOnScope(array $scenario_tags, array $feature_tags, string $tag, bool $expected): void {
    $this->assertSame($expected, Tag::has($this->createBeforeScenarioScope($scenario_tags, $feature_tags), $tag));
  }

  public static function dataProviderHasOnScope(): \Iterator {
    yield 'on the scenario' => [['testmode'], [], 'testmode', TRUE];
    yield 'on the feature' => [[], ['testmode'], 'testmode', TRUE];
    yield 'on both' => [['testmode'], ['testmode'], 'testmode', TRUE];
    yield 'a Behat 4 tag on the feature' => [[], ['@testmode'], 'testmode', TRUE];
    yield 'on neither' => [['api'], ['javascript'], 'testmode', FALSE];
    yield 'a tag carrying a value is not the bare tag' => [['email:default'], [], 'email', FALSE];
  }

  /**
   * Tests the values a parametrized tag carries on a scenario and its feature.
   *
   * @param list<string> $scenario_tags
   *   Tags on the scenario.
   * @param list<string> $feature_tags
   *   Tags on the feature.
   * @param string $name
   *   The tag name to collect the values of.
   * @param list<string> $expected
   *   The values expected, in order.
   */
  #[DataProvider('dataProviderValues')]
  public function testValues(array $scenario_tags, array $feature_tags, string $name, array $expected): void {
    $this->assertSame($expected, Tag::values($this->createBeforeScenarioScope($scenario_tags, $feature_tags), $name));
  }

  public static function dataProviderValues(): \Iterator {
    yield 'no tags' => [[], [], 'watchdog', []];
    yield 'on the scenario' => [['watchdog:php', 'watchdog:cron'], [], 'watchdog', ['php', 'cron']];
    yield 'on the feature' => [[], ['watchdog:php'], 'watchdog', ['php']];
    yield 'feature values come first' => [['watchdog:cron'], ['watchdog:php'], 'watchdog', ['php', 'cron']];
    yield 'a repeated value is kept' => [['watchdog:php'], ['watchdog:php'], 'watchdog', ['php', 'php']];
    yield 'Behat 4 tags' => [['@watchdog:php'], ['@watchdog:cron'], 'watchdog', ['cron', 'php']];
    yield 'a value keeps a separator of its own' => [['email:user@example.com:8080'], [], 'email', ['user@example.com:8080']];
    yield 'a value keeps its negation' => [['module:!help'], [], 'module', ['!help']];
    yield 'an empty value is skipped' => [['watchdog:'], [], 'watchdog', []];
    yield 'the bare tag carries no value' => [['watchdog'], [], 'watchdog', []];
    yield 'another tag sharing the start is not read' => [['watchdogs:php', 'my-watchdog:php'], [], 'watchdog', []];
  }

  public function testValuesOfOneNode(): void {
    [$feature, $scenario] = $this->createScenarioNodes(['breakpoint:mobile_portrait'], ['breakpoint:desktop']);

    $this->assertSame(['mobile_portrait'], Tag::values($scenario, 'breakpoint'));
    $this->assertSame(['desktop'], Tag::values($feature, 'breakpoint'));
  }

  public function testValuesFromEvent(): void {
    [$feature, $scenario] = $this->createScenarioNodes(['driver:drush'], ['driver:blackbox']);
    $event = new BeforeScenarioTested($this->createMock(Environment::class), $feature, $scenario);

    $this->assertSame(['blackbox', 'drush'], Tag::values($event, 'driver'));
  }

  /**
   * Tests the on/off state each value of a parametrized tag resolves to.
   *
   * @param list<string> $scenario_tags
   *   Tags on the scenario.
   * @param list<string> $feature_tags
   *   Tags on the feature.
   * @param array<string, bool> $expected
   *   The state expected for each value.
   */
  #[DataProvider('dataProviderValueStates')]
  public function testValueStates(array $scenario_tags, array $feature_tags, array $expected): void {
    $this->assertSame($expected, Tag::valueStates($this->createBeforeScenarioScope($scenario_tags, $feature_tags), 'module'));
  }

  public static function dataProviderValueStates(): \Iterator {
    yield 'no tags' => [[], [], []];
    yield 'unrelated tags' => [['api', 'modules:help'], [], []];
    yield 'switched on' => [['module:help'], [], ['help' => TRUE]];
    yield 'switched off' => [['module:!help'], [], ['help' => FALSE]];
    yield 'several values' => [['module:help', 'module:!contextual'], [], ['help' => TRUE, 'contextual' => FALSE]];
    yield 'a repeated tag' => [['module:help', 'module:help'], [], ['help' => TRUE]];
    yield 'a later tag replaces an earlier one' => [['module:help', 'module:!help'], [], ['help' => FALSE]];
    yield 'the feature applies without a scenario tag' => [[], ['module:help'], ['help' => TRUE]];
    yield 'the scenario overrides the feature' => [['module:!help'], ['module:help'], ['help' => FALSE]];
    yield 'the first mention keeps its position' => [['module:!help'], ['module:help', 'module:syslog'], ['help' => FALSE, 'syslog' => TRUE]];
    yield 'a bare negation is skipped' => [['module:!'], [], []];
    yield 'Behat 4 tags' => [['@module:!help'], ['@module:help'], ['help' => FALSE]];
  }

  public function testAllMergesFeatureAndScenarioTagsFromScope(): void {
    $scenario = new ScenarioNode('Scenario', ['@email'], [], 'Scenario', 2);
    $feature = new FeatureNode('Feature', NULL, ['@api'], NULL, [$scenario], 'Feature', 'en', NULL, 1);

    $scope = $this->createMock(ScenarioScope::class);
    $scope->method('getFeature')->willReturn($feature);
    $scope->method('getScenario')->willReturn($scenario);

    $this->assertSame(['api', 'email'], Tag::all($scope));
  }

  public function testAllMergesFeatureAndScenarioTagsFromEvent(): void {
    $scenario = new ScenarioNode('Scenario', ['@email'], [], 'Scenario', 2);
    $feature = new FeatureNode('Feature', NULL, ['@api'], NULL, [$scenario], 'Feature', 'en', NULL, 1);
    $event = new BeforeScenarioTested($this->createMock(Environment::class), $feature, $scenario);

    $this->assertSame(['api', 'email'], Tag::all($event));
  }

  public function testAllReadsFeatureTagsWhenTheScenarioCarriesNone(): void {
    $feature = new FeatureNode('Feature', NULL, ['@api'], NULL, [], 'Feature', 'en', NULL, 1);
    $event = new BeforeScenarioTested(
      $this->createMock(Environment::class),
      $feature,
      $this->createMock(ScenarioLikeInterface::class)
    );

    $this->assertSame(['api'], Tag::all($event));
  }

}
