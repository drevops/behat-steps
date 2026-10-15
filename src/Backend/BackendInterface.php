<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend;

use DrevOps\BehatSteps\Backend\Random\RandomGeneratorInterface;

/**
 * Minimum contract that every backend must satisfy.
 *
 * Additional functionality is expressed through capability interfaces, such
 * as those in the 'DrevOps\BehatSteps\Backend\Capability' namespace.
 */
interface BackendInterface {

  /**
   * Returns a random-value generator.
   */
  public function getRandom(): RandomGeneratorInterface;

  /**
   * Bootstraps the backend.
   */
  public function bootstrap(): void;

  /**
   * Indicates whether the backend has been bootstrapped.
   */
  public function isBootstrapped(): bool;

}
