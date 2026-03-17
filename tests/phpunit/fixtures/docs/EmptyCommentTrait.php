<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Fixtures;

use Behat\Step\Given;

/**
 *
 *
 *
 */
trait EmptyCommentTrait {

  /**
   * Test method.
   *
   * @code
   * Given I test empty comment
   * @endcode
   */
  #[Given('I test empty comment')]
  public function emptycommentTestMethod(): void {}

}
