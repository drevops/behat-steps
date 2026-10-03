<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Hook\AfterScenario;
use Behat\Step\When;
use DrevOps\BehatSteps\Backend\Capability\CoreCapabilityInterface;
use DrevOps\BehatSteps\Behat\Config\Option;

/**
 * Control system time in tests using Drupal state overrides.
 *
 * This trait requires the consuming application to use a mockable time
 * service that checks Drupal state for time overrides.
 *
 * Example implementation:
 * - Time service: https://github.com/drevops/behat-steps/blob/main/tests/behat/fixtures_drupal/d11/web/modules/custom/mysite_core/src/Time/Time.php
 * - Time interface: https://github.com/drevops/behat-steps/blob/main/tests/behat/fixtures_drupal/d11/web/modules/custom/mysite_core/src/Time/TimeInterface.php
 * - Service registration: https://github.com/drevops/behat-steps/blob/main/tests/behat/fixtures_drupal/d11/web/modules/custom/mysite_core/mysite_core.services.yml
 *
 * Skip processing with tag: `@behat-steps-skip:TimeTrait`.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
 */
trait TimeTrait {

  /**
   * Whether a step in this scenario overrode the system time.
   */
  protected bool $timeWasSet = FALSE;

  /**
   * Cleans up testing.time state after each scenario.
   */
  #[AfterScenario]
  public function timeAfterScenario(AfterScenarioScope $scope): void {
    // A scenario that never set the time has nothing to clean up, and
    // resolving a backend would fail a suite that lists none reaching Drupal.
    if (!$this->timeWasSet || $this->skipTag(__TRAIT__, $scope)) {
      $this->timeWasSet = FALSE;

      return;
    }

    $this->timeWasSet = FALSE;

    $this->backendFor(CoreCapabilityInterface::class);

    \Drupal::state()->delete('testing.time');
  }

  /**
   * Sets the system time for testing.
   *
   * @param string $value
   *   The time value as Unix timestamp.
   *
   * @code
   *   When I set the system time to the value "1737849900"
   * @endcode
   */
  #[When('I set the system time to the value :value')]
  public function timeSet(string $value): void {
    $this->backendFor(CoreCapabilityInterface::class);

    $this->timeWasSet = TRUE;

    \Drupal::state()->set('testing.time', (int) $value);
  }

  /**
   * Resets the system time to real time.
   *
   * @code
   * When I reset the system time
   * @endcode
   */
  #[When('I reset the system time')]
  public function timeReset(): void {
    $this->backendFor(CoreCapabilityInterface::class);

    \Drupal::state()->delete('testing.time');
  }

  /**
   * Declares the options this trait reads.
   *
   * @return array<int, \DrevOps\BehatSteps\Behat\Config\Option>
   *   The options this trait declares.
   */
  protected function timeConfigSchema(): array {
    return [
      new Option('enabled', default: TRUE, description: 'Restore the site clock after a scenario that moved it.'),
    ];
  }

}
