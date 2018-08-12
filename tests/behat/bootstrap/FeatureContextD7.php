<?php

/**
 * @file
 * MYSITE Drupal context for Behat testing.
 */

use Behat\Gherkin\Node\PyStringNode;
use Drupal\DrupalExtension\Context\DrupalContext;
use IntegratedExperts\BehatSteps\D7\ContentTrait;
use IntegratedExperts\BehatSteps\D7\EmailTrait;
use IntegratedExperts\BehatSteps\D7\FileDownloadTrait;
use IntegratedExperts\BehatSteps\D7\FieldCollectionTrait;
use IntegratedExperts\BehatSteps\D7\FileTrait;
use IntegratedExperts\BehatSteps\D7\MediaTrait;
use IntegratedExperts\BehatSteps\D7\ParagraphsTrait;
use IntegratedExperts\BehatSteps\D7\TaxonomyTrait;
use IntegratedExperts\BehatSteps\D7\UserTrait;
use IntegratedExperts\BehatSteps\D7\VariableTrait;
use IntegratedExperts\BehatSteps\FieldTrait;
use IntegratedExperts\BehatSteps\LinkTrait;
use IntegratedExperts\BehatSteps\PathTrait;
use IntegratedExperts\BehatSteps\ResponseTrait;

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

}
