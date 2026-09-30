<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Fixtures;

use Behat\Step\Given;

/**
 * Base context declared outside the directory the step discovery reads.
 */
class StepCoverageBaseContext {

  /**
   * Step the discovery skips, because its file is outside the directory.
   */
  #[Given('the base fixture exists')]
  public function baseStep(): void {
  }

}
