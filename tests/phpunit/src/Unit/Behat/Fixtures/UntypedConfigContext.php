<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures;

use DrevOps\BehatSteps\Behat\Context\RawContext;

/**
 * Context whose declaration method returns something other than an array.
 *
 * The shipped traits declare an `array` return type, which a consuming
 * project's own trait need not.
 */
class UntypedConfigContext extends RawContext {

  /**
   * Declares nothing usable.
   *
   * @return mixed
   *   Deliberately not an array.
   */
  protected function untypedConfigSchema(): mixed {
    return 'not an array';
  }

}
