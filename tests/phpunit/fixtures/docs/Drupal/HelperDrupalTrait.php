<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Fixtures\Drupal;

use DrevOps\BehatSteps\Attribute\Steps;

/**
 * Drupal trait carrying a helper for testing.
 */
#[Steps]
trait HelperDrupalTrait {

  /**
   * Read the Drupal value.
   */
  public function helperDrupalValue(): string {
    return 'value';
  }

}
