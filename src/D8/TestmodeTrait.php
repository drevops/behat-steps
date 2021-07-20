<?php

namespace DrevOps\BehatSteps\D8;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Drupal\testmode\Testmode;

/**
 * Trait TestmodeTrait.
 *
 * What is Testmode?
 *
 * @see https://www.drupal.org/project/testmode
 *
 * Running a Behat test on the site with existing content may result in
 * FALSE positives because of the live content being mixed with test content.
 *
 * Example: list of 3 featured articles. When the test creates 3 articles and
 * make them featured, there may be existing featured articles that will confuse
 * tests resulting in false positive failure.
 *
 * Include this trait in your FeatureContext.php file to enable Testmode's test
 * mode for tests tagged with 'testmode'.
 */
trait TestmodeTrait {

  /**
   * Enable test mode before test run for scenarios tagged with @testmode.
   *
   * @BeforeScenario
   */
  public function testmodeBeforeScenarioEnableTestMode(BeforeScenarioScope $scope) {
    // Allow to skip this by adding a tag.
    if ($scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__)) {
      return;
    }

    if ($scope->getScenario()->hasTag('testmode')) {
      self::testmodeEnableTestMode();
    }
  }

  /**
   * Disable test mode before test run for scenarios tagged with @testmode.
   *
   * @AfterScenario
   */
  public function testmodeBeforeScenarioDisableTestMode(AfterScenarioScope $scope) {
    // Allow to skip this by adding a tag.
    if ($scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__)) {
      return;
    }

    if ($scope->getScenario()->hasTag('testmode')) {
      self::testmodeDisableTestMode();
    }
  }

  /**
   * Enable test mode.
   */
  protected static function testmodeEnableTestMode() {
    return Testmode::getInstance()->enableTestMode();
  }

  /**
   * Disable test mode.
   */
  protected static function testmodeDisableTestMode() {
    return Testmode::getInstance()->disableTestMode();
  }

}
