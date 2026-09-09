<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Hook\AfterScenario;
use Behat\Step\When;

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
   * Whether a step in this scenario overrode the system time.
   */
  protected bool $timeWasSet = FALSE;

  /**
   * Cleans up testing.time state after each scenario.
   */
  #[AfterScenario('@api')]
  public function timeCleanup(AfterScenarioScope $scope): void {
    // A scenario that never set the time has nothing to clean up, and asking
    // for the driver would fail one running on a driver that never had it.
    if (!$this->timeWasSet || $this->skipTag(__FUNCTION__, $scope)) {
      $this->timeWasSet = FALSE;

      return;
    }

    $this->timeWasSet = FALSE;

    $this->assertDrupal();

    \Drupal::state()->delete('testing.time');
  }

  /**
   * Sets the system time for testing.
   *
   * @param string $value
   *   The time value as Unix timestamp.
   *
   * @code
   *   When I set system time to "1737849900"
   * @endcode
   */
  #[When('I set system time to :value')]
  public function timeSet(string $value): void {
    $this->assertDrupal();

    $this->timeWasSet = TRUE;

    \Drupal::state()->set('testing.time', (int) $value);
  }

  /**
   * Resets the system time to real time.
   *
   * @code
   * When I reset system time
   * @endcode
   */
  #[When('I reset system time')]
  public function timeReset(): void {
    $this->assertDrupal();

    \Drupal::state()->delete('testing.time');
  }

}
