<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Manager;

use Behat\Testwork\Environment\Environment;
use DrevOps\BehatSteps\Backend\BackendInterface;

/**
 * Holds the backends registered with a suite and resolves one by name.
 *
 * Two name spaces meet here. A backend is registered under the name the
 * extension builds it with ('drupal', 'drush', 'blackbox'), and a suite maps
 * a Gherkin-facing tag name onto one of those. 'getBackend()' takes the tag
 * name, because that is the name a test author writes; 'registerBackend()'
 * takes the registered name.
 */
interface BackendRegistryInterface {

  /**
   * Registers a new backend.
   *
   * @param string $name
   *   The registered backend name.
   * @param \DrevOps\BehatSteps\Backend\BackendInterface $backend
   *   The backend to register under that name.
   */
  public function registerBackend(string $name, BackendInterface $backend): void;

  /**
   * Returns all registered backends.
   *
   * @return array<string, \DrevOps\BehatSteps\Backend\BackendInterface>
   *   The backends, keyed by their lowercased registered name.
   */
  public function getBackends(): array;

  /**
   * Sets the backend order the current scenario resolves against.
   *
   * Resolution walks this order, so the first entry wins any capability it
   * provides. Setting the order also clears the record of which backends the
   * previous scenario resolved.
   *
   * @param array<string, string> $backends
   *   Ordered map of tag name to registered backend name.
   *
   * @throws \RuntimeException
   *   When an entry names a backend that is not registered.
   */
  public function setScenarioBackends(array $backends): void;

  /**
   * Returns the backend order the current scenario resolves against.
   *
   * @return array<string, string>
   *   Ordered map of tag name to registered backend name.
   */
  public function getScenarioBackends(): array;

  /**
   * Returns a backend of the current scenario by its tag name.
   *
   * @param string $name
   *   The tag name the suite gave the backend.
   *
   * @return \DrevOps\BehatSteps\Backend\BackendInterface
   *   The requested backend, bootstrapped.
   *
   * @throws \RuntimeException
   *   When the scenario's backend order holds no such name.
   */
  public function getBackend(string $name): BackendInterface;

  /**
   * Returns the highest-priority backend providing a capability.
   *
   * Walks the scenario's backend order and bootstraps only the backend it
   * returns.
   *
   * @param class-string<T> $capability
   *   The capability interface the caller needs.
   *
   * @return T
   *   The backend, bootstrapped.
   *
   * @throws \DrevOps\BehatSteps\Backend\Exception\UnsupportedBackendActionException
   *   When no backend in the scenario's order implements the capability.
   *
   * @template T of object
   */
  public function getBackendFor(string $capability): object;

  /**
   * Determines whether any backend in the scenario's order has a capability.
   *
   * Bootstraps nothing, so a hook can call it before a scenario has used the
   * site.
   *
   * @param class-string $capability
   *   The capability interface to look for.
   */
  public function hasCapability(string $capability): bool;

  /**
   * Returns a backend with the capability that this scenario already resolved.
   *
   * Reports whether a step in this scenario resolved the capability, which is
   * narrower than 'hasCapability()': a suite may list a cache-capable backend
   * that no step in this scenario resolved.
   *
   * @param class-string<T> $capability
   *   The capability interface to look for.
   *
   * @return T|null
   *   The backend, or NULL when this scenario resolved no such backend.
   *
   * @template T of object
   */
  public function getResolvedBackendFor(string $capability): ?object;

  /**
   * Returns the Behat environment.
   */
  public function getEnvironment(): ?Environment;

  /**
   * Sets the Behat environment.
   */
  public function setEnvironment(Environment $environment): void;

}
