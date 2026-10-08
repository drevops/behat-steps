<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Fixtures;

/**
 * A trait that redeclares a method of the trait it composes.
 */
trait RedeclaredMethodTrait {

  use RedeclaredMethodComposedTrait;

  /**
   * Returns the value.
   */
  protected function redeclaredGet(): string {
    return 'redeclared';
  }

  /**
   * Returns the label.
   */
  protected function redeclaredGetLabel(): string {
    return 'label';
  }

}
