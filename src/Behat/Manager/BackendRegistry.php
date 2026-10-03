<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Manager;

use Behat\Testwork\Environment\Environment;
use DrevOps\BehatSteps\Backend\BackendInterface;
use DrevOps\BehatSteps\Backend\Exception\UnsupportedBackendActionException;

/**
 * Default implementation of the backend registry service.
 */
class BackendRegistry implements BackendRegistryInterface {

  /**
   * All registered backends, keyed by their lowercased registered name.
   *
   * @var array<string, \DrevOps\BehatSteps\Backend\BackendInterface>
   */
  protected array $backends = [];

  /**
   * The current scenario's order, as tag name to registered backend name.
   *
   * @var array<string, string>
   */
  protected array $scenarioBackends = [];

  /**
   * Backends resolved during the current scenario.
   *
   * @var array<int, \DrevOps\BehatSteps\Backend\BackendInterface>
   */
  protected array $resolvedBackends = [];

  /**
   * Behat environment.
   */
  protected ?Environment $environment = NULL;

  /**
   * Initializes the backend registry.
   *
   * @param array<string, \DrevOps\BehatSteps\Backend\BackendInterface> $backends
   *   Backends to register, keyed by name.
   */
  public function __construct(array $backends = []) {
    foreach ($backends as $name => $backend) {
      $this->registerBackend($name, $backend);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function registerBackend(string $name, BackendInterface $backend): void {
    $name = strtolower($name);
    $this->backends[$name] = $backend;
  }

  /**
   * {@inheritdoc}
   */
  public function getBackends(): array {
    return $this->backends;
  }

  /**
   * {@inheritdoc}
   */
  public function setScenarioBackends(array $backends): void {
    $order = [];

    foreach ($backends as $tag => $name) {
      $name = strtolower($name);

      if (!isset($this->backends[$name])) {
        throw new \RuntimeException(sprintf('Backend "%s" is not registered. Registered backends: %s.', $name, $this->listRegisteredNames()));
      }

      $order[strtolower((string) $tag)] = $name;
    }

    $this->scenarioBackends = $order;
    $this->resolvedBackends = [];
  }

  /**
   * {@inheritdoc}
   */
  public function getScenarioBackends(): array {
    return $this->scenarioBackends;
  }

  /**
   * {@inheritdoc}
   */
  public function getBackend(string $name): BackendInterface {
    $name = strtolower($name);

    if (!isset($this->scenarioBackends[$name])) {
      throw new \RuntimeException(sprintf('Backend "%s" is not available to this scenario. Available backends: %s.', $name, $this->listScenarioNames()));
    }

    return $this->resolve($this->backends[$this->scenarioBackends[$name]]);
  }

  /**
   * {@inheritdoc}
   */
  public function getBackendFor(string $capability): object {
    foreach ($this->scenarioBackends as $name) {
      $backend = $this->backends[$name];

      if ($backend instanceof $capability) {
        $this->resolve($backend);

        return $backend;
      }
    }

    throw new UnsupportedBackendActionException(sprintf('No backend provides "%s". Backends available to this scenario, in order: %s.', $capability, $this->listScenarioNames()));
  }

  /**
   * {@inheritdoc}
   */
  public function hasCapability(string $capability): bool {
    foreach ($this->scenarioBackends as $name) {
      if ($this->backends[$name] instanceof $capability) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getResolvedBackendFor(string $capability): ?object {
    // Walk the scenario's order rather than the resolution order, so this
    // returns the same backend 'getBackendFor()' would return.
    foreach ($this->scenarioBackends as $name) {
      $backend = $this->backends[$name];

      if ($backend instanceof $capability && in_array($backend, $this->resolvedBackends, TRUE)) {
        return $backend;
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
   * Bootstraps a backend and records that this scenario resolved it.
   */
  protected function resolve(BackendInterface $backend): BackendInterface {
    if (!in_array($backend, $this->resolvedBackends, TRUE)) {
      $this->resolvedBackends[] = $backend;
    }

    if (!$backend->isBootstrapped()) {
      $backend->bootstrap();
    }

    return $backend;
  }

  /**
   * Formats the registered backend names for an error message.
   */
  protected function listRegisteredNames(): string {
    return $this->backends === [] ? 'none' : implode(', ', array_keys($this->backends));
  }

  /**
   * Formats the scenario's backend names for an error message.
   */
  protected function listScenarioNames(): string {
    return $this->scenarioBackends === [] ? 'none' : implode(', ', array_keys($this->scenarioBackends));
  }

}
