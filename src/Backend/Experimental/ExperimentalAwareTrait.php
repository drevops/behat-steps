<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Experimental;

/**
 * Implements 'ExperimentalAwareInterface' and records experimental code paths.
 *
 * Without an injected registry, the trait records to a non-strict registry of
 * its own, so a class works outside a Behat run.
 */
trait ExperimentalAwareTrait {

  /**
   * The experimental registry.
   */
  protected ?ExperimentalRegistryInterface $experimentalRegistry = NULL;

  /**
   * Sets the experimental registry.
   *
   * @param \DrevOps\BehatSteps\Backend\Experimental\ExperimentalRegistryInterface $registry
   *   The registry.
   *
   * @internal
   *   Injection point called by the context initializer and the backend
   *   registry.
   */
  public function setExperimentalRegistry(ExperimentalRegistryInterface $registry): void {
    $this->experimentalRegistry = $registry;
  }

  /**
   * Returns the experimental registry, creating a non-strict one when unset.
   */
  public function getExperimentalRegistry(): ExperimentalRegistryInterface {
    return $this->experimentalRegistry ??= new ExperimentalRegistry();
  }

  /**
   * Records that an experimental code path ran.
   *
   * @param string $id
   *   The stable ID of the code path.
   * @param string $message
   *   What the code path does instead of the expected behavior.
   */
  public function experimentalRecord(string $id, string $message): void {
    $this->getExperimentalRegistry()->record($id, $message);
  }

  /**
   * Records a fallback taken over a failure, or rethrows it in strict mode.
   *
   * @param string $id
   *   The stable ID of the code path.
   * @param string $message
   *   What the code path does instead of the expected behavior.
   * @param \Throwable $exception
   *   The failure the fallback absorbs.
   *
   * @throws \Throwable
   *   The given exception, unchanged, in strict mode.
   */
  public function experimentalFallback(string $id, string $message, \Throwable $exception): void {
    $this->getExperimentalRegistry()->fallback($id, $message, $exception);
  }

}
