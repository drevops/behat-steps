<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Hook\AfterScenario;
use Behat\Hook\BeforeScenario;
use DrevOps\BehatSteps\Backend\Capability\CacheCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\ModuleCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\StateCapabilityInterface;
use DrevOps\BehatSteps\Extension\Config\Option;
use DrevOps\BehatSteps\Extension\Prerequisite\Prerequisite;
use DrevOps\BehatSteps\Extension\Tag;

/**
 * Configure Drupal Testmode module for controlled testing scenarios.
 *
 * Skip processing with tag: `@behat-steps-skip:TestmodeTrait`.
 *
 * Special tags:
 * - `@testmode` - enable for scenario
 *
 * @title Testmode
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\WebRawContext
 */
trait TestmodeTrait {

  /**
   * The tag that runs the scenario in test mode.
   */
  protected const string TESTMODE_TAG = 'testmode';

  /**
   * The state key the Testmode module reads the mode from.
   */
  protected const string TESTMODE_STATE_KEY = 'testmode.enabled';

  /**
   * The cache tag the Testmode module puts on the views it filters.
   */
  protected const string TESTMODE_CACHE_VIEWS = 'testmode:views';

  /**
   * Whether this scenario enabled test mode.
   */
  protected bool $testmodeActive = FALSE;

  /**
   * Enable test mode before a scenario tagged with @testmode.
   */
  #[BeforeScenario]
  public function testmodeBeforeScenario(BeforeScenarioScope $scope): void {
    if ($this->skipTag(__TRAIT__, $scope) || !Tag::has($scope, self::TESTMODE_TAG)) {
      return;
    }

    $this->assertPrerequisites(__TRAIT__, $scope);

    $this->testmodeEnableTestMode();

    $this->testmodeActive = TRUE;
  }

  /**
   * Disable test mode after a scenario that enabled it.
   */
  #[AfterScenario]
  public function testmodeAfterScenario(AfterScenarioScope $scope): void {
    if ($this->skipTag(__TRAIT__, $scope) || !$this->testmodeActive) {
      return;
    }

    $this->testmodeDisableTestMode();

    $this->testmodeActive = FALSE;
  }

  /**
   * Enable test mode.
   */
  public function testmodeEnableTestMode(): void {
    $this->backendFor(StateCapabilityInterface::class)->stateSet(self::TESTMODE_STATE_KEY, TRUE);
    $this->backendFor(CacheCapabilityInterface::class)->cacheInvalidateTags([self::TESTMODE_CACHE_VIEWS]);
  }

  /**
   * Disable test mode.
   */
  public function testmodeDisableTestMode(): void {
    $this->backendFor(StateCapabilityInterface::class)->stateDelete(self::TESTMODE_STATE_KEY);
    $this->backendFor(CacheCapabilityInterface::class)->cacheInvalidateTags([self::TESTMODE_CACHE_VIEWS]);
  }

  /**
   * Declares the options this trait reads.
   *
   * @return array<int, \DrevOps\BehatSteps\Extension\Config\Option>
   *   The options this trait declares.
   */
  protected function testmodeConfigSchema(): array {
    return [
      new Option('enabled', default: TRUE, description: "If `TRUE`, switches the Testmode module's test mode on for a `@testmode` scenario and off after it finishes. If `FALSE`, the tag does nothing."),
    ];
  }

  /**
   * Declares the prerequisites this trait asserts.
   *
   * @return array<int, \DrevOps\BehatSteps\Extension\Prerequisite\Prerequisite>
   *   The prerequisites this trait declares.
   */
  protected function testmodePrerequisites(): array {
    return [
      Prerequisite::capability(StateCapabilityInterface::class),
      Prerequisite::capability(CacheCapabilityInterface::class),
      Prerequisite::check(static fn(ModuleCapabilityInterface $backend): bool => $backend->moduleIsEnabled('testmode'), 'the "testmode" module from the "drupal/testmode" package is enabled'),
    ];
  }

}
