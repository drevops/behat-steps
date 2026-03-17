<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Fixtures;

use Behat\Step\Then;

/**
 * Second trait for testing.
 */
trait SecondTrait {

  /**
   * Second method.
   *
   * @code
   * Then the second should pass
   * @endcode
   */
  #[Then('the second should pass')]
  public function secondAssertSecond(): void {}

}
