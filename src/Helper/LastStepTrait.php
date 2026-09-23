<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Helper;

use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;

/**
 * Identifies the last step of the running scenario.
 *
 * A trait that reports once per scenario from an 'AfterStep' hook composes
 * this to tell the final step from the rest.
 *
 * This is an internal trait and should not be used directly in step
 * definitions.
 */
trait LastStepTrait {

  /**
   * Line of the last step of the current scenario.
   */
  protected int $lastStepLine = 0;

  /**
   * Record the line of the scenario's last step.
   *
   * Step scopes expose no scenario, so the line is resolved here and compared
   * later.
   */
  protected function lastStepCapture(BeforeScenarioScope $scope): void {
    $steps = $scope->getScenario()->getSteps();
    $last = end($steps);

    $this->lastStepLine = $last === FALSE ? 0 : $last->getLine();
  }

  /**
   * Whether the given step is the last step of the current scenario.
   *
   * Outline examples reuse the outline's step lines and a background runs as a
   * separate step container, so the line identifies the step in both.
   */
  protected function lastStepReached(AfterStepScope $scope): bool {
    return $this->lastStepLine !== 0 && $scope->getStep()->getLine() === $this->lastStepLine;
  }

}
