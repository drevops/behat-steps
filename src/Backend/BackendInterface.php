<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend;

use Drupal\Component\Utility\Random;

/**
 * Minimum contract that every backend must satisfy.
 *
 * Additional functionality is expressed through capability interfaces in the
 * 'DrevOps\BehatSteps\Backend\Capability' namespace.
 */
interface BackendInterface {

  /**
   * Returns a random-value generator.
   */
  public function getRandom(): Random;

  /**
   * Bootstraps the backend.
   */
  public function bootstrap(): void;

  /**
   * Indicates whether the backend has been bootstrapped.
   */
  public function isBootstrapped(): bool;

}
