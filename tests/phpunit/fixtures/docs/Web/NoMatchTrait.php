<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Fixtures\Web;

use DrevOps\BehatSteps\Attribute\Steps;

/**
 * A test trait with no matching methods.
 */
#[Steps]
trait NoMatchTrait {

  public function otherMethod(): void {}

}
