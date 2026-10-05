<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend;

use Drupal\Component\Utility\Random;

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
  protected readonly Random $random;

  /**
   * Sets up the backend with an optional random generator.
   */
  public function __construct(?Random $random = NULL) {
    $this->random = $random ?? new Random();
  }

  /**
   * {@inheritdoc}
   */
  public function getRandom(): Random {
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
