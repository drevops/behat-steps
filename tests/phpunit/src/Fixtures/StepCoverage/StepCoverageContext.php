<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Fixtures\StepCoverage;

use Behat\Step\Then;
use DrevOps\BehatSteps\Tests\Fixtures\StepCoverageBaseContext;

/**
 * Context composing the fixture trait on top of a base declared elsewhere.
 */
class StepCoverageContext extends StepCoverageBaseContext {

  use StepCoverageTrait;

  /**
   * Step declared on the context rather than on a trait.
   */
  #[Then('the context step should run')]
  public function contextStep(): void {}

}
