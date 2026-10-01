<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Listener;

use Behat\Behat\EventDispatcher\Event\BeforeScenarioTested;
use Behat\Behat\EventDispatcher\Event\ExampleTested;
use Behat\Behat\EventDispatcher\Event\ScenarioTested;
use Behat\Gherkin\Node\TaggedNodeInterface;
use DrevOps\BehatSteps\Behat\Manager\DriverRegistryInterface;
use DrevOps\BehatSteps\Behat\Manager\ScenarioTagRegistryInterface;
use DrevOps\BehatSteps\Behat\Tag;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Publishes the state each scenario or example resolves against.
 *
 * Behat dispatches this event before the first 'BeforeScenario' hook, so
 * everything set here is in place for the whole scenario.
 */
class DriverListener implements EventSubscriberInterface {

  /**
   * Prefix of the tag that promotes a driver for one scenario or feature.
   */
  public const DRIVER_TAG_PREFIX = 'driver:';

  /**
   * Constructs a DriverListener.
   *
   * @param \DrevOps\BehatSteps\Behat\Manager\DriverRegistryInterface $driverRegistry
   *   The driver registry.
   * @param \DrevOps\BehatSteps\Behat\Manager\ScenarioTagRegistryInterface $scenarioTagRegistry
   *   The registry option resolution reads the scenario's tags from.
   * @param array<array-key, string> $drivers
   *   The configured driver list, as ordered pairs of tag name to registered
   *   driver name. A bare entry carries an integer key and names both.
   */
  public function __construct(
    protected readonly DriverRegistryInterface $driverRegistry,
    protected readonly ScenarioTagRegistryInterface $scenarioTagRegistry,
    protected readonly array $drivers = [],
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ScenarioTested::BEFORE => ['prepareScenarioDrivers', 11],
      ExampleTested::BEFORE => ['prepareScenarioDrivers', 11],
    ];
  }

  /**
   * Passes the registries the state for the scenario about to run.
   *
   * The configured list is both the allow-list and the precedence order. A
   * '@driver:NAME' tag moves NAME ahead of the drivers no tag names; it never
   * adds a driver the configuration does not list.
   *
   * The drivers the scenario's tags name come first, then those its feature's
   * tags name. Each group keeps the configured order, so the order the tags
   * are written in never changes the result.
   *
   * The scenario's tags are published here rather than read from a hook scope,
   * so a tag that sets a trait option applies to a step as well as to a hook.
   *
   * Both subscribed events carry a 'BeforeScenarioTested', an example's
   * scenario being the outline row itself.
   *
   * @throws \RuntimeException
   *   When a '@driver:' tag names a driver the configuration does not list.
   */
  public function prepareScenarioDrivers(BeforeScenarioTested $event): void {
    $this->scenarioTagRegistry->setTags(Tag::all($event));

    $configured = $this->configuredDrivers();
    $scenario = $event->getScenario();
    $scenario_drivers = $scenario instanceof TaggedNodeInterface ? $this->promotedDrivers($scenario, $configured) : [];
    $feature_drivers = $this->promotedDrivers($event->getFeature(), $configured);

    $this->driverRegistry->setScenarioDrivers($scenario_drivers + $feature_drivers + $configured);
    $this->driverRegistry->setEnvironment($event->getEnvironment());
  }

  /**
   * Normalizes the configured list into a tag name to driver name map.
   *
   * A bare entry names a driver whose tag name is the driver name. A keyed
   * entry gives the tag a name of its own, so the same feature file can run
   * against a different driver in another profile.
   *
   * @return array<string, string>
   *   Ordered map of tag name to registered driver name. A configuration that
   *   declares no list gets every registered driver, in registration order.
   */
  protected function configuredDrivers(): array {
    if ($this->drivers === []) {
      $names = array_keys($this->driverRegistry->getDrivers());

      return array_combine($names, $names);
    }

    $drivers = [];

    foreach ($this->drivers as $tag => $name) {
      $drivers[strtolower(is_int($tag) ? $name : $tag)] = $name;
    }

    return $drivers;
  }

  /**
   * Selects the configured entries the '@driver:' tags of a node name.
   *
   * An example of a scenario outline counts as 1 node: Gherkin merges the
   * outline's tags and its 'Examples:' table's tags into the example's list.
   *
   * @param \Behat\Gherkin\Node\TaggedNodeInterface $node
   *   The scenario or the feature to read.
   * @param array<string, string> $configured
   *   The configured map of tag name to registered driver name.
   *
   * @return array<string, string>
   *   The named entries of the configured map, in the configured order.
   *
   * @throws \RuntimeException
   *   When a '@driver:' tag names a driver the configuration does not list.
   */
  protected function promotedDrivers(TaggedNodeInterface $node, array $configured): array {
    $names = [];

    foreach (Tag::on($node) as $tag) {
      if (!str_starts_with($tag, self::DRIVER_TAG_PREFIX)) {
        continue;
      }

      $name = strtolower(substr($tag, strlen(self::DRIVER_TAG_PREFIX)));

      if (!isset($configured[$name])) {
        throw new \RuntimeException(sprintf('The "@%s%s" tag names a driver that the configured driver list does not hold. Configured drivers: %s. The tag reorders that list; it never adds to it.', self::DRIVER_TAG_PREFIX, $name, implode(', ', array_keys($configured))));
      }

      $names[$name] = TRUE;
    }

    // 'array_intersect_key()' keeps the order of its first argument, so the
    // order the tags are written in is discarded.
    return array_intersect_key($configured, $names);
  }

}
