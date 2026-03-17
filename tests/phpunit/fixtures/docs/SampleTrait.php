<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Fixtures;

use Behat\Step\Then;

/**
 * Sample trait for testing.
 */
trait SampleTrait {

  /**
   * Test method.
   *
   * @code
   * Then the test should pass
   * @endcode
   */
  #[Then('the test should pass')]
  public function sampleAssertTest(): void {}

}
