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
   * @Given I disable the test email system
   */
  public function emailDisableTestEmailSystem() {
    $original_test_system = variable_get('mail_system_original', ['default-system' => 'DefaultMailSystem']);
    variable_del('mail_system_original');
    // Restore the original system to after the scenario.
    variable_set('mail_system', $original_test_system);

    $this->emailClearTestEmailSystemQueue(TRUE);
  }

  /**
   * @When I clear the test email system queue
   */
  public function emailClearTestEmailSystemQueue($force = FALSE) {
    if (!$force && !variable_get('mail_system_original')) {
      throw new \RuntimeException('Clearing testing email system queue can be done only when email testing system is activated. Add @email tag or "When I enable the test email system" step definition to the scenario.');
    }

    variable_set('drupal_test_email_collector', []);
  }

  /**
   * @Then an email is sent to :address
   */
  public function emailAssertEmailIsSentTo($address) {
    foreach ($this->emailGetCollectedEmails() as $email) {
      if ($email['to'] == $address) {
        return;
      }
    }

    throw new \Exception(sprintf("Unable to find email sent to '%s' retrieved from test email collector.", $address));
  }

  /**
   * @Then no emails were sent
   */
  public function emailAssertNoEmailsWereSent() {
    if (count($this->emailGetCollectedEmails()) > 0) {
      throw new \Exception('No emails were supposed to be sent');
    }
  }

  /**
   * @Then /^an email to "(?P<name>[^"]*)" user is "(?P<action>[^"]*)" with "(?P<field>[^"]*)" content:$/
   */
  public function emailAssertEmailIsActionToUserWithContent($name, $action, $field, PyStringNode $string) {
    $user = $name == 'current' && !empty($this->user) ? $this->user : user_load_by_name($name);
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
   * @Then an email :field contains:
   */
  public function emailAssertEmailContains($field, PyStringNode $string, $exact = FALSE) {
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

    throw new \Exception(sprintf("Unable to find email with%s text '%s' in field '%s' retrieved from test email collector.", ($exact ? ' exact' : ''), $string, $field));
  }

  /**
   * @Then an email :field contains exact:
   */
  public function emailAssertEmailContainsExact($field, PyStringNode $string) {
    $this->emailAssertEmailContains($field, $string, TRUE);
  }

  /**
   * @Then an email :field does not contain
   */
  public function emailAssertEmailNotContains($field, PyStringNode $string, $exact = FALSE) {
    if (!in_array($field, ['subject', 'body', 'to', 'from'])) {
      throw new \RuntimeException(sprintf('Invalid email field %s was specified for assertion', $field));
    }

    $string = strval($string);
    $string = $exact ? $string : trim(preg_replace('/\s+/', ' ', $string));
    foreach ($this->emailGetCollectedEmails() as $email) {
      $field_string = $exact ? $email[$field] : trim(preg_replace('/\s+/', ' ', $email[$field]));;
      if (strpos($field_string, $string) !== FALSE) {
        throw new \Exception(sprintf("Found email with%s text '%s' in field '%s' retrieved from test email collector, but should not.", ($exact ? ' exact' : ''), $string, $field));
      }
    }
  }

  /**
   * @Then an email :field does not contain exact
   */
  public function emailAssertEmailNotContainsExact($field, PyStringNode $string) {
    $this->emailAssertEmailNotContains($field, $string, TRUE);
  }

  /**
   * @When I follow the link number :number in the email with the subject
   */
  public function emailFollowLinkNumber($number, PyStringNode $subject) {
    $email = $this->emailAssertEmailContains('subject', $subject);
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
   * @When I send test email to :email with:
   */
  public function emailSendTestEmail($email, PyStringNode $string) {
    $fields = $string->getStrings();
    if (!isset($fields[0]) || empty($fields[0])) {
      throw new \Exception(sprintf('Subject is empty'));
    }
    if (!isset($fields[1]) || empty($fields[1])) {
      throw new \Exception(sprintf('Body is empty'));
    }
    drupal_mail('behat_test_email', 'test_email', $email, language_default(), [
      'subject' => $fields[0],
      'body' => $fields[1],
    ], FALSE);
  }

  /**
   * Get emails collected during the test.
   */
  protected function emailGetCollectedEmails() {
    $emailsData = array_map('unserialize', db_query("SELECT name, value FROM {variable} WHERE name = 'drupal_test_email_collector'")->fetchAllKeyed());
    $prepareFields = ['body', 'subject'];
    $emails = [];
    foreach ($prepareFields as $prepareField) {
      if (!empty($emailsData['drupal_test_email_collector'])) {
        foreach ($emails =& $emailsData['drupal_test_email_collector'] as $delta => $email) {
          if (isset($email['params'][$prepareField]) && empty($email[$prepareField])) {
            $emails[$delta][$prepareField] = $email['params'][$prepareField];
          }
        }
      }
    }

    return $emails;
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

/**
 * Implements hook_mail().
 */
function dhhs_email_mail($key, &$message, $params) {
  switch ($key) {
    case 'test_email':
      $message['subject'] = $params['subject'];
      $message['body'][] = $params['body'];
      break;
  }
}
