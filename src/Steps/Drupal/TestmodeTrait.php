<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Hook\AfterScenario;
use Behat\Hook\BeforeScenario;
use DrevOps\BehatSteps\Backend\Capability\CoreCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\ModuleCapabilityInterface;
use DrevOps\BehatSteps\Behat\Config\Option;
use DrevOps\BehatSteps\Behat\Prerequisite\Prerequisite;
use DrevOps\BehatSteps\Behat\Tag;
use Drupal\testmode\Testmode;

/**
 * Configure Drupal Testmode module for controlled testing scenarios.
 *
 * Skip processing with tag: `@behat-steps-skip:TestmodeTrait`.
 *
 * Special tags:
 * - `@testmode` - enable for scenario
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
 */
trait TestmodeTrait {

  /**
   * The tag that runs the scenario in test mode.
   */
  protected const string TESTMODE_TAG = 'testmode';

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

    $this->assertPrerequisites(__TRAIT__);

    $this->backendFor(CoreCapabilityInterface::class);

    static::testmodeEnableTestMode();

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

    $this->backendFor(CoreCapabilityInterface::class);

    static::testmodeDisableTestMode();

    $this->testmodeActive = FALSE;
  }

  /**
   * Enable test mode.
   */
  public static function testmodeEnableTestMode(): void {
    Testmode::getInstance()->enableTestMode();
  }

  /**
   * Disable test mode.
   */
  public static function testmodeDisableTestMode(): void {
    Testmode::getInstance()->disableTestMode();
  }

  /**
   * Declares the options this trait reads.
   *
   * @return array<int, \DrevOps\BehatSteps\Behat\Config\Option>
   *   The options this trait declares.
   */
  protected function testmodeConfigSchema(): array {
    return [
      new Option('enabled', default: TRUE, description: 'Enable the Testmode module for a `@testmode` scenario and disable it afterwards.'),
    ];
  }

  /**
   * Declares the prerequisites this trait asserts.
   *
   * @return array<int, \DrevOps\BehatSteps\Behat\Prerequisite\Prerequisite>
   *   The prerequisites this trait declares.
   */
  protected function testmodePrerequisites(): array {
    return [
      Prerequisite::capability(CoreCapabilityInterface::class),
      Prerequisite::check(static fn(ModuleCapabilityInterface $backend): bool => $backend->moduleIsEnabled('testmode'), 'the "testmode" module from the "drupal/testmode" package is enabled'),
    ];
  }

}
