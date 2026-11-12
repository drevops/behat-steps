<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Experimental;

/**
 * Takes the experimental registry its experimental code paths record to.
 */
interface ExperimentalAwareInterface {

  /**
   * Sets the experimental registry.
   *
   * @param \DrevOps\BehatSteps\Backend\Experimental\ExperimentalRegistryInterface $registry
   *   The registry.
   */
  public function setExperimentalRegistry(ExperimentalRegistryInterface $registry): void;

}
