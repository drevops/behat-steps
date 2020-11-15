<?php

/**
 * @file
 * Feature context for testing Behat-steps traits for Drupal 8.
 *
 * This is a test for the test framework itself. Consumer project should not
 * use any steps or functions from this file.
 *
 * However, consumer sites can use this file as an example of traits inclusion.
 * The usage of these traits can be seen in *.feature files.
 */

use Behat\Behat\Hook\Scope\AfterFeatureScope;
use Drupal\DrupalExtension\Context\DrupalContext;
use IntegratedExperts\BehatSteps\D8\UserTrait;
use IntegratedExperts\BehatSteps\D8\WatchdogTrait;
use IntegratedExperts\BehatSteps\FieldTrait;
use IntegratedExperts\BehatSteps\LinkTrait;
use IntegratedExperts\BehatSteps\PathTrait;
use IntegratedExperts\BehatSteps\ResponseTrait;

/**
 * Defines application features from the specific context.
 */
class FeatureContextD8 extends DrupalContext {

  use FieldTrait;
  use LinkTrait;
  use PathTrait;
  use ResponseTrait;
  use UserTrait;
  use WatchdogTrait;

  /**
   * @Then user :name does not exists
   */
  public function userDoesNotExist($name) {
    // We need to check that user was removed from both DB and test variables.
    $user = user_load($name);

    if ($user) {
      throw new \Exception(sprintf('User "%s" exists in DB but should not', $name));
    }

    try {
      $this->getUserManager()->getUser($name);
    }
    catch (\Exception $exception) {
      return;
    }

    throw new \Exception(sprintf('User "%s" does not exist in DB, but still exists in test variables', $name));
  }

  /**
   * @Given set Drupal8 watchdog error level :level
   */
  public function setWatchdogErrorDrupal8($level) {
    \Drupal::logger('php')->log($level, 'test');
  }

  /**
   * Clean watchdog after feature with an error.
   *
   * @AfterFeature @errorcleanup
   */
  public static function cleanWatchdog(AfterFeatureScope $scope) {
    if (db_table_exists('watchdog')) {
      db_truncate('watchdog')->execute();
    }
  }

}
