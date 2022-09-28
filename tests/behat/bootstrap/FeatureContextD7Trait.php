<?php

/**
 * @file
 * Feature context trait for testing Behat-steps for Drupal 7.
 *
 * This is a test for the test framework itself. Consumer project should not
 * use any steps or functions from this file.
 *
 * phpcs:disable Drupal.Commenting.DocComment.MissingShort
 */

use Behat\Behat\Hook\Scope\AfterFeatureScope;
use Behat\Gherkin\Node\PyStringNode;

/**
 * Defines application features from the specific context.
 */
trait FeatureContextD7Trait {

  /**
   * Assert that a user does not exist.
   *
   * @Then user :name does not exist
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
   * Send email via Drupal.
   *
   * @When I send test email to :email with
   * @When I send test email to :email with:
   */
  public function sendTestEmail($email, PyStringNode $string) {
    drupal_mail('mysite_core', 'test_email', $email, language_default(), ['body' => $string], FALSE);
  }

  /**
   * @Given I set test variable :name to value :value
   */
  public function setTestVariable($name, $value) {
    variable_set($name, (string) $value);
  }

  /**
   * @Given I delete test variable :name
   */
  public function deleteTestVariable($name) {
    variable_del($name);
  }

  /**
   * @Then :file_name file object exists
   */
  public function fileObjectExist($file_name) {
    $file_name = basename($file_name);
    $file_name_in_db = file_load_multiple([], ['filename' => $file_name]);
    if ($file_name !== current($file_name_in_db)->filename) {
      throw new \Exception(sprintf('"%s" file does not exist in DB, but it should', $file_name));
    }
  }

  /**
   * @Given set Drupal7 watchdog error level :level
   * @Given set Drupal7 watchdog error level :level of type :type
   */
  public function setWatchdogErrorDrupal7($level, $type = 'php') {
    watchdog($type, 'test', [], $level);
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
