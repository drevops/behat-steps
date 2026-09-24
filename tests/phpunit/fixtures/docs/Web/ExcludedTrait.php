<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Fixtures\Web;

use Behat\Step\Then;
use DrevOps\BehatSteps\Attribute\Steps;

/**
 * Excluded trait for testing.
 */
#[Steps]
trait ExcludedTrait {

  /**
   * Excluded method.
   *
   * @code
   * Then the excluded should pass
   * @endcode
   */
  #[Then('the excluded should pass')]
  public function excludedAssertExcluded(): void {}

}
