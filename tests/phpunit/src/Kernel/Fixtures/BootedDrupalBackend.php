<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Fixtures;

use DrevOps\BehatSteps\Backend\Drupal\DrupalBackend;

/**
 * Drupal backend over the kernel a test already booted.
 *
 * 'bootstrap()' only records the flag, because booting Drupal again would
 * replace the kernel the test runs in.
 */
class BootedDrupalBackend extends DrupalBackend {

  /**
   * {@inheritdoc}
   */
  public function bootstrap(): void {
    $this->bootstrapped = TRUE;
  }

}
