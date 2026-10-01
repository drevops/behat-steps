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
use DrevOps\BehatSteps\Behat\Listener\DriverListener;
use DrevOps\BehatSteps\Behat\Manager\DriverRegistryInterface;
use DrevOps\BehatSteps\Behat\Manager\ScenarioTagRegistry;
use DrevOps\BehatSteps\Driver\DriverInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests how the configured list and a scenario's tags build the driver order.
 */
#[CoversClass(DriverListener::class)]
class DriverListenerTest extends TestCase {

  /**
   * The driver list the extension configuration declares, in order.
   */
  protected const DRIVERS = ['drupal', 'drush', 'blackbox'];

  /**
   * The registry the listener publishes the scenario's tags to.
   */
  protected ScenarioTagRegistry $scenarioTags;

  protected function setUp(): void {
    parent::setUp();

    $this->scenarioTags = new ScenarioTagRegistry();
  }

  public function testItSubscribesToScenariosAndExamples(): void {
    $events = DriverListener::getSubscribedEvents();

    $this->assertSame(['prepareScenarioDrivers', 11], $events[ScenarioTested::BEFORE]);
    $this->assertSame(['prepareScenarioDrivers', 11], $events[ExampleTested::BEFORE]);
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
  #[DataProvider('dataProviderDriverOrder')]
  public function testDriverOrder(array $feature_tags, array $scenario_tags, array $expected): void {
    $driver_registry = $this->createMock(DriverRegistryInterface::class);
    $driver_registry->expects($this->once())->method('setScenarioDrivers')->with($this->identicalTo($expected));

    $listener = new DriverListener($driver_registry, $this->scenarioTags, self::DRIVERS);
    $listener->prepareScenarioDrivers($this->createEvent($feature_tags, $scenario_tags));
  }

  public static function dataProviderDriverOrder(): \Iterator {
    yield 'no tag keeps the configured order' => [
      [],
      [],
      ['drupal' => 'drupal', 'drush' => 'drush', 'blackbox' => 'blackbox'],
    ];
    yield 'a scenario tag moves its driver to the front' => [
      [],
      ['driver:drush'],
      ['drush' => 'drush', 'drupal' => 'drupal', 'blackbox' => 'blackbox'],
    ];
    yield 'a feature tag moves its driver to the front' => [
      ['driver:blackbox'],
      [],
      ['blackbox' => 'blackbox', 'drupal' => 'drupal', 'drush' => 'drush'],
    ];
    yield 'repeated scenario tags keep the configured order' => [
      [],
      ['driver:blackbox', 'driver:drush'],
      ['drush' => 'drush', 'blackbox' => 'blackbox', 'drupal' => 'drupal'],
    ];
    yield 'repeated scenario tags in reverse give the same order' => [
      [],
      ['driver:drush', 'driver:blackbox'],
      ['drush' => 'drush', 'blackbox' => 'blackbox', 'drupal' => 'drupal'],
    ];
    yield 'repeated feature tags keep the configured order' => [
      ['driver:blackbox', 'driver:drush'],
      [],
      ['drush' => 'drush', 'blackbox' => 'blackbox', 'drupal' => 'drupal'],
    ];
    yield 'a scenario tag is promoted ahead of a feature tag' => [
      ['driver:drush'],
      ['driver:blackbox'],
      ['blackbox' => 'blackbox', 'drush' => 'drush', 'drupal' => 'drupal'],
    ];
    yield 'scenario tags keep the configured order ahead of a feature tag' => [
      ['driver:drush'],
      ['driver:blackbox', 'driver:drupal'],
      ['drupal' => 'drupal', 'blackbox' => 'blackbox', 'drush' => 'drush'],
    ];
    yield 'a name on both lines is promoted with the scenario' => [
      ['driver:drush', 'driver:blackbox'],
      ['driver:blackbox'],
      ['blackbox' => 'blackbox', 'drush' => 'drush', 'drupal' => 'drupal'],
    ];
    yield 'a repeated name is promoted once' => [
      ['driver:drush'],
      ['driver:drush'],
      ['drush' => 'drush', 'drupal' => 'drupal', 'blackbox' => 'blackbox'],
    ];
    yield 'a name repeated on one line is promoted once' => [
      [],
      ['driver:drush', 'driver:drush'],
      ['drush' => 'drush', 'drupal' => 'drupal', 'blackbox' => 'blackbox'],
    ];
    yield 'a tag that is not a driver tag is ignored' => [
      [],
      ['javascript'],
      ['drupal' => 'drupal', 'drush' => 'drush', 'blackbox' => 'blackbox'],
    ];
  }

  /**
   * Tests that an example's outline tags and table tags rank together.
   *
   * Gherkin merges the outline's tags and the 'Examples:' table's tags into the
   * example's own list, outline first.
   */
  public function testExampleRanksOutlineAndTableTagsTogether(): void {
    $table = new ExampleTableNode([1 => ['name'], 2 => ['value']], 'Examples', ['driver:drush']);
    $outline = new OutlineNode('Outline', ['driver:blackbox'], [], $table, 'Scenario Outline', 2);
    $feature = new FeatureNode('Feature', NULL, [], NULL, [$outline], 'Feature', 'en', NULL, 1);
    $event = new BeforeScenarioTested($this->createMock(Environment::class), $feature, $outline->getExamples()[0]);

    $driver_registry = $this->createMock(DriverRegistryInterface::class);
    $driver_registry->expects($this->once())->method('setScenarioDrivers')->with($this->identicalTo(['drush' => 'drush', 'blackbox' => 'blackbox', 'drupal' => 'drupal']));

    $listener = new DriverListener($driver_registry, $this->scenarioTags, self::DRIVERS);
    $listener->prepareScenarioDrivers($event);
  }

  public function testAnAliasedEntryNamesTheDriverBehindIt(): void {
    $driver_registry = $this->createMock(DriverRegistryInterface::class);
    $driver_registry->expects($this->once())->method('setScenarioDrivers')->with($this->identicalTo(['api' => 'drupal', 'blackbox' => 'blackbox']));

    $listener = new DriverListener($driver_registry, $this->scenarioTags, ['blackbox', 'api' => 'drupal']);
    $listener->prepareScenarioDrivers($this->createEvent([], ['driver:api']));
  }

  public function testTagNameIsMatchedWithoutRegardToCase(): void {
    $driver_registry = $this->createMock(DriverRegistryInterface::class);
    $driver_registry->expects($this->once())->method('setScenarioDrivers')->with($this->identicalTo(['api' => 'drupal', 'blackbox' => 'blackbox']));

    $listener = new DriverListener($driver_registry, $this->scenarioTags, ['API' => 'drupal', 'blackbox']);
    $listener->prepareScenarioDrivers($this->createEvent([], ['driver:API']));
  }

  public function testConfigurationWithoutListGetsEveryRegisteredDriver(): void {
    $driver_registry = $this->createMock(DriverRegistryInterface::class);
    $driver_registry->method('getDrivers')->willReturn([
      'blackbox' => $this->createMock(DriverInterface::class),
      'drupal' => $this->createMock(DriverInterface::class),
    ]);
    $driver_registry->expects($this->once())->method('setScenarioDrivers')->with($this->identicalTo(['blackbox' => 'blackbox', 'drupal' => 'drupal']));

    $listener = new DriverListener($driver_registry, $this->scenarioTags);
    $listener->prepareScenarioDrivers($this->createEvent([], []));
  }

  /**
   * Tests that a tag naming a driver outside the configured list is reported.
   *
   * @param list<string> $feature_tags
   *   Tags declared on the feature.
   * @param list<string> $scenario_tags
   *   Tags declared on the scenario.
   * @param string $expected_tag
   *   The tag the message is expected to name.
   */
  #[DataProvider('dataProviderTagNamingUnlistedDriverIsReported')]
  public function testTagNamingUnlistedDriverIsReported(array $feature_tags, array $scenario_tags, string $expected_tag): void {
    $listener = new DriverListener($this->createMock(DriverRegistryInterface::class), $this->scenarioTags, self::DRIVERS);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage(sprintf('The "%s" tag names a driver that the configured driver list does not hold. Configured drivers: drupal, drush, blackbox. The tag reorders that list; it never adds to it.', $expected_tag));

    $listener->prepareScenarioDrivers($this->createEvent($feature_tags, $scenario_tags));
  }

  public static function dataProviderTagNamingUnlistedDriverIsReported(): \Iterator {
    yield 'on the scenario line' => [[], ['driver:typo'], '@driver:typo'];
    yield 'on the feature line' => [['driver:typo'], [], '@driver:typo'];
    yield 'beside a listed name' => [[], ['driver:drush', 'driver:typo'], '@driver:typo'];
    yield 'with no name' => [[], ['driver:'], '@driver:'];
  }

  public function testTheEnvironmentIsHandedToTheManager(): void {
    $event = $this->createEvent([], []);

    $driver_registry = $this->createMock(DriverRegistryInterface::class);
    $driver_registry->expects($this->once())->method('setEnvironment')->with($event->getEnvironment());

    $listener = new DriverListener($driver_registry, $this->scenarioTags, self::DRIVERS);
    $listener->prepareScenarioDrivers($event);
  }

  /**
   * Tests that the scenario's tags reach the registry, feature tags first.
   */
  public function testTheScenarioTagsArePublished(): void {
    $listener = new DriverListener($this->createMock(DriverRegistryInterface::class), $this->scenarioTags, self::DRIVERS);

    $listener->prepareScenarioDrivers($this->createEvent(['api'], ['javascript', 'error']));

    $this->assertSame(['api', 'javascript', 'error'], $this->scenarioTags->getTags());
  }

  /**
   * Tests that each scenario replaces the tags of the one before it.
   */
  public function testTheTagsOfOneScenarioDoNotLeakIntoTheNext(): void {
    $listener = new DriverListener($this->createMock(DriverRegistryInterface::class), $this->scenarioTags, self::DRIVERS);

    $listener->prepareScenarioDrivers($this->createEvent([], ['error']));
    $listener->prepareScenarioDrivers($this->createEvent([], []));

    $this->assertSame([], $this->scenarioTags->getTags());
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
