<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Fixtures\Web;

use Behat\Step\Then;

/**
 * Step with an unknown actor tag, for testing.
 */
trait UnknownActorTrait {

  /**
   * Test method.
   *
   * @code
   * Then the actor should be known
   * @endcode
   *
   * @actor:interactive
   */
  #[Then('the actor should be known')]
  public function unknownActorAssertKnown(): void {}

}
