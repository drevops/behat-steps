<?php

/**
 * @file
 * Feature context for testing Behat-steps traits for Drupal 7.
 *
 * This is a test for the test framework itself. Consumer project should not
 * use any steps or functions from this file.
 *
 * However, consumer sites can use this file as an example of traits inclusion.
 * The usage of these traits can be seen in *.feature files.
 */

use Behat\Behat\Hook\Scope\AfterFeatureScope;
use Behat\Gherkin\Node\PyStringNode;
use Drupal\DrupalExtension\Context\DrupalContext;
use DrevOps\BehatSteps\D7\ContentTrait;
use DrevOps\BehatSteps\D7\EmailTrait;
use DrevOps\BehatSteps\D7\FieldCollectionTrait;
use DrevOps\BehatSteps\D7\FileDownloadTrait;
use DrevOps\BehatSteps\D7\FileTrait;
use DrevOps\BehatSteps\D7\MediaTrait;
use DrevOps\BehatSteps\D7\ParagraphsTrait;
use DrevOps\BehatSteps\D7\TaxonomyTrait;
use DrevOps\BehatSteps\D7\UserTrait;
use DrevOps\BehatSteps\D7\VariableTrait;
use DrevOps\BehatSteps\D7\WatchdogTrait;
use DrevOps\BehatSteps\FieldTrait;
use DrevOps\BehatSteps\LinkTrait;
use DrevOps\BehatSteps\PathTrait;
use DrevOps\BehatSteps\ResponseTrait;
use DrevOps\BehatSteps\WaitTrait;

/**
 * Defines application features from the specific context.
 */
class FeatureContextD7 extends DrupalContext {

  use ContentTrait;
  use EmailTrait;
  use FieldCollectionTrait;
  use FieldTrait;
  use FileDownloadTrait;
  use FileTrait;
  use LinkTrait;
  use MediaTrait;
  use ParagraphsTrait;
  use PathTrait;
  use ResponseTrait;
  use TaxonomyTrait;
  use UserTrait;
  use VariableTrait;
  use WaitTrait;
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
   */
  public function setWatchdogErrorDrupal7($level) {
    watchdog('php', 'test', [], $level);
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
