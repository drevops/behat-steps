<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Mink;

use Behat\Mink\Driver\DriverInterface;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use DrevOps\BehatSteps\Behat\Mink\Adapter\BrowserKitAdapter;
use DrevOps\BehatSteps\Behat\Mink\Adapter\ChromeAdapter;
use DrevOps\BehatSteps\Behat\Mink\Adapter\Selenium2Adapter;

/**
 * Resolves the capabilities of a session's browser driver.
 *
 * Mirrors 'BackendRegistry::getBackendFor()' on the Drupal side: a step names
 * the capability it needs and never a browser driver. A project registering
 * its own browser driver therefore runs the shipped steps as soon as it
 * registers an adapter declaring that capability.
 */
final class BrowserCapabilityResolver {

  /**
   * Adapter classes, in the order they are tried for a browser driver.
   *
   * @var array<int, class-string<\DrevOps\BehatSteps\Behat\Mink\BrowserAdapterInterface>>
   */
  protected array $adapters = [
    Selenium2Adapter::class,
    ChromeAdapter::class,
    BrowserKitAdapter::class,
  ];

  /**
   * Adapters already built, keyed by the browser driver they wrap.
   *
   * Keyed by the object rather than its id, because PHP reuses an object id
   * once the object it belonged to is collected.
   *
   * @var \WeakMap<\Behat\Mink\Driver\DriverInterface, \DrevOps\BehatSteps\Behat\Mink\BrowserAdapterInterface>
   */
  protected \WeakMap $resolved;

  /**
   * Constructs a resolver with an empty adapter cache.
   */
  public function __construct() {
    $this->resolved = new \WeakMap();
  }

  /**
   * Registers an adapter class ahead of the shipped ones.
   *
   * @param class-string<\DrevOps\BehatSteps\Behat\Mink\BrowserAdapterInterface> $adapter
   *   The adapter class to try first for a browser driver.
   */
  public function registerAdapter(string $adapter): void {
    array_unshift($this->adapters, $adapter);
    $this->resolved = new \WeakMap();
  }

  /**
   * Returns the adapter providing a capability for the given browser driver.
   *
   * @param \Behat\Mink\Driver\DriverInterface $driver
   *   The browser driver the session is running.
   * @param class-string<T> $capability
   *   The capability interface the caller needs.
   *
   * @return T&\DrevOps\BehatSteps\Behat\Mink\BrowserAdapterInterface
   *   The adapter.
   *
   * @throws \Behat\Mink\Exception\UnsupportedDriverActionException
   *   When no adapter provides the capability for this browser driver.
   *
   * @template T of object
   */
  public function resolve(DriverInterface $driver, string $capability): BrowserAdapterInterface {
    $adapter = $this->adapterFor($driver);

    if (!$adapter instanceof $capability) {
      throw new UnsupportedDriverActionException(sprintf('No browser capability "%s" is available for %%s.', $capability), $driver);
    }

    return $adapter;
  }

  /**
   * Whether the given browser driver provides a capability.
   *
   * Pairs with 'resolve()' the way 'BackendRegistryInterface::hasCapability()'
   * pairs with 'getBackendFor()': this fits a capability the caller can do
   * without, and 'resolve()' one it cannot.
   *
   * @param \Behat\Mink\Driver\DriverInterface $driver
   *   The browser driver the session is running.
   * @param class-string $capability
   *   The capability interface to look for.
   */
  public function has(DriverInterface $driver, string $capability): bool {
    return $this->adapterFor($driver) instanceof $capability;
  }

  /**
   * Returns the adapter for a browser driver, or NULL when none supports it.
   *
   * @param \Behat\Mink\Driver\DriverInterface $driver
   *   The browser driver the session is running.
   */
  protected function adapterFor(DriverInterface $driver): ?BrowserAdapterInterface {
    if (isset($this->resolved[$driver])) {
      return $this->resolved[$driver];
    }

    foreach ($this->adapters as $adapter) {
      if ($adapter::supports($driver)) {
        return $this->resolved[$driver] = new $adapter($driver);
      }
    }

    return NULL;
  }

}
