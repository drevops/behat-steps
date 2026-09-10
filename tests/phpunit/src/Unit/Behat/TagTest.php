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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests reading tags produced by either Gherkin parsing mode.
 */
#[CoversClass(Tag::class)]
class TagTest extends TestCase {

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
    yield 'legacy tags are unchanged' => [['api', 'javascript'], ['api', 'javascript']];
    yield 'gherkin-32 tags lose the prefix' => [['@api', '@javascript'], ['api', 'javascript']];
    yield 'both forms in one list' => [['@api', 'javascript'], ['api', 'javascript']];
    yield 'only the first prefix is removed' => [['@@api'], ['@api']];
    yield 'a bare prefix leaves an empty tag' => [['@'], ['']];
    yield 'a prefix inside the tag is kept' => [['@email:user@example.com'], ['email:user@example.com']];
    yield 'keys are discarded' => [['first' => '@api'], ['api']];
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
    yield 'a legacy tag is found' => [['email'], 'email', TRUE];
    yield 'a gherkin-32 tag is found' => [['@email'], 'email', TRUE];
    yield 'an absent tag is not found' => [['@email'], 'download', FALSE];
    yield 'a prefixed lookup is not found' => [['@email'], '@email', FALSE];
    yield 'a tag carrying a value is matched whole' => [['@email:default'], 'email:default', TRUE];
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
