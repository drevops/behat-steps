<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Listener;

use Behat\Behat\EventDispatcher\Event\BeforeScenarioTested;
use Behat\Behat\EventDispatcher\Event\ExampleTested;
use Behat\Behat\EventDispatcher\Event\ScenarioTested;
use DrevOps\BehatSteps\Behat\Manager\DriverManagerInterface;
use DrevOps\BehatSteps\Behat\Tag;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Selects the driver each scenario or example runs against.
 */
class DriverListener implements EventSubscriberInterface {

  /**
   * Constructs a DriverListener.
   *
   * @param \DrevOps\BehatSteps\Behat\Manager\DriverManagerInterface $driverManager
   *   The driver manager.
   * @param array<string, mixed> $parameters
   *   Test parameters.
   */
  public function __construct(
    protected readonly DriverManagerInterface $driverManager,
    protected array $parameters,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ScenarioTested::BEFORE => ['prepareDefaultDriver', 11],
      ExampleTested::BEFORE => ['prepareDefaultDriver', 11],
    ];
  }

  /**
   * Sets the default driver for the scenario or example about to run.
   *
   * A tag named '<tag>' selects the driver configured as '<tag>_driver', so
   * an '@api' scenario runs against 'api_driver'. Scenarios carrying no such
   * tag run against 'default_driver'.
   *
   * Both subscribed events carry a 'BeforeScenarioTested', an example's
   * scenario being the outline row itself.
   *
   * @throws \RuntimeException
   *   When neither a tag nor 'default_driver' names a driver.
   */
  public function prepareDefaultDriver(BeforeScenarioTested $event): void {
    $driver = $this->parameters['default_driver'] ?? NULL;

    foreach (Tag::all($event) as $tag) {
      if (!empty($this->parameters[$tag . '_driver'])) {
        $driver = $this->parameters[$tag . '_driver'];
      }
    }

    if (!is_string($driver) || $driver === '') {
      throw new \RuntimeException('No driver is configured for this scenario: set "default_driver" in the extension configuration.');
    }

    $this->driverManager->setDefaultDriverName($driver);
    $this->driverManager->setEnvironment($event->getEnvironment());
  }

}
