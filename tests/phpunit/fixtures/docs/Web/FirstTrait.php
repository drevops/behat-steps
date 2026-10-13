<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Fixtures\Web;

use Behat\Step\Then;

/**
 * First trait for testing.
 */
trait FirstTrait {

  /**
   * First method.
   *
   * @code
   * Then the first should pass
   * @endcode
   *
   * @actor:system
   */
  #[Then('the first should pass')]
  public function firstAssertFirst(): void {}

}
