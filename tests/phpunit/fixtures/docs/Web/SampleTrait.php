<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Fixtures\Web;

use Behat\Step\Then;
use DrevOps\BehatSteps\Attribute\Steps;

/**
 * Sample trait for testing.
 */
#[Steps]
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
