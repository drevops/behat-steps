<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Fixtures;

use Behat\Step\Then;

/**
 * Excluded trait for testing.
 */
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
