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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests how a scenario's tags select the driver it runs against.
 */
#[CoversClass(DriverListener::class)]
class DriverListenerTest extends TestCase {

  /**
   * Parameters mapping the '@api' tag onto the Drupal driver.
   */
  protected const PARAMETERS = [
    'default_driver' => 'blackbox',
    'api_driver' => 'drupal',
    'javascript_driver' => 'drush',
  ];

  public function testItSubscribesToScenariosAndExamples(): void {
    $events = DriverListener::getSubscribedEvents();

    $this->assertSame(['prepareDefaultDriver', 11], $events[ScenarioTested::BEFORE]);
    $this->assertSame(['prepareDefaultDriver', 11], $events[ExampleTested::BEFORE]);
  }

  /**
   * Tests which driver a set of feature and scenario tags selects.
   *
   * @param list<string> $feature_tags
   *   Tags declared on the feature.
   * @param list<string> $scenario_tags
   *   Tags declared on the scenario.
   * @param string $expected
   *   The driver name expected to be selected.
   */
  #[DataProvider('dataProviderDriverSelection')]
  public function testDriverSelection(array $feature_tags, array $scenario_tags, string $expected): void {
    $driver_manager = $this->createMock(DriverManagerInterface::class);
    $driver_manager->expects($this->once())->method('setDefaultDriverName')->with($expected);

    $listener = new DriverListener($driver_manager, self::PARAMETERS);
    $listener->prepareDefaultDriver($this->createEvent($feature_tags, $scenario_tags));
  }

  public static function dataProviderDriverSelection(): \Iterator {
    yield 'no tags falls back to the default driver' => [[], [], 'blackbox'];
    yield 'a feature tag selects its driver' => [['api'], [], 'drupal'];
    yield 'a scenario tag selects its driver' => [[], ['api'], 'drupal'];
    yield 'a tag without a configured driver is ignored' => [[], ['wip'], 'blackbox'];
    yield 'the last matching tag wins' => [['api'], ['javascript'], 'drush'];
  }

  public function testMissingDriverConfigurationIsReported(): void {
    $listener = new DriverListener($this->createMock(DriverManagerInterface::class), []);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('No driver is configured for this scenario: set "default_driver" in the extension configuration.');

    $listener->prepareDefaultDriver($this->createEvent([], []));
  }

  public function testTheEnvironmentIsHandedToTheManager(): void {
    $event = $this->createEvent([], []);

    $driver_manager = $this->createMock(DriverManagerInterface::class);
    $driver_manager->expects($this->once())->method('setEnvironment')->with($event->getEnvironment());

    $listener = new DriverListener($driver_manager, self::PARAMETERS);
    $listener->prepareDefaultDriver($event);
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
