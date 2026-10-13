<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend;

use DrevOps\BehatSteps\Backend\Random\RandomGenerator;
use DrevOps\BehatSteps\Backend\Random\RandomGeneratorInterface;

/**
 * Performs no backend operations.
 *
 * Useful when only the public-facing site is being exercised and no API
 * interaction is required.
 */
class BlackboxBackend implements BlackboxBackendInterface {

  /**
   * Random generator.
   */
  protected readonly RandomGeneratorInterface $random;

  /**
   * Sets up the backend with an optional random generator.
   */
  public function __construct(?RandomGeneratorInterface $random = NULL) {
    $this->random = $random ?? new RandomGenerator();
  }

  /**
   * {@inheritdoc}
   */
  public function getRandom(): RandomGeneratorInterface {
    return $this->random;
  }

  /**
   * {@inheritdoc}
   */
  public function bootstrap(): void {}

  /**
   * {@inheritdoc}
   */
  public function isBootstrapped(): bool {
    return TRUE;
  }

}
