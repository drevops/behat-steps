<?php

namespace IntegratedExperts\BehatSteps\D7;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\PyStringNode;

/**
 * Trait EmailTrait.
 *
 * @package IntegratedExperts\BehatSteps\D7
 */
trait EmailTrait {

  /**
   * @BeforeScenario
   */
  public function emailBeforeScenarioEnableTestEmailSystem(BeforeScenarioScope $scope) {
    if ($scope->getScenario()->hasTag('email')) {
      $this->emailEnableTestEmailSystem();
    }
  }

  /**
   * @AfterScenario
   */
  public function emailAfterScenarioDisableTestEmailSystem(AfterScenarioScope $scope) {
    if ($scope->getScenario()->hasTag('email')) {
      $this->emailDisableTestEmailSystem();
    }
  }

  /**
   * Enable test email system.
   *
   * @Given I enable the test email system
   */
  public function emailEnableTestEmailSystem() {
    // Store the original system to restore after the scenario.
    $original_test_system = variable_get('mail_system', ['default-system' => 'DefaultMailSystem']);
    // But store only if previous has not been stored yet.
    if (!variable_get('mail_system_original')) {
      variable_set('mail_system_original', $original_test_system);
    }
    // Set the test system.
    variable_set('mail_system', ['default-system' => 'TestingMailSystem']);
    // Flush the email buffer, allowing us to reuse this step definition
    // to clear existing mail.
    $this->emailClearTestEmailSystemQueue();
  }

  /**
   * Disable test email system.
   *
   * @Given I disable the test email system
   */
  public function emailDisableTestEmailSystem() {
    $original_test_system = variable_get('mail_system_original', ['default-system' => 'DefaultMailSystem']);
    variable_del('mail_system_original');
    // Restore the original system to after the scenario.
    variable_set('mail_system', $original_test_system);
    // Flush the email buffer, allowing us to reuse this step definition
    // to clear existing mail.
    $this->emailClearTestEmailSystemQueue(TRUE);
  }

  /**
   * Clear the test email system queue.
   *
   * @When I clear the test email system queue
   */
  public function emailClearTestEmailSystemQueue($force = FALSE) {
    if (!$force && !variable_get('mail_system_original')) {
      throw new \RuntimeException('Clearing testing email system queue can be done only when email testing system is activated. Add @email tag or "When I enable the test email system" step definition to the scenario.');
    }

    variable_set('drupal_test_email_collector', []);
  }

  /**
   * Assert that at least one email sent to an address.
   *
   * @Then an email is sent to :address
   */
  public function emailAssertIsSentTo($address) {
    foreach ($this->emailGetCollectedEmails() as $email) {
      if ($email['to'] == $address) {
        return;
      }
    }

    throw new \Exception(sprintf('Unable to find email sent to "%s" retrieved from test email collector.', $address));
  }

  /**
   * Assert that no emails were sent.
   *
   * @Then no emails were sent
   */
  public function emailAssertNoneWereSent() {
    $count = count($this->emailGetCollectedEmails());
    if ($count > 0) {
      throw new \Exception(sprintf('Expected no emails to be sent, but sent "%s" emails', $count));
    }
  }

  /**
   * Assert that an email to a user was sent or not with content.
   *
   * @code
   * Then an email to "client1" user is "sent" with "body" content:
   * """
   * Test email content
   * """
   * @endcode
   *
   * @Then /^an email to "(?P<name>[^"]*)" user is "(?P<action>[^"]*)" with "(?P<field>[^"]*)" content:$/
   */
  public function emailAssertToUserIsActionWithContent($name, $action, $field, PyStringNode $string) {
    $user = $name == 'current' && !empty($this->user) ? $this->user : user_load_by_name($name);
    if (!$user) {
      throw new \RuntimeException(sprintf('Unable to find a user "%s"', $name));
    }

    if ($action == 'sent') {
      $this->emailAssertFieldContains('to', new PyStringNode([$user->mail], 0), TRUE);
      $this->emailAssertFieldContains($field, $string);
    }
    elseif ($action == 'not sent') {
      $this->emailAssertFieldNotContains($field, $string);
    }
    else {
      throw new \RuntimeException(sprintf('Provided action "%s" is not from a list of allowed actions', $action));
    }
  }

  /**
   * Assert that an email field contains a value.
   *
   * @code
   * Then an email "body" contains:
   * """
   * Test email content
   * """
   * @endcode
   *
   * @Then an email :field contains:
   */
  public function emailAssertFieldContains($field, PyStringNode $string, $exact = FALSE) {
    if (!in_array($field, ['subject', 'body', 'to', 'from'])) {
      throw new \RuntimeException(sprintf('Invalid email field %s was specified for assertion', $field));
    }

    $string = strval($string);
    $string = $exact ? $string : trim(preg_replace('/\s+/', ' ', $string));
    foreach ($this->emailGetCollectedEmails() as $email) {
      $field_string = $exact ? $email[$field] : trim(preg_replace('/\s+/', ' ', $email[$field]));;
      if (strpos($field_string, $string) !== FALSE) {
        return $email;
      }
    }

    throw new \Exception(sprintf('Unable to find email with%s text "%s" in field "%s" retrieved from test email collector.', ($exact ? ' exact' : ''), $string, $field));
  }

  /**
   * Assert that an email field contains an exact value.
   *
   * @code
   * Then an email "body" contains exact:
   * """
   * Test email content
   * """
   * @endcode
   *
   * @Then an email :field contains exact:
   */
  public function emailAssertFieldContainsExact($field, PyStringNode $string) {
    $this->emailAssertFieldContains($field, $string, TRUE);
  }

  /**
   * Assert that an email field does not contain a value.
   *
   * @code
   * Then an email "body" does not contain:
   * """
   * Test email content
   * """
   * @endcode
   *
   * @Then an email :field does not contain:
   */
  public function emailAssertFieldNotContains($field, PyStringNode $string, $exact = FALSE) {
    if (!in_array($field, ['subject', 'body', 'to', 'from'])) {
      throw new \RuntimeException(sprintf('Invalid email field %s was specified for assertion', $field));
    }

    $string = strval($string);
    $string = $exact ? $string : trim(preg_replace('/\s+/', ' ', $string));
    foreach ($this->emailGetCollectedEmails() as $email) {
      $field_string = $exact ? $email[$field] : trim(preg_replace('/\s+/', ' ', $email[$field]));;
      if (strpos($field_string, $string) !== FALSE) {
        throw new \Exception(sprintf('Found email with%s text "%s" in field "%s" retrieved from test email collector, but should not.', ($exact ? ' exact' : ''), $string, $field));
      }
    }
  }

  /**
   * Assert that an email field does not contain an exact value.
   *
   * @code
   * Then an email "body" does not contain exact:
   * """
   * Test email content
   * """
   * @endcode
   *
   * @Then an email :field does not contain exact:
   */
  public function emailAssertNotContainsExact($field, PyStringNode $string) {
    $this->emailAssertFieldNotContains($field, $string, TRUE);
  }

  /**
   * Follow the specified link number in the email with subject.
   *
   * @code
   * When I follow the link number "2" in the email with the subject:
   * """
   * Test email subject
   * """
   * @endcode
   *
   * @When I follow the link number :number in the email with the subject:
   */
  public function emailFollowLinkNumber($number, PyStringNode $subject) {
    $email = $this->emailAssertFieldContains('subject', $subject);
    $links = $this->emailExtractLinks($email['body']);
    if (empty($links)) {
      throw new \Exception(sprintf('No links were found in the email with subject %s', $subject));
    }
    if (count($links) < $number) {
      throw new \Exception(sprintf('The link with number %s was not found among %s links', $number, count($links)));
    }

    $link = $links[$number - 1];
    print $link;
    $this->getSession()->visit($link);
  }

  /**
   * Assert that a file with a specified file name is attached to an email.
   *
   * @code
   * Then file "myfile.pdg" attached to the email with the subject:
   * """
   * Test email subject
   * """
   * @endcode
   *
   * @Then file :name attached to the email with the subject:
   */
  public function emailAssertContainsAttachmentWithName($name, PyStringNode $subject) {
    $email = $this->emailAssertFieldContains('subject', $subject);

    foreach ($email['params']['attachments'] as $attachment) {
      if ($attachment['filename'] == $name) {
        return;
      }
    }

    throw new \Exception(sprintf('No attachments were found in the email with subject %s', $subject));
  }

  /**
   * Get emails collected during the test.
   */
  protected function emailGetCollectedEmails() {
    $emails = array_map('unserialize', db_query("SELECT name, value FROM {variable} WHERE name = 'drupal_test_email_collector'")->fetchAllKeyed());

    return !empty($emails['drupal_test_email_collector']) ? $emails['drupal_test_email_collector'] : [];
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
  protected function emailExtractLinks($string) {
    // Correct links before extraction.
    $pattern = '(?xi)\b((?:https?://|www\d{0,3}[.]|[a-z0-9.\-]+[.][a-z]{2,4}/)(?:[^\s()<>]+|\(([^\s()<>]+|(\([^\s()<>]+\)))*\))+(?:\(([^\s()<>]+|(\([^\s()<>]+\)))*\)|[^\s`!()\[\]{};:\'".,<>?«»“”‘’]))';
    $string = preg_replace_callback("#$pattern#i", function ($matches) {
      return preg_match('!^https?://!i', $matches[0]) ? $matches[0] : "http://$matches[0]";
    }, $string);

    preg_match_all("#$pattern#i", $string, $matches);

    return !empty($matches[0]) ? $matches[0] : [];
  }

}
