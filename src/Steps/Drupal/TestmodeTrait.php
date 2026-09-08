<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

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
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\RawContext
 */
trait TestmodeTrait {

  /**
   * Enable test mode before an @api scenario tagged with @testmode.
   */
  #[BeforeScenario('@api')]
  public function testmodeBeforeScenario(BeforeScenarioScope $scope): void {
    if ($this->skipTag(__FUNCTION__, $scope) || !$scope->getScenario()->hasTag('testmode')) {
      return;
    }

    $this->drupal();

    self::testmodeEnableTestMode();
  }

  /**
   * Disable test mode after an @api scenario tagged with @testmode.
   */
  #[AfterScenario('@api')]
  public function testmodeAfterScenario(AfterScenarioScope $scope): void {
    if ($this->skipTag(__FUNCTION__, $scope) || !$scope->getScenario()->hasTag('testmode')) {
      return;
    }

    $this->drupal();

    self::testmodeDisableTestMode();
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
