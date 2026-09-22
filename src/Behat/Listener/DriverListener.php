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
   * Suite setting holding the suite's ordered driver list.
   */
  public const DRIVERS_SETTING = 'drivers';

  /**
   * Constructs a DriverListener.
   *
   * @param \DrevOps\BehatSteps\Behat\Manager\DriverManagerInterface $driverManager
   *   The driver manager.
   */
  public function __construct(
    protected readonly DriverManagerInterface $driverManager,
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
   * The suite's list is both the allow-list and the precedence order. A
   * '@driver:NAME' tag moves NAME to the front of that order for this scenario;
   * it never adds a driver the suite does not list.
   *
   * Both subscribed events carry a 'BeforeScenarioTested', an example's
   * scenario being the outline row itself.
   *
   * @throws \RuntimeException
   *   When a '@driver:' tag names a driver the suite does not list.
   */
  public function prepareScenarioDrivers(BeforeScenarioTested $event): void {
    $suite = $event->getEnvironment()->getSuite();
    $configured = $this->configuredDrivers($suite->hasSetting(self::DRIVERS_SETTING) ? $suite->getSetting(self::DRIVERS_SETTING) : NULL);

    $order = [];

    foreach ($this->promotedNames($event) as $name) {
      if (!isset($configured[$name])) {
        throw new \RuntimeException(sprintf('The "@%s%s" tag names a driver that the "%s" suite does not list. The suite lists: %s. The tag reorders the suite list; it never adds to it.', self::DRIVER_TAG_PREFIX, $name, $suite->getName(), implode(', ', array_keys($configured))));
      }

      $order[$name] = $configured[$name];
    }

    $this->driverManager->setScenarioDrivers($order + $configured);
    $this->driverManager->setEnvironment($event->getEnvironment());
  }

  /**
   * Normalizes the suite's driver list into a tag name to driver name map.
   *
   * A bare entry names a driver whose tag name is the driver name. A keyed
   * entry gives the tag a name of its own, so the same feature file can run
   * against a different driver in another suite.
   *
   * @param mixed $setting
   *   The 'drivers' suite setting, or NULL when the suite declares none.
   *
   * @return array<string, string>
   *   Ordered map of tag name to registered driver name. A suite that declares
   *   no list gets every registered driver, in registration order.
   */
  protected function configuredDrivers(mixed $setting): array {
    if (!is_array($setting) || $setting === []) {
      $names = array_keys($this->driverManager->getDrivers());

      return array_combine($names, $names);
    }

    $drivers = [];

    foreach ($setting as $tag => $name) {
      $name = (string) $name;
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
