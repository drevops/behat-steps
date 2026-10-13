<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Extension\Fixtures;

use DrevOps\BehatSteps\WebRawContext;

/**
 * Context whose constructor never calls the one it inherits.
 */
class UnforwardedConfigContext extends WebRawContext {

  /**
   * Constructs an UnforwardedConfigContext, deliberately forwarding nothing.
   */
  public function __construct() {}

}
