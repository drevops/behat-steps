<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Fixtures;

use Behat\Step\Then;

/**
 * Included trait for testing.
 */
trait IncludedTrait {

  /**
   * Included method.
   *
   * @code
   * Then the included should pass
   * @endcode
   */
  #[Then('the included should pass')]
  public function includedAssertIncluded(): void {}

}
