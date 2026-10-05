<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Fixtures\StepCoverage;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;

/**
 * Trait carrying 1 method per case the step discovery distinguishes.
 */
trait StepCoverageTrait {

  /**
   * Method carrying a Given step.
   */
  #[Given('the fixture exists')]
  public function stepCoverageGiven(): void {}

  /**
   * Method carrying 2 When steps at once.
   */
  #[When('I open the fixture')]
  #[When('I visit the fixture')]
  public function stepCoverageWhen(): void {}

  /**
   * Method carrying a Then step.
   */
  #[Then('the fixture should be open')]
  public function stepCoverageThen(): void {}

  /**
   * Method carrying no step.
   */
  public function stepCoverageHelper(): void {}

}
