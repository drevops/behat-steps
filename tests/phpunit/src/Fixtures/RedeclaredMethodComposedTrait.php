<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Fixtures;

/**
 * A trait whose method a composing trait redeclares.
 */
trait RedeclaredMethodComposedTrait {

  /**
   * Returns the value.
   */
  protected function redeclaredGet(): string {
    return 'composed';
  }

  /**
   * Returns the count.
   */
  protected function redeclaredGetCount(): int {
    return 1;
  }

}
