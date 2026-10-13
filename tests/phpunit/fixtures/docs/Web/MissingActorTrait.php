<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Fixtures\Web;

use Behat\Step\Then;

/**
 * Step without an actor tag, for testing.
 */
trait MissingActorTrait {

  /**
   * Test method.
   *
   * @code
   * Then the actor should be declared
   * @endcode
   */
  #[Then('the actor should be declared')]
  public function missingActorAssertDeclared(): void {}

}
