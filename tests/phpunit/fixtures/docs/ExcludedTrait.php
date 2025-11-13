<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Fixtures;

/**
 * Excluded trait for testing.
 */
trait ExcludedTrait {

  /**
   * Excluded method.
   *
   * @Then the excluded should pass
   *
   * @code
   * Then the excluded should pass
   * @endcode
   */
  public function excludedAssertExcluded(): void {}

}
