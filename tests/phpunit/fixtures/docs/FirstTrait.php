<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Fixtures;

/**
 * First trait for testing.
 */
trait FirstTrait {

  /**
   * First method.
   *
   * @Then the first should pass
   *
   * @code
   * Then the first should pass
   * @endcode
   */
  public function firstAssertFirst(): void {}

}
