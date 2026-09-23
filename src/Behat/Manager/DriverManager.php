<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Manager;

use Behat\Testwork\Environment\Environment;
use DrevOps\BehatSteps\Driver\DriverInterface;
use DrevOps\BehatSteps\Driver\Exception\UnsupportedDriverActionException;

/**
 * Default implementation of the driver manager service.
 */
class DriverManager implements DriverManagerInterface {

  /**
   * All registered drivers, keyed by their lowercased registered name.
   *
   * @var array<string, \DrevOps\BehatSteps\Driver\DriverInterface>
   */
  protected array $drivers = [];

  /**
   * The current scenario's order, as tag name to registered driver name.
   *
   * @var array<string, string>
   */
  protected array $scenarioDrivers = [];

  /**
   * Drivers handed out during the current scenario.
   *
   * @var array<int, \DrevOps\BehatSteps\Driver\DriverInterface>
   */
  protected array $resolvedDrivers = [];

  /**
   * Behat environment.
   */
  protected ?Environment $environment = NULL;

  /**
   * Initializes the driver manager.
   *
   * @param array<string, \DrevOps\BehatSteps\Driver\DriverInterface> $drivers
   *   Drivers to register, keyed by name.
   */
  public function __construct(array $drivers = []) {
    foreach ($drivers as $name => $driver) {
      $this->registerDriver($name, $driver);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function registerDriver(string $name, DriverInterface $driver): void {
    $name = strtolower($name);
    $this->drivers[$name] = $driver;
  }

  /**
   * {@inheritdoc}
   */
  public function getDrivers(): array {
    return $this->drivers;
  }

  /**
   * {@inheritdoc}
   */
  public function setScenarioDrivers(array $drivers): void {
    $order = [];

    foreach ($drivers as $tag => $name) {
      $name = strtolower($name);

      if (!isset($this->drivers[$name])) {
        throw new \RuntimeException(sprintf('Driver "%s" is not registered. Registered drivers: %s.', $name, $this->listRegisteredNames()));
      }

      $order[strtolower((string) $tag)] = $name;
    }

    $this->scenarioDrivers = $order;
    $this->resolvedDrivers = [];
  }

  /**
   * {@inheritdoc}
   */
  public function getScenarioDrivers(): array {
    return $this->scenarioDrivers;
  }

  /**
   * {@inheritdoc}
   */
  public function getDriver(string $name): DriverInterface {
    $name = strtolower($name);

    if (!isset($this->scenarioDrivers[$name])) {
      throw new \RuntimeException(sprintf('Driver "%s" is not available to this scenario. Available drivers: %s.', $name, $this->listScenarioNames()));
    }

    return $this->resolve($this->drivers[$this->scenarioDrivers[$name]]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDriverFor(string $capability): object {
    foreach ($this->scenarioDrivers as $name) {
      $driver = $this->drivers[$name];

      if ($driver instanceof $capability) {
        $this->resolve($driver);

        return $driver;
      }
    }

    throw new UnsupportedDriverActionException(sprintf('No driver provides "%s". Drivers available to this scenario, in order: %s.', $capability, $this->listScenarioNames()));
  }

  /**
   * {@inheritdoc}
   */
  public function hasCapability(string $capability): bool {
    foreach ($this->scenarioDrivers as $name) {
      if ($this->drivers[$name] instanceof $capability) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getResolvedDriverFor(string $capability): ?object {
    // Walk the scenario's order rather than the order the drivers happened to
    // be reached in, so this answers with the same driver 'getDriverFor()'
    // would have returned.
    foreach ($this->scenarioDrivers as $name) {
      $driver = $this->drivers[$name];

      if ($driver instanceof $capability && in_array($driver, $this->resolvedDrivers, TRUE)) {
        return $driver;
      }
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getEnvironment(): ?Environment {
    return $this->environment;
  }

  /**
   * {@inheritdoc}
   */
  public function setEnvironment(Environment $environment): void {
    $this->environment = $environment;
  }

  /**
   * Bootstraps a driver and records that this scenario reached for it.
   */
  protected function resolve(DriverInterface $driver): DriverInterface {
    if (!in_array($driver, $this->resolvedDrivers, TRUE)) {
      $this->resolvedDrivers[] = $driver;
    }

    if (!$driver->isBootstrapped()) {
      $driver->bootstrap();
    }

    return $driver;
  }

  /**
   * Formats the registered driver names for an error message.
   */
  protected function listRegisteredNames(): string {
    return $this->drivers === [] ? 'none' : implode(', ', array_keys($this->drivers));
  }

  /**
   * Formats the scenario's driver names for an error message.
   */
  protected function listScenarioNames(): string {
    return $this->scenarioDrivers === [] ? 'none' : implode(', ', array_keys($this->scenarioDrivers));
  }

}
