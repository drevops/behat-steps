<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Driver\Capability;

/**
 * Capability: process outstanding Drupal batches.
 */
interface BatchCapabilityInterface {

  /**
   * Processes an outstanding Drupal batch, if any.
   */
  public function processBatch(): void;

}
