<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Drupal;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Hook\AfterScenario;
use Behat\Hook\BeforeScenario;
use Drupal\testmode\Testmode;

/**
 * Configure Drupal Testmode module for controlled testing scenarios.
 *
 * Skip processing with tags: `@behat-steps-skip:testmodeBeforeScenario` and
 * `@behat-steps-skip:testmodeAfterScenario`.
 *
 * Special tags:
 * - `@testmode` - enable for scenario
 */
trait TestmodeTrait {

  /**
   * Enable test mode before test run for scenarios tagged with @testmode.
   */
  #[BeforeScenario]
  public function testmodeBeforeScenario(BeforeScenarioScope $scope): void {
    // @codeCoverageIgnoreStart
    if ($scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__)) {
      return;
    }
    // @codeCoverageIgnoreEnd
    if ($scope->getScenario()->hasTag('testmode')) {
      self::testmodeEnableTestMode();
    }
  }

  /**
   * Disable test mode before test run for scenarios tagged with @testmode.
   */
  #[AfterScenario]
  public function testmodeAfterScenario(AfterScenarioScope $scope): void {
    // @codeCoverageIgnoreStart
    if ($scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__)) {
      return;
    }
    // @codeCoverageIgnoreEnd
    if ($scope->getScenario()->hasTag('testmode')) {
      self::testmodeDisableTestMode();
    }
  }

  /**
   * Enable test mode.
   */
  protected static function testmodeEnableTestMode(): void {
    Testmode::getInstance()->enableTestMode();
  }

  /**
   * Disable test mode.
   */
  protected static function testmodeDisableTestMode(): void {
    Testmode::getInstance()->disableTestMode();
  }

}
