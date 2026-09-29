<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Mink;

use Behat\Mink\Driver\DriverInterface;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use DrevOps\BehatSteps\Behat\Mink\Adapter\BrowserKitAdapter;
use DrevOps\BehatSteps\Behat\Mink\Adapter\ChromeAdapter;
use DrevOps\BehatSteps\Behat\Mink\Adapter\Selenium2Adapter;

/**
 * Answers what a session's browser driver can do.
 *
 * Mirrors 'DriverManager::getDriverFor()' on the Drupal side: a step names the
 * capability it needs and never a driver, so a project registering its own
 * Mink driver gets the shipped steps working as soon as it registers an
 * adapter declaring that capability.
 */
class BrowserCapabilityResolver {

  /**
   * Adapter classes, in the order they are offered a driver.
   *
   * @var array<int, class-string<\DrevOps\BehatSteps\Behat\Mink\BrowserAdapterInterface>>
   */
  protected array $adapters = [
    Selenium2Adapter::class,
    ChromeAdapter::class,
    BrowserKitAdapter::class,
  ];

  /**
   * Adapters already built, keyed by the driver's object id.
   *
   * @var array<int, \DrevOps\BehatSteps\Behat\Mink\BrowserAdapterInterface>
   */
  protected array $resolved = [];

  /**
   * Registers an adapter class ahead of the shipped ones.
   *
   * @param class-string<\DrevOps\BehatSteps\Behat\Mink\BrowserAdapterInterface> $adapter
   *   The adapter class to offer a driver first.
   */
  public function registerAdapter(string $adapter): void {
    array_unshift($this->adapters, $adapter);
    $this->resolved = [];
  }

  /**
   * Returns the adapter providing a capability for the given driver.
   *
   * @param \Behat\Mink\Driver\DriverInterface $driver
   *   The driver the session is running.
   * @param class-string<T> $capability
   *   The capability interface the caller needs.
   *
   * @return T
   *   The adapter.
   *
   * @throws \Behat\Mink\Exception\UnsupportedDriverActionException
   *   When no adapter provides the capability for this driver.
   *
   * @template T of object
   */
  public function resolve(DriverInterface $driver, string $capability): object {
    $adapter = $this->adapterFor($driver);

    if (!$adapter instanceof $capability) {
      throw new UnsupportedDriverActionException(sprintf('No browser capability "%s" is available for %%s.', $capability), $driver);
    }

    return $adapter;
  }

  /**
   * Whether the given driver provides a capability.
   *
   * Pairs with 'resolve()' the way 'DriverManagerInterface::hasCapability()'
   * pairs with 'getDriverFor()': a step that degrades gracefully asks this,
   * and a step that cannot proceed without the capability calls 'resolve()'.
   *
   * @param \Behat\Mink\Driver\DriverInterface $driver
   *   The driver the session is running.
   * @param class-string $capability
   *   The capability interface to look for.
   */
  public function has(DriverInterface $driver, string $capability): bool {
    return $this->adapterFor($driver) instanceof $capability;
  }

  /**
   * Returns the adapter speaking for a driver, or NULL when none does.
   *
   * @param \Behat\Mink\Driver\DriverInterface $driver
   *   The driver the session is running.
   */
  protected function adapterFor(DriverInterface $driver): ?BrowserAdapterInterface {
    $id = spl_object_id($driver);

    if (array_key_exists($id, $this->resolved)) {
      return $this->resolved[$id];
    }

    foreach ($this->adapters as $adapter) {
      if ($adapter::supports($driver)) {
        return $this->resolved[$id] = new $adapter($driver);
      }
    }

    return NULL;
  }

}
