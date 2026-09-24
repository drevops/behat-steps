<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Fixtures\Web;

use Behat\Step\Then;
use DrevOps\BehatSteps\Attribute\Steps;

/**
 * Second trait for testing.
 */
#[Steps]
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
