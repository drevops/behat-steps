<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Fixtures;

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
   */
  #[Then('the first should pass')]
  public function firstAssertFirst(): void {}

}
