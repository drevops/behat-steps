<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Manager;

use Behat\Testwork\Environment\Environment;
use DrevOps\BehatSteps\Driver\DriverInterface;

/**
 * Default implementation of the driver manager service.
 */
class DriverManager implements DriverManagerInterface {

  /**
   * The name of the default driver.
   */
  protected ?string $defaultDriverName = NULL;

  /**
   * All registered drivers, keyed by their lowercased name.
   *
   * @var array<string, \DrevOps\BehatSteps\Driver\DriverInterface>
   */
  protected array $drivers = [];

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
  public function getDriver(?string $name = NULL): DriverInterface {
    $name = $name === NULL ? $this->defaultDriverName : strtolower($name);

    if ($name === NULL) {
      throw new \InvalidArgumentException('Specify a Drupal driver to get.');
    }

    if (!isset($this->drivers[$name])) {
      throw new \InvalidArgumentException(sprintf('Driver "%s" is not registered', $name));
    }

    $driver = $this->drivers[$name];

    if (!$driver->isBootstrapped()) {
      $driver->bootstrap();
    }

    return $driver;
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
  public function setDefaultDriverName(string $name): void {
    $name = strtolower($name);

    if (!isset($this->drivers[$name])) {
      throw new \InvalidArgumentException(sprintf('Driver "%s" is not registered.', $name));
    }

    $this->defaultDriverName = $name;
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

}
