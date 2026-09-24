<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Fixtures\Drupal;

use DrevOps\BehatSteps\Attribute\Steps;

/**
 * Drupal trait for testing.
 */
#[Steps]
trait DrupalTrait {

  public function drupalAssertTest(): void {}

}
