<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Listener;

use Behat\Behat\EventDispatcher\Event\BeforeScenarioTested;
use Behat\Behat\EventDispatcher\Event\ExampleTested;
use Behat\Behat\EventDispatcher\Event\ScenarioTested;
use Behat\Gherkin\Node\FeatureNode;
use Behat\Gherkin\Node\ScenarioNode;
use Behat\Testwork\Environment\Environment;
use DrevOps\BehatSteps\Behat\Listener\DriverListener;
use DrevOps\BehatSteps\Behat\Manager\DriverManagerInterface;
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
   *   The order the manager is expected to receive.
   */
  #[DataProvider('dataProviderDriverOrder')]
  public function testDriverOrder(array $feature_tags, array $scenario_tags, array $expected): void {
    $driver_manager = $this->createMock(DriverManagerInterface::class);
    $driver_manager->expects($this->once())->method('setScenarioDrivers')->with($expected);

    $listener = new DriverListener($driver_manager, self::DRIVERS);
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
    yield 'repeated tags keep the order they were written in' => [
      [],
      ['driver:blackbox', 'driver:drush'],
      ['blackbox' => 'blackbox', 'drush' => 'drush', 'drupal' => 'drupal'],
    ];
    yield 'a scenario tag is promoted ahead of a feature tag' => [
      ['driver:drush'],
      ['driver:blackbox'],
      ['blackbox' => 'blackbox', 'drush' => 'drush', 'drupal' => 'drupal'],
    ];
    yield 'a repeated name is promoted once' => [
      ['driver:drush'],
      ['driver:drush'],
      ['drush' => 'drush', 'drupal' => 'drupal', 'blackbox' => 'blackbox'],
    ];
    yield 'a tag that is not a driver tag is ignored' => [
      [],
      ['javascript'],
      ['drupal' => 'drupal', 'drush' => 'drush', 'blackbox' => 'blackbox'],
    ];
  }

  public function testAnAliasedEntryNamesTheDriverBehindIt(): void {
    $driver_manager = $this->createMock(DriverManagerInterface::class);
    $driver_manager->expects($this->once())->method('setScenarioDrivers')->with(['api' => 'drupal', 'blackbox' => 'blackbox']);

    $listener = new DriverListener($driver_manager, ['blackbox', 'api' => 'drupal']);
    $listener->prepareScenarioDrivers($this->createEvent([], ['driver:api']));
  }

  public function testTagNameIsMatchedWithoutRegardToCase(): void {
    $driver_manager = $this->createMock(DriverManagerInterface::class);
    $driver_manager->expects($this->once())->method('setScenarioDrivers')->with(['api' => 'drupal', 'blackbox' => 'blackbox']);

    $listener = new DriverListener($driver_manager, ['API' => 'drupal', 'blackbox']);
    $listener->prepareScenarioDrivers($this->createEvent([], ['driver:API']));
  }

  public function testConfigurationWithoutListGetsEveryRegisteredDriver(): void {
    $driver_manager = $this->createMock(DriverManagerInterface::class);
    $driver_manager->method('getDrivers')->willReturn([
      'blackbox' => $this->createMock(DriverInterface::class),
      'drupal' => $this->createMock(DriverInterface::class),
    ]);
    $driver_manager->expects($this->once())->method('setScenarioDrivers')->with(['blackbox' => 'blackbox', 'drupal' => 'drupal']);

    $listener = new DriverListener($driver_manager);
    $listener->prepareScenarioDrivers($this->createEvent([], []));
  }

  public function testTagNamingUnlistedDriverIsReported(): void {
    $listener = new DriverListener($this->createMock(DriverManagerInterface::class), self::DRIVERS);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The "@driver:typo" tag names a driver that the configured driver list does not hold. Configured drivers: drupal, drush, blackbox. The tag reorders that list; it never adds to it.');

    $listener->prepareScenarioDrivers($this->createEvent([], ['driver:typo']));
  }

  public function testTheEnvironmentIsHandedToTheManager(): void {
    $event = $this->createEvent([], []);

    $driver_manager = $this->createMock(DriverManagerInterface::class);
    $driver_manager->expects($this->once())->method('setEnvironment')->with($event->getEnvironment());

    $listener = new DriverListener($driver_manager, self::DRIVERS);
    $listener->prepareScenarioDrivers($event);
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
