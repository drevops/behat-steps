<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Listener;

use Behat\Behat\EventDispatcher\Event\BeforeScenarioTested;
use Behat\Behat\EventDispatcher\Event\ExampleTested;
use Behat\Behat\EventDispatcher\Event\ScenarioTested;
use Behat\Gherkin\Node\TaggedNodeInterface;
use DrevOps\BehatSteps\Behat\Manager\DriverManagerInterface;
use DrevOps\BehatSteps\Behat\Tag;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Builds the driver order each scenario or example resolves against.
 */
class DriverListener implements EventSubscriberInterface {

  /**
   * Prefix of the tag that promotes a driver for one scenario or feature.
   */
  public const DRIVER_TAG_PREFIX = 'driver:';

  /**
   * Constructs a DriverListener.
   *
   * @param \DrevOps\BehatSteps\Behat\Manager\DriverManagerInterface $driverManager
   *   The driver manager.
   * @param array<array-key, string> $drivers
   *   The configured driver list, as ordered pairs of tag name to registered
   *   driver name. A bare entry carries an integer key and names both.
   */
  public function __construct(
    protected readonly DriverManagerInterface $driverManager,
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
   * Hands the manager the driver order for the scenario about to run.
   *
   * The configured list is both the allow-list and the precedence order. A
   * '@driver:NAME' tag moves NAME to the front of that order for this
   * scenario; it never adds a driver the configuration does not list.
   *
   * Both subscribed events carry a 'BeforeScenarioTested', an example's
   * scenario being the outline row itself.
   *
   * @throws \RuntimeException
   *   When a '@driver:' tag names a driver the configuration does not list.
   */
  public function prepareScenarioDrivers(BeforeScenarioTested $event): void {
    $configured = $this->configuredDrivers();
    $order = [];

    foreach ($this->promotedNames($event) as $name) {
      if (!isset($configured[$name])) {
        throw new \RuntimeException(sprintf('The "@%s%s" tag names a driver that the configured driver list does not hold. Configured drivers: %s. The tag reorders that list; it never adds to it.', self::DRIVER_TAG_PREFIX, $name, implode(', ', array_keys($configured))));
      }

      $order[$name] = $configured[$name];
    }

    $this->driverManager->setScenarioDrivers($order + $configured);
    $this->driverManager->setEnvironment($event->getEnvironment());
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
      $names = array_keys($this->driverManager->getDrivers());

      return array_combine($names, $names);
    }

    $drivers = [];

    foreach ($this->drivers as $tag => $name) {
      $drivers[strtolower(is_int($tag) ? $name : $tag)] = $name;
    }

    return $drivers;
  }

  /**
   * Collects the driver names a scenario and its feature promote.
   *
   * Scenario tags come first, so the more specific declaration takes the front
   * of the order. Within one node the tags keep the order they were written in.
   *
   * @return array<int, string>
   *   Promoted driver names, deduplicated, most specific first.
   */
  protected function promotedNames(BeforeScenarioTested $event): array {
    $scenario = $event->getScenario();
    $tags = $scenario instanceof TaggedNodeInterface ? Tag::on($scenario) : [];
    $tags = array_merge($tags, Tag::on($event->getFeature()));

    $names = [];

    foreach ($tags as $tag) {
      if (str_starts_with($tag, self::DRIVER_TAG_PREFIX)) {
        $names[] = strtolower(substr($tag, strlen(self::DRIVER_TAG_PREFIX)));
      }
    }

    return array_values(array_unique($names));
  }

}
