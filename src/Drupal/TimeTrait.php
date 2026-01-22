<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Drupal;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Hook\AfterScenario;

/**
 * Control system time in tests using Drupal state overrides.
 *
 * IMPORTANT: This trait requires your application to use a mockable time
 * service that checks Drupal state for time overrides.
 *
 * Example implementation:
 * - Time service: https://github.com/drevops/behat-steps/blob/main/tests/behat/fixtures_drupal/d11/web/modules/custom/mysite_core/src/Time/Time.php
 * - Time interface: https://github.com/drevops/behat-steps/blob/main/tests/behat/fixtures_drupal/d11/web/modules/custom/mysite_core/src/Time/TimeInterface.php
 * - Service registration: https://github.com/drevops/behat-steps/blob/main/tests/behat/fixtures_drupal/d11/web/modules/custom/mysite_core/mysite_core.services.yml
 */
trait TimeTrait {

  /**
   * Cleans up testing.time state after each scenario.
   */
  #[AfterScenario]
  public function timeCleanup(AfterScenarioScope $scope): void {
    if ($scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__)) {
      return;
    }

    \Drupal::state()->delete('testing.time');
  }

  /**
   * Sets the system time for testing.
   *
   * @param string $value
   *   The time value as Unix timestamp.
   *
   * @When I set system time to :value
   *
   * @code
   * When I set system time to "1737849900"
   * @endcode
   */
  public function timeSet(string $value): void {
    \Drupal::state()->set('testing.time', (int) $value);
  }

  /**
   * Resets the system time to real time.
   *
   * @When I reset system time
   *
   * @code
   * When I reset system time
   * @endcode
   */
  public function timeReset(): void {
    \Drupal::state()->delete('testing.time');
  }

}
