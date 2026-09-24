<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Fixtures\Web;

use Behat\Step\Then;
use DrevOps\BehatSteps\Attribute\Steps;

/**
 * Included trait for testing.
 */
#[Steps]
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
