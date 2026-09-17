<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Fixtures\Drupal;

/**
 * Drupal trait carrying a helper for testing.
 */
trait HelperDrupalTrait {

  /**
   * Read the Drupal value.
   */
  protected function helperDrupalValue(): string {
    return 'value';
  }

}
