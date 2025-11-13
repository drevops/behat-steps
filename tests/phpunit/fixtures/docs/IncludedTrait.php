<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Fixtures;

/**
 * Included trait for testing.
 */
trait IncludedTrait {

  /**
   * Included method.
   *
   * @Then the included should pass
   *
   * @code
   * Then the included should pass
   * @endcode
   */
  public function includedAssertIncluded(): void {}

}
