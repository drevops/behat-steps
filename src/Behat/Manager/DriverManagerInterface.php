<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Manager;

use Behat\Testwork\Environment\Environment;
use DrevOps\BehatSteps\Driver\DriverInterface;

/**
 * Interface for managing the drivers registered with a suite.
 *
 * Two name spaces meet here. A driver is registered under the name the
 * extension builds it with ('drupal', 'drush', 'blackbox'), and a suite maps
 * a Gherkin-facing tag name onto one of those. Every method below that takes a
 * name takes the tag name, because that is the name a test author writes.
 */
interface DriverManagerInterface {

  /**
   * Registers a new driver.
   *
   * @param string $name
   *   The registered driver name.
   * @param \DrevOps\BehatSteps\Driver\DriverInterface $driver
   *   The driver to register under that name.
   */
  public function registerDriver(string $name, DriverInterface $driver): void;

  /**
   * Returns all registered drivers.
   *
   * @return array<string, \DrevOps\BehatSteps\Driver\DriverInterface>
   *   The drivers, keyed by their lowercased registered name.
   */
  public function getDrivers(): array;

  /**
   * Sets the driver order the current scenario resolves against.
   *
   * Resolution walks this order, so the first entry wins any capability it
   * provides. Setting the order also forgets which drivers the previous
   * scenario resolved.
   *
   * @param array<string, string> $drivers
   *   Ordered map of tag name to registered driver name.
   *
   * @throws \RuntimeException
   *   When an entry names a driver that is not registered.
   */
  public function setScenarioDrivers(array $drivers): void;

  /**
   * Returns the driver order the current scenario resolves against.
   *
   * @return array<string, string>
   *   Ordered map of tag name to registered driver name.
   */
  public function getScenarioDrivers(): array;

  /**
   * Returns a driver of the current scenario by its tag name.
   *
   * @param string $name
   *   The tag name the suite gave the driver.
   *
   * @return \DrevOps\BehatSteps\Driver\DriverInterface
   *   The requested driver, bootstrapped.
   *
   * @throws \RuntimeException
   *   When the scenario's driver order holds no such name.
   */
  public function getDriver(string $name): DriverInterface;

  /**
   * Returns the highest-priority driver providing a capability.
   *
   * Walks the scenario's driver order and bootstraps only the driver it
   * returns.
   *
   * @template T of object
   *
   * @param class-string<T> $capability
   *   The capability interface the caller needs.
   *
   * @return T
   *   The driver, bootstrapped.
   *
   * @throws \DrevOps\BehatSteps\Driver\Exception\UnsupportedDriverActionException
   *   When no driver in the scenario's order implements the capability.
   */
  public function getDriverFor(string $capability): object;

  /**
   * Determines whether any driver in the scenario's order has a capability.
   *
   * Bootstraps nothing, so a hook can ask before a scenario has touched the
   * site.
   *
   * @param class-string $capability
   *   The capability interface to look for.
   */
  public function hasCapability(string $capability): bool;

  /**
   * Returns a driver with the capability that this scenario already resolved.
   *
   * Answers "did a step reach for this capability", which is a narrower
   * question than 'hasCapability()': a suite may list a cache-capable driver
   * that no step in this scenario ever asked for.
   *
   * @template T of object
   *
   * @param class-string<T> $capability
   *   The capability interface to look for.
   *
   * @return T|null
   *   The driver, or NULL when this scenario resolved no such driver.
   */
  public function getResolvedDriverFor(string $capability): ?object;

  /**
   * Returns the Behat environment.
   */
  public function getEnvironment(): ?Environment;

  /**
   * Sets the Behat environment.
   */
  public function setEnvironment(Environment $environment): void;

}
