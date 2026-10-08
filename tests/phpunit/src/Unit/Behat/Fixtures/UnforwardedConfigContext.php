<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures;

use DrevOps\BehatSteps\Behat\Context\WebRawContext;

/**
 * Context whose constructor never calls the one it inherits.
 */
class UnforwardedConfigContext extends WebRawContext {

  /**
   * Constructs an UnforwardedConfigContext, deliberately forwarding nothing.
   */
  public function __construct() {}

}
