<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Fixtures\Web;

use Behat\Step\Then;
use DrevOps\BehatSteps\Attribute\Steps;

/**
 * First trait for testing.
 */
#[Steps]
trait FirstTrait {

  /**
   * First method.
   *
   * @code
   * Then the first should pass
   * @endcode
   */
  #[Then('the first should pass')]
  public function firstAssertFirst(): void {}

}
