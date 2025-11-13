<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Fixtures;

/**
 * Second trait for testing.
 */
trait SecondTrait {

  /**
   * Second method.
   *
   * @Then the second should pass
   *
   * @code
   * Then the second should pass
   * @endcode
   */
  public function secondAssertSecond(): void {}

}
