<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Listener;

use Behat\Behat\EventDispatcher\Event\BeforeScenarioTested;
use Behat\Behat\EventDispatcher\Event\ExampleTested;
use Behat\Behat\EventDispatcher\Event\ScenarioTested;
use Behat\Gherkin\Node\ExampleTableNode;
use Behat\Gherkin\Node\FeatureNode;
use Behat\Gherkin\Node\OutlineNode;
use Behat\Gherkin\Node\ScenarioNode;
use Behat\Testwork\Environment\Environment;
use DrevOps\BehatSteps\Backend\BackendInterface;
use DrevOps\BehatSteps\Behat\Listener\BackendListener;
use DrevOps\BehatSteps\Behat\Manager\BackendRegistryInterface;
use DrevOps\BehatSteps\Behat\Manager\ScenarioTagRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests how the configured list and a scenario's tags build the backend order.
 */
#[CoversClass(BackendListener::class)]
class BackendListenerTest extends TestCase {

  /**
   * The backend list the extension configuration declares, in order.
   */
  protected const BACKENDS = ['drupal', 'drush', 'blackbox'];

  /**
   * The registry the listener publishes the scenario's tags to.
   */
  protected ScenarioTagRegistry $scenarioTagRegistry;

  protected function setUp(): void {
    parent::setUp();

    $this->scenarioTagRegistry = new ScenarioTagRegistry();
  }

  public function testItSubscribesToScenariosAndExamples(): void {
    $events = BackendListener::getSubscribedEvents();

    $this->assertSame(['prepareScenarioBackends', 11], $events[ScenarioTested::BEFORE]);
    $this->assertSame(['prepareScenarioBackends', 11], $events[ExampleTested::BEFORE]);
  }

  /**
   * Tests the order a set of feature and scenario tags produces.
   *
   * @param list<string> $feature_tags
   *   Tags declared on the feature.
   * @param list<string> $scenario_tags
   *   Tags declared on the scenario.
   * @param array<string, string> $expected
   *   The order the registry is expected to receive.
   */
  #[DataProvider('dataProviderBackendOrder')]
  public function testBackendOrder(array $feature_tags, array $scenario_tags, array $expected): void {
    $backend_registry = $this->createMock(BackendRegistryInterface::class);
    $backend_registry->expects($this->once())->method('setScenarioBackends')->with($this->identicalTo($expected));

    $listener = new BackendListener($backend_registry, $this->scenarioTagRegistry, self::BACKENDS);
    $listener->prepareScenarioBackends($this->createEvent($feature_tags, $scenario_tags));
  }

  public static function dataProviderBackendOrder(): \Iterator {
    yield 'no tag keeps the configured order' => [
      [],
      [],
      ['drupal' => 'drupal', 'drush' => 'drush', 'blackbox' => 'blackbox'],
    ];
    yield 'a scenario tag moves its backend to the front' => [
      [],
      ['backend:drush'],
      ['drush' => 'drush', 'drupal' => 'drupal', 'blackbox' => 'blackbox'],
    ];
    yield 'a feature tag moves its backend to the front' => [
      ['backend:blackbox'],
      [],
      ['blackbox' => 'blackbox', 'drupal' => 'drupal', 'drush' => 'drush'],
    ];
    yield 'repeated scenario tags keep the configured order' => [
      [],
      ['backend:blackbox', 'backend:drush'],
      ['drush' => 'drush', 'blackbox' => 'blackbox', 'drupal' => 'drupal'],
    ];
    yield 'repeated scenario tags in reverse give the same order' => [
      [],
      ['backend:drush', 'backend:blackbox'],
      ['drush' => 'drush', 'blackbox' => 'blackbox', 'drupal' => 'drupal'],
    ];
    yield 'repeated feature tags keep the configured order' => [
      ['backend:blackbox', 'backend:drush'],
      [],
      ['drush' => 'drush', 'blackbox' => 'blackbox', 'drupal' => 'drupal'],
    ];
    yield 'a scenario tag is promoted ahead of a feature tag' => [
      ['backend:drush'],
      ['backend:blackbox'],
      ['blackbox' => 'blackbox', 'drush' => 'drush', 'drupal' => 'drupal'],
    ];
    yield 'scenario tags keep the configured order ahead of a feature tag' => [
      ['backend:drush'],
      ['backend:blackbox', 'backend:drupal'],
      ['drupal' => 'drupal', 'blackbox' => 'blackbox', 'drush' => 'drush'],
    ];
    yield 'a name on both lines is promoted with the scenario' => [
      ['backend:drush', 'backend:blackbox'],
      ['backend:blackbox'],
      ['blackbox' => 'blackbox', 'drush' => 'drush', 'drupal' => 'drupal'],
    ];
    yield 'a repeated name is promoted once' => [
      ['backend:drush'],
      ['backend:drush'],
      ['drush' => 'drush', 'drupal' => 'drupal', 'blackbox' => 'blackbox'],
    ];
    yield 'a name repeated on one line is promoted once' => [
      [],
      ['backend:drush', 'backend:drush'],
      ['drush' => 'drush', 'drupal' => 'drupal', 'blackbox' => 'blackbox'],
    ];
    yield 'a tag that is not a backend tag is ignored' => [
      [],
      ['javascript'],
      ['drupal' => 'drupal', 'drush' => 'drush', 'blackbox' => 'blackbox'],
    ];
    yield 'a bare driver tag is ignored' => [
      [],
      ['driver'],
      ['drupal' => 'drupal', 'drush' => 'drush', 'blackbox' => 'blackbox'],
    ];
  }

  public function testExampleRanksOutlineAndTableTagsTogether(): void {
    $table = new ExampleTableNode([1 => ['name'], 2 => ['value']], 'Examples', ['backend:drush']);
    $outline = new OutlineNode('Outline', ['backend:blackbox'], [], $table, 'Scenario Outline', 2);
    $feature = new FeatureNode('Feature', NULL, [], NULL, [$outline], 'Feature', 'en', NULL, 1);
    $event = new BeforeScenarioTested($this->createMock(Environment::class), $feature, $outline->getExamples()[0]);

    $backend_registry = $this->createMock(BackendRegistryInterface::class);
    $backend_registry->expects($this->once())->method('setScenarioBackends')->with($this->identicalTo(['drush' => 'drush', 'blackbox' => 'blackbox', 'drupal' => 'drupal']));

    $listener = new BackendListener($backend_registry, $this->scenarioTagRegistry, self::BACKENDS);
    $listener->prepareScenarioBackends($event);
  }

  public function testAnAliasedEntryNamesTheBackendBehindIt(): void {
    $backend_registry = $this->createMock(BackendRegistryInterface::class);
    $backend_registry->expects($this->once())->method('setScenarioBackends')->with($this->identicalTo(['api' => 'drupal', 'blackbox' => 'blackbox']));

    $listener = new BackendListener($backend_registry, $this->scenarioTagRegistry, ['blackbox', 'api' => 'drupal']);
    $listener->prepareScenarioBackends($this->createEvent([], ['backend:api']));
  }

  public function testTagNameIsMatchedWithoutRegardToCase(): void {
    $backend_registry = $this->createMock(BackendRegistryInterface::class);
    $backend_registry->expects($this->once())->method('setScenarioBackends')->with($this->identicalTo(['api' => 'drupal', 'blackbox' => 'blackbox']));

    $listener = new BackendListener($backend_registry, $this->scenarioTagRegistry, ['API' => 'drupal', 'blackbox']);
    $listener->prepareScenarioBackends($this->createEvent([], ['backend:API']));
  }

  public function testConfigurationWithoutListGetsEveryRegisteredBackend(): void {
    $backend_registry = $this->createMock(BackendRegistryInterface::class);
    $backend_registry->method('getBackends')->willReturn([
      'blackbox' => $this->createMock(BackendInterface::class),
      'drupal' => $this->createMock(BackendInterface::class),
    ]);
    $backend_registry->expects($this->once())->method('setScenarioBackends')->with($this->identicalTo(['blackbox' => 'blackbox', 'drupal' => 'drupal']));

    $listener = new BackendListener($backend_registry, $this->scenarioTagRegistry);
    $listener->prepareScenarioBackends($this->createEvent([], []));
  }

  /**
   * Tests that a tag naming a backend outside the configured list is reported.
   *
   * @param list<string> $feature_tags
   *   Tags declared on the feature.
   * @param list<string> $scenario_tags
   *   Tags declared on the scenario.
   * @param string $expected_tag
   *   The tag the message is expected to name.
   */
  #[DataProvider('dataProviderTagNamingUnlistedBackendIsReported')]
  public function testTagNamingUnlistedBackendIsReported(array $feature_tags, array $scenario_tags, string $expected_tag): void {
    $listener = new BackendListener($this->createMock(BackendRegistryInterface::class), $this->scenarioTagRegistry, self::BACKENDS);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage(sprintf('The "%s" tag names a backend that the configured backend list does not hold. Configured backends: drupal, drush, blackbox. The tag reorders that list; it never adds to it.', $expected_tag));

    $listener->prepareScenarioBackends($this->createEvent($feature_tags, $scenario_tags));
  }

  public static function dataProviderTagNamingUnlistedBackendIsReported(): \Iterator {
    yield 'on the scenario line' => [[], ['backend:typo'], '@backend:typo'];
    yield 'on the feature line' => [['backend:typo'], [], '@backend:typo'];
    yield 'beside a listed name' => [[], ['backend:drush', 'backend:typo'], '@backend:typo'];
    yield 'with no name' => [[], ['backend:'], '@backend:'];
  }

  /**
   * Tests that a '@driver:' tag fails, naming the '@backend:' tag to use.
   *
   * @param list<string> $feature_tags
   *   Tags declared on the feature.
   * @param list<string> $scenario_tags
   *   Tags declared on the scenario.
   * @param string $expected_message
   *   The message the scenario is expected to fail with.
   */
  #[DataProvider('dataProviderReplacedTagIsReported')]
  public function testReplacedTagIsReported(array $feature_tags, array $scenario_tags, string $expected_message): void {
    $backend_registry = $this->createMock(BackendRegistryInterface::class);
    $backend_registry->expects($this->never())->method('setScenarioBackends');

    $listener = new BackendListener($backend_registry, $this->scenarioTagRegistry, self::BACKENDS);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage($expected_message);

    $listener->prepareScenarioBackends($this->createEvent($feature_tags, $scenario_tags));
  }

  public static function dataProviderReplacedTagIsReported(): \Iterator {
    yield 'on the scenario line' => [[], ['driver:drush'], 'The "@driver:drush" tag moved to "@backend:drush". Rename the tag; the name it carries is unchanged.'];
    yield 'on the feature line' => [['driver:blackbox'], [], 'The "@driver:blackbox" tag moved to "@backend:blackbox". Rename the tag; the name it carries is unchanged.'];
    yield 'beside a backend tag' => [[], ['backend:drush', 'driver:drupal'], 'The "@driver:drupal" tag moved to "@backend:drupal".'];
    yield 'naming a backend the list does not hold' => [[], ['driver:typo'], 'The "@driver:typo" tag moved to "@backend:typo".'];
    yield 'naming no backend' => [[], ['driver:'], 'The "@driver:" tag moved to "@backend:".'];
  }

  public function testTheEnvironmentIsHandedToTheRegistry(): void {
    $event = $this->createEvent([], []);

    $backend_registry = $this->createMock(BackendRegistryInterface::class);
    $backend_registry->expects($this->once())->method('setEnvironment')->with($event->getEnvironment());

    $listener = new BackendListener($backend_registry, $this->scenarioTagRegistry, self::BACKENDS);
    $listener->prepareScenarioBackends($event);
  }

  /**
   * Tests that the scenario's tags reach the registry, feature tags first.
   */
  public function testTheScenarioTagsArePublished(): void {
    $listener = new BackendListener($this->createMock(BackendRegistryInterface::class), $this->scenarioTagRegistry, self::BACKENDS);

    $listener->prepareScenarioBackends($this->createEvent(['api'], ['javascript', 'error']));

    $this->assertSame(['api', 'javascript', 'error'], $this->scenarioTagRegistry->getTags());
  }

  public function testTheTagsOfOneScenarioDoNotLeakIntoTheNext(): void {
    $listener = new BackendListener($this->createMock(BackendRegistryInterface::class), $this->scenarioTagRegistry, self::BACKENDS);

    $listener->prepareScenarioBackends($this->createEvent([], ['error']));
    $listener->prepareScenarioBackends($this->createEvent([], []));

    $this->assertSame([], $this->scenarioTagRegistry->getTags());
  }

  /**
   * Builds the event Behat dispatches before a scenario or an example.
   *
   * @param list<string> $feature_tags
   *   Tags declared on the feature.
   * @param list<string> $scenario_tags
   *   Tags declared on the scenario.
   */
  protected function createEvent(array $feature_tags, array $scenario_tags): BeforeScenarioTested {
    $scenario = new ScenarioNode('Scenario', $scenario_tags, [], 'Scenario', 2);
    $feature = new FeatureNode('Feature', NULL, $feature_tags, NULL, [$scenario], 'Feature', 'en', NULL, 1);

    return new BeforeScenarioTested($this->createMock(Environment::class), $feature, $scenario);
  }

}
