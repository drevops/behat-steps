<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Manager;

use Behat\Testwork\Environment\Environment;
use DrevOps\BehatSteps\Driver\DriverInterface;

/**
 * Interface for managing the drivers registered with a suite.
 */
interface DriverManagerInterface {

  /**
   * Registers a new driver.
   *
   * @param string $name
   *   Driver name.
   * @param \DrevOps\BehatSteps\Driver\DriverInterface $driver
   *   The driver to register under that name.
   */
  public function registerDriver(string $name, DriverInterface $driver): void;

  /**
   * Returns a registered driver by name, or the default driver.
   *
   * @param string|null $name
   *   The name of the driver to return. If omitted the default driver is
   *   returned.
   *
   * @return \DrevOps\BehatSteps\Driver\DriverInterface
   *   The requested driver, bootstrapped.
   *
   * @throws \InvalidArgumentException
   *   Thrown when the requested driver is not registered.
   */
  public function getDriver(?string $name = NULL): DriverInterface;

  /**
   * Returns all registered drivers.
   *
   * @return array<string, \DrevOps\BehatSteps\Driver\DriverInterface>
   *   The drivers, keyed by their lowercased name.
   */
  public function getDrivers(): array;

  /**
   * Sets the default driver name.
   *
   * @param string $name
   *   Default driver name to set.
   *
   * @throws \InvalidArgumentException
   *   Thrown when the driver is not registered.
   */
  public function setDefaultDriverName(string $name): void;

  /**
   * Returns the Behat environment.
   */
  public function getEnvironment(): ?Environment;

  /**
   * Sets the Behat environment.
   */
  public function setEnvironment(Environment $environment): void;

}
