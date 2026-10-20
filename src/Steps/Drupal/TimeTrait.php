<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Hook\AfterScenario;
use Behat\Step\When;
use DrevOps\BehatSteps\Backend\Drupal\Capability\StateCapabilityInterface;
use DrevOps\BehatSteps\Extension\Config\Option;
use DrevOps\BehatSteps\Extension\Prerequisite\Prerequisite;
use DrevOps\BehatSteps\Steps\Helper\Web\StringTrait;

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
 * @title System time
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\WebRawContext
 */
trait TimeTrait {

  use StringTrait;

  /**
   * Whether a step in this scenario overrode the system time.
   */
  protected bool $timeWasSet = FALSE;

  /**
   * Cleans up testing.time state after each scenario.
   */
  #[AfterScenario]
  public function timeAfterScenario(AfterScenarioScope $scope): void {
    // A scenario that never set the time has nothing to clean up. Resolving a
    // backend would fail a suite that lists none reaching Drupal.
    if ($this->skipTag(__TRAIT__, $scope) || !$this->timeWasSet) {
      $this->timeWasSet = FALSE;

      return;
    }

    $this->timeWasSet = FALSE;

    $this->backendFor(StateCapabilityInterface::class)->stateDelete('testing.time');
  }

  /**
   * Sets the system time for testing.
   *
   * @param string $value
   *   The time value as Unix timestamp.
   *
   * @code
   *   When the system time is set to "1737849900"
   * @endcode
   *
   * @actor:system
   */
  #[When('the system time is set to :value')]
  public function timeSet(string $value): void {
    $backend = $this->backendFor(StateCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    $this->timeWasSet = TRUE;

    $backend->stateSet('testing.time', $this->stringParseInteger($value, 'value'));
  }

  /**
   * Resets the system time to real time.
   *
   * @code
   * When the system time is reset
   * @endcode
   *
   * @actor:system
   */
  #[When('the system time is reset')]
  public function timeReset(): void {
    $backend = $this->backendFor(StateCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    $backend->stateDelete('testing.time');
  }

  /**
   * Declares the options this trait reads.
   *
   * @return array<int, \DrevOps\BehatSteps\Extension\Config\Option>
   *   The options this trait declares.
   */
  protected function timeConfigSchema(): array {
    return [
      new Option('enabled', default: TRUE, description: 'If `TRUE`, removes the time set by `the system time is set to :value` after the scenario finishes. If `FALSE`, the site keeps that time.'),
    ];
  }

  /**
   * Declares the prerequisites this trait asserts.
   *
   * @return array<int, \DrevOps\BehatSteps\Extension\Prerequisite\Prerequisite>
   *   The prerequisites this trait declares.
   */
  protected function timePrerequisites(): array {
    return [
      Prerequisite::capability(StateCapabilityInterface::class),
    ];
  }

}
