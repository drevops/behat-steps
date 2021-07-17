<?php

namespace IntegratedExperts\BehatSteps\D8;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\PyStringNode;
use Drupal\Core\Database\Database;

/**
 * Trait EmailTrait.
 *
 * Behat trait for email interactions.
 */
trait EmailTrait {

  /**
   * @BeforeScenario
   */
  public function emailBeforeScenarioEnableTestEmailSystem(BeforeScenarioScope $scope) {
    // Allow to skip this by adding a tag.
    if ($scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__)) {
      return;
    }

    if ($scope->getScenario()->hasTag('email')) {
      self::emailEnableTestEmailSystem();
    }
  }

  /**
   * @AfterScenario
   */
  public function emailAfterScenarioDisableTestEmailSystem(AfterScenarioScope $scope) {
    // Allow to skip this by adding a tag.
    if ($scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__)) {
      return;
    }

    if ($scope->getScenario()->hasTag('email')) {
      self::emailDisableTestEmailSystem();
    }
  }

  /**
   * @Given I enable the test email system
   */
  public function emailEnableTestEmailSystem() {
    // Store the original system to restore after the scenario.
    $original_test_system = self::emailGetMailSystemDefault();
    // But store only if previous has not been stored yet.
    if (!self::emailGetMailSystemOriginal()) {
      self::emailSetMailSystemOriginal($original_test_system);
    }
    // Set the test system.
    self::emailSetMailSystemDefault('test_mail_collector');
    // Flush the email buffer, allowing us to reuse this step definition
    // to clear existing mail.
    self::emailClearTestEmailSystemQueue();
  }

  /**
   * @Given I disable the test email system
   */
  public function emailDisableTestEmailSystem() {
    $original_test_system = self::emailGetMailSystemOriginal();
    self::emailDeleteMailSystemOriginal();
    // Restore the original system to after the scenario.
    self::emailSetMailSystemDefault($original_test_system);

    self::emailClearTestEmailSystemQueue(TRUE);
  }

  /**
   * @When I clear the test email system queue
   */
  public function emailClearTestEmailSystemQueue($force = FALSE) {
    if (!$force && !self::emailGetMailSystemOriginal()) {
      throw new \RuntimeException('Clearing testing email system queue can be done only when email testing system is activated. Add @email tag or "When I enable the test email system" step definition to the scenario.');
    }

    \Drupal::state()->set('system.test_mail_collector', []);
  }

  /**
   * @Then an email is sent to :address
   */
  public function emailAssertEmailIsSentTo($address) {
    foreach (self::emailGetCollectedEmails() as $email) {
      if ($email['to'] == $address) {
        return;
      }
    }

    throw new \Exception(sprintf('Unable to find email sent to "%s" retrieved from test email collector.', $address));
  }

  /**
   * @Then no emails were sent
   */
  public function emailAssertNoEmailsWereSent() {
    if (count(self::emailGetCollectedEmails()) > 0) {
      throw new \Exception('No emails were supposed to be sent');
    }
  }

  /**
   * @Then /^an email to "(?P<name>[^"]*)" user is "(?P<action>[^"]*)" with "(?P<field>[^"]*)" content:$/
   */
  public function emailAssertEmailToUserIsActionWithContent($name, $action, $field, PyStringNode $string) {
    $user = $name == 'current' && !empty(self::user) ? self::user : user_load_by_name($name);
    if (!$user) {
      throw new \RuntimeException(sprintf('Unable to find a user "%s"', $name));
    }

    if ($action == 'sent') {
      $this->emailAssertEmailContains('to', new PyStringNode([$user->mail], 0), TRUE);
      $this->emailAssertEmailContains($field, $string);
    }
    elseif ($action == 'not sent') {
      $this->emailAssertEmailNotContains($field, $string);
    }
    else {
      throw new \RuntimeException(sprintf('Provided action "%s" is not from a list of allowed actions', $action));
    }
  }

  /**
   * @Then an email :field contains
   * @Then an email :field contains:
   */
  public function emailAssertEmailContains($field, PyStringNode $string, $exact = FALSE) {
    if (!in_array($field, ['subject', 'body', 'to', 'from'])) {
      throw new \RuntimeException(sprintf('Invalid email field %s was specified for assertion', $field));
    }

    $string = strval($string);
    $string = $exact ? $string : trim(preg_replace('/\s+/', ' ', $string));
    foreach (self::emailGetCollectedEmails() as $email) {
      $field_string = $email[$field] ?? '';
      $field_string = $exact ? $field_string : trim(preg_replace('/\s+/', ' ', $field_string));
      if (strpos($field_string, $string) !== FALSE) {
        return $email;
      }
    }

    throw new \Exception(sprintf('Unable to find email with%s text "%s" in field "%s" retrieved from test email collector.', ($exact ? ' exact' : ''), $string, $field));
  }

  /**
   * @Then an email :field contains exact
   * @Then an email :field contains exact:
   */
  public function emailAssertEmailContainsExact($field, PyStringNode $string) {
    $this->emailAssertEmailContains($field, $string, TRUE);
  }

  /**
   * @Then an email :field does not contain
   * @Then an email :field does not contain:
   */
  public function emailAssertEmailNotContains($field, PyStringNode $string, $exact = FALSE) {
    if (!in_array($field, ['subject', 'body', 'to', 'from'])) {
      throw new \RuntimeException(sprintf('Invalid email field %s was specified for assertion', $field));
    }

    $string = strval($string);
    $string = $exact ? $string : trim(preg_replace('/\s+/', ' ', $string));
    foreach (self::emailGetCollectedEmails() as $email) {
      $field_string = $exact ? $email[$field] : trim(preg_replace('/\s+/', ' ', $email[$field]));
      if (strpos($field_string, $string) !== FALSE) {
        throw new \Exception(sprintf('Found email with%s text "%s" in field "%s" retrieved from test email collector, but should not.', ($exact ? ' exact' : ''), $string, $field));
      }
    }
  }

  /**
   * @Then an email :field does not contain exact
   * @Then an email :field does not contain exact:
   */
  public function emailAssertEmailNotContainsExact($field, PyStringNode $string) {
    $this->emailAssertEmailNotContains($field, $string, TRUE);
  }

  /**
   * @When I follow the link number :number in the email with the subject
   * @When I follow the link number :number in the email with the subject:
   */
  public function emailFollowLinkNumber($number, PyStringNode $subject) {
    $email = $this->emailAssertEmailContains('subject', $subject);
    $links = self::emailExtractLinks($email['params']['body'] ?? $email['body']);
    if (empty($links)) {
      throw new \Exception(sprintf('No links were found in the email with subject %s', $subject));
    }
    if (count($links) < $number) {
      throw new \Exception(sprintf('The link with number %s was not found among %s links', $number, count($links)));
    }

    $link = $links[$number - 1];
    print $link;
    self::getSession()->visit($link);
  }

  /**
   * @Then file :name attached to the email with the subject
   * @Then file :name attached to the email with the subject:
   */
  public function emailAssertEmailContainsAttachmentWithName($name, PyStringNode $subject) {
    $email = $this->emailAssertEmailContains('subject', $subject);

    foreach ($email['params']['attachments'] as $attachment) {
      if ($attachment['filename'] == $name) {
        return;
      }
    }

    throw new \Exception(sprintf('No attachments were found in the email with subject %s', $subject));
  }

  /**
   * Get default mail system value.
   */
  protected static function emailGetMailSystemDefault() {
    return \Drupal::config('system.mail')->get('interface.default');
  }

  /**
   * Set default mail system value.
   */
  protected static function emailSetMailSystemDefault($value) {
    \Drupal::configFactory()->getEditable('system.mail')->set('interface.default', $value)->save();

    // Maisystem module completely takes over default interface, so we need to
    // update it as well if the module is installed.
    // @note: For some unknown reasons, we do not need to reset this back to
    // the original values after the test. The values in the configuration
    // will not be overridden.
    if (\Drupal::service('module_handler')->moduleExists('mailsystem')) {
      \Drupal::configFactory()->getEditable('mailsystem.settings')
        ->set('defaults.sender', $value)
        ->set('defaults.formatter', $value)
        ->save();
    }
  }

  /**
   * Get original mail system value.
   */
  protected static function emailGetMailSystemOriginal() {
    return \Drupal::config('system.mail_original')->get('interface.default');
  }

  /**
   * Set original mail system value.
   */
  protected static function emailSetMailSystemOriginal($value) {
    return \Drupal::configFactory()->getEditable('system.mail_original')->set('interface.default', $value)->save();
  }

  /**
   * Remove original mail system value.
   */
  protected static function emailDeleteMailSystemOriginal() {
    return \Drupal::configFactory()->getEditable('system.mail_original')->delete();
  }

  /**
   * Get emails collected during the test.
   */
  protected static function emailGetCollectedEmails() {
    // Directly read data from the database to avoid cache invalidation that
    // may corrupt the system under test.
    $emails = array_map('unserialize', Database::getConnection()->query("SELECT name, value FROM {key_value} WHERE name = 'system.test_mail_collector'")->fetchAllKeyed());

    return !empty($emails['system.test_mail_collector']) ? $emails['system.test_mail_collector'] : [];
  }

  /**
   * Extract all links from provided string.
   *
   * @param string $string
   *   String to extract links from.
   *
   * @return array
   *   Array of extracted links.
   */
  protected static function emailExtractLinks($string) {
    // Correct links before extraction.
    $pattern = '(?xi)\b((?:https?://|www\d{0,3}[.]|[a-z0-9.\-]+[.][a-z]{2,4}/)(?:[^\s()<>]+|\(([^\s()<>]+|(\([^\s()<>]+\)))*\))+(?:\(([^\s()<>]+|(\([^\s()<>]+\)))*\)|[^\s`!()\[\]{};:\'".,<>?«»“”‘’]))';
    $string = preg_replace_callback("#$pattern#i", function ($matches) {
      return preg_match('!^https?://!i', $matches[0]) ? $matches[0] : "http://$matches[0]";
    }, $string);

    preg_match_all("#$pattern#i", $string, $matches);

    return !empty($matches[0]) ? $matches[0] : [];
  }

}
