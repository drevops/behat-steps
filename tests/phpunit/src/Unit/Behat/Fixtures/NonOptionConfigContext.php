<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures;

use DrevOps\BehatSteps\Behat\Context\WebRawContext;

/**
 * Context whose declaration method lists something other than an option.
 */
class NonOptionConfigContext extends WebRawContext {

  /**
   * Declares an entry that is not an option.
   *
   * @return array<int, mixed>
   *   Deliberately not a list of options.
   */
  protected function nonOptionConfigSchema(): array {
    return ['not an option'];
  }

}
