<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\PyStringNode;
use Drupal\Core\Database\Database;

/**
 * Trait EmailTrait.
 *
 * Email-related steps.
 *
 * Behat trait for email interactions.
 */
trait EmailTrait {

  /**
   * List of email service types.
   *
   * @var array
   */
  protected $emailTypes = [];

  /**
   * Enable email debug.
   *
   * @var bool
   */
  protected $emailDebug = FALSE;

  /**
   * Enable email tracking.
   *
   * @BeforeScenario
   */
  public function emailBeforeScenarioEnableTestEmailSystem(BeforeScenarioScope $scope): void {
    // Allow to skip this by adding a tag.
    if ($scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__)) {
      return;
    }

    if (!$scope->getScenario()->hasTag('email')) {
      return;
    }

    if ($scope->getScenario()->hasTag('debug')) {
      $this->emailDebug = TRUE;
    }

    $this->emailTypes = self::emailExtractTypes($scope->getScenario()->getTags());

    if (empty($this->emailTypes)) {
      $this->emailTypes[] = 'default';
      $module_handler = \Drupal::service('module_handler');
      if ($module_handler->moduleExists('webform')) {
        $this->emailTypes[] = 'webform';
      }
    }

    self::emailEnableTestEmailSystem();
  }

  /**
   * Disable email tracking.
   *
   * @AfterScenario
   */
  public function emailAfterScenarioDisableTestEmailSystem(AfterScenarioScope $scope): void {
    // Allow to skip this by adding a tag.
    if ($scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__)) {
      return;
    }

    if ($scope->getScenario()->hasTag('email')) {
      self::emailDisableTestEmailSystem();
    }
  }

  /**
   * Clear test email system queue.
   *
   * @When I clear the test email system queue
   */
  public function emailClearTestEmailSystemQueue(bool $force = FALSE): void {
    if (!$force && !self::emailGetMailSystemOriginal()) {
      throw new \RuntimeException('Clearing testing email system queue can be done only when email testing system is activated. Add @email tag or "When I enable the test email system" step definition to the scenario.');
    }

    \Drupal::state()->set('system.test_mail_collector', []);
  }

  /**
   * Assert that an email was sent to an address.
   *
   * @Then an email is sent to :address
   */
  public function emailAssertEmailIsSentTo(string $address): void {
    foreach (self::emailGetCollectedEmails() as $record) {
      $email_to = explode(',', (string) $record['to']);

      if (in_array($address, $email_to)) {
        return;
      }
    }

    throw new \Exception(sprintf('Unable to find email sent to "%s" retrieved from test record collector.', $address));
  }

  /**
   * Assert that no email messages were sent.
   *
   * @Then no emails were sent
   */
  public function emailAssertNoEmailsWereSent(): void {
    if (count(self::emailGetCollectedEmails()) > 0) {
      throw new \Exception('No emails were supposed to be sent');
    }
  }

  /**
   * Assert that no email messages were sent to a specified address.
   *
   * @Then no emails were sent to :address
   */
  public function emailAssertNoEmailsWereSentToAddress(string $address): void {
    foreach ($this->emailGetCollectedEmails() as $record) {
      $email_to = explode(',', (string) $record['to']);
      if (in_array($address, $email_to)) {
        throw new \Exception(sprintf('An email sent to "%s" retrieved from test email collector.', $address));
      }

      if (!empty($record['headers']['Cc'])) {
        $email_cc = explode(',', (string) $record['headers']['Cc']);
        if (in_array($address, $email_cc)) {
          throw new \Exception(sprintf('An email cc\'ed to "%s" retrieved from test email collector.', $address));
        }
      }

      if (!empty($record['headers']['Bcc'])) {
        $email_bcc = explode(',', (string) $record['headers']['Bcc']);
        if (in_array($address, $email_bcc)) {
          throw new \Exception(sprintf('An email bcc\'ed to "%s" retrieved from test email collector.', $address));
        }
      }
    }
  }

  /**
   * Assert that an email message header contains specified content.
   *
   * @Then an email header :header contains:
   */
  public function emailAssertEmailHeadersContains(string $header, PyStringNode $string, bool $exact = FALSE): array {
    $string_value = (string) $string;
    $string_value = $exact ? $string_value : trim((string) preg_replace('/\s+/', ' ', $string_value));

    foreach ($this->emailGetCollectedEmails() as $record) {
      $header_value = $record['headers'][$header] ?? '';
      $header_value = $exact ? $header_value : trim((string) preg_replace('/\s+/', ' ', (string) $header_value));

      if (str_contains((string) $header_value, $string_value)) {
        return $record;
      }
    }

    throw new \Exception(sprintf('Unable to find email with%s text "%s" in the header "%s" retrieved from test email collector.', ($exact ? ' exact' : ''), $string, $header));
  }

  /**
   * Assert that an email message header contains exact specified content.
   *
   * @Then an email header :header contains exact:
   */
  public function emailAssertEmailHeadersContainsExact(string $header, PyStringNode $string): void {
    $this->emailAssertEmailHeadersContains($header, $string, TRUE);
  }

  /**
   * Assert that an email message was sent or not sent to a user with content.
   *
   * @Then /^an email to "(?P<name>[^"]*)" user is "(?P<action>[^"]*)" with "(?P<field>[^"]*)" content:$/
   */
  public function emailAssertEmailToUserIsActionWithContent(string $name, string $action, string $field, PyStringNode $string): void {
    $user = $name === 'current' && !empty($this->getUserManager()->getCurrentUser()) ? $this->getUserManager()->getCurrentUser() : user_load_by_name($name);
    if (!$user) {
      throw new \Exception(sprintf('Unable to find a user "%s"', $name));
    }

    if ($action === 'sent') {
      $this->emailAssertEmailContains('to', new PyStringNode([$user->mail], 0), TRUE);
      $this->emailAssertEmailContains($field, $string);
    }
    elseif ($action === 'not sent') {
      $this->emailAssertEmailNotContains($field, $string);
    }
    else {
      throw new \RuntimeException(sprintf('Provided action "%s" is not from a list of allowed actions', $action));
    }
  }

  /**
   * Assert that an email message field contains a value.
   *
   * @Then an email :field contains
   * @Then an email :field contains:
   */
  public function emailAssertEmailContains(string $field, PyStringNode $string, bool $exact = FALSE): array {
    if (!in_array($field, ['subject', 'body', 'to', 'from'])) {
      throw new \RuntimeException(sprintf('Invalid email field %s was specified for assertion', $field));
    }

    $string = strval($string);
    $string = $exact ? $string : trim((string) preg_replace('/\s+/', ' ', $string));

    foreach (self::emailGetCollectedEmails() as $record) {
      $field_string = $record[$field] ?? '';
      $field_string = $exact ? $field_string : trim((string) preg_replace('/\s+/', ' ', (string) $field_string));

      if (str_contains((string) $field_string, $string)) {
        return $record;
      }
    }

    throw new \Exception(sprintf('Unable to find email with%s text "%s" in field "%s" retrieved from test email collector.', ($exact ? ' exact' : ''), $string, $field));
  }

  /**
   * Assert that an email message field contains an exact value.
   *
   * @Then an email :field contains exact
   * @Then an email :field contains exact:
   */
  public function emailAssertEmailContainsExact(string $field, PyStringNode $string): void {
    $this->emailAssertEmailContains($field, $string, TRUE);
  }

  /**
   * Assert that an email message field does not contain a value.
   *
   * @Then an email :field does not contain
   * @Then an email :field does not contain:
   */
  public function emailAssertEmailNotContains(string $field, PyStringNode $string, bool $exact = FALSE): void {
    if (!in_array($field, ['subject', 'body', 'to', 'from'])) {
      throw new \RuntimeException(sprintf('Invalid record field %s was specified for assertion', $field));
    }

    $string = strval($string);
    $string = $exact ? $string : trim((string) preg_replace('/\s+/', ' ', $string));

    foreach (self::emailGetCollectedEmails() as $record) {
      $field_string = $exact ? $record[$field] : trim((string) preg_replace('/\s+/', ' ', (string) $record[$field]));

      if (str_contains((string) $field_string, $string)) {
        throw new \Exception(sprintf('Found record with%s text "%s" in field "%s" retrieved from test record collector, but should not.', ($exact ? ' exact' : ''), $string, $field));
      }
    }
  }

  /**
   * Assert that an email message field does not contain an exact value.
   *
   * @Then an email :field does not contain exact
   * @Then an email :field does not contain exact:
   */
  public function emailAssertEmailNotContainsExact(string $field, PyStringNode $string): void {
    $this->emailAssertEmailNotContains($field, $string, TRUE);
  }

  /**
   * Visit a link from the email.
   *
   * @When I follow the link number :number in the email with the subject
   * @When I follow the link number :number in the email with the subject:
   */
  public function emailFollowLinkNumber(string $number, PyStringNode $subject): void {
    $number = intval($number);

    $email = $this->emailAssertEmailContains('subject', $subject);
    $links = self::emailExtractLinks($email['params']['body'] ?? $email['body'] ?? '');

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
   * Assert that a file is attached to an email message with specified subject.
   *
   * @Then file :name attached to the email with the subject
   * @Then file :name attached to the email with the subject:
   */
  public function emailAssertEmailContainsAttachmentWithName(string $name, PyStringNode $subject): void {
    $email = $this->emailAssertEmailContains('subject', $subject);

    foreach ($email['params']['attachments'] as $attachment) {
      if ($attachment['filename'] == $name) {
        return;
      }
    }

    throw new \Exception(sprintf('No attachments were found in the email with subject %s', $subject));
  }

  /**
   * Enable test email system.
   */
  protected function emailEnableTestEmailSystem(): void {
    foreach ($this->emailTypes as $type) {
      // Store the original system to restore after the scenario.
      $original_test_system = self::emailGetMailSystemDefault($type);
      // But store only if previous has not been stored yet.
      if (!self::emailGetMailSystemOriginal($type)) {
        self::emailSetMailSystemOriginal($type, $original_test_system);
      }
      // Set the test system.
      self::emailSetMailSystemDefault($type, 'test_mail_collector');
    }

    // Flush the email buffer, allowing us to reuse this step definition
    // to clear existing mail.
    self::emailClearTestEmailSystemQueue(TRUE);
  }

  /**
   * Disable test email system.
   */
  protected function emailDisableTestEmailSystem(): void {
    foreach ($this->emailTypes as $type) {
      $original_test_system = self::emailGetMailSystemOriginal($type);
      self::emailDeleteMailSystemOriginal();
      // Restore the original system to after the scenario.
      self::emailSetMailSystemDefault($type, $original_test_system);
    }

    self::emailClearTestEmailSystemQueue(TRUE);
  }

  /**
   * Get default mail system value.
   */
  protected static function emailGetMailSystemDefault(string $type = 'default'): mixed {
    return \Drupal::config('system.mail')->get('interface.' . $type);
  }

  /**
   * Set default mail system value.
   */
  protected static function emailSetMailSystemDefault(string $type, mixed $value): void {
    \Drupal::configFactory()->getEditable('system.mail')->set('interface.' . $type, $value)->save();

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
  protected static function emailGetMailSystemOriginal(string $type = 'default'): mixed {
    return \Drupal::config('system.mail_original')->get('interface.' . $type);
  }

  /**
   * Set original mail system value.
   */
  protected static function emailSetMailSystemOriginal(string $type, mixed $value): void {
    \Drupal::configFactory()->getEditable('system.mail_original')->set('interface.' . $type, $value)->save();
  }

  /**
   * Remove original mail system value.
   */
  protected static function emailDeleteMailSystemOriginal(): void {
    \Drupal::configFactory()->getEditable('system.mail_original')->delete();
  }

  /**
   * Get emails collected during the test.
   */
  protected function emailGetCollectedEmails(): array {
    // Directly read data from the database to avoid cache invalidation that
    // may corrupt the system under test.
    $emails = array_map(unserialize(...), Database::getConnection()->query("SELECT name, value FROM {key_value} WHERE name = 'system.test_mail_collector'")->fetchAllKeyed());

    $emails = empty($emails['system.test_mail_collector']) ? [] : $emails['system.test_mail_collector'];

    if ($this->emailDebug) {
      $fields = ['to', 'from', 'subject', 'body'];
      foreach ($emails as $idx => $email) {
        printf("----------------------------------------\n");
        printf("Email number: %s\n", $idx);
        printf("----------------------------------------\n");
        foreach ($fields as $field) {
          printf("Field: %s\n", $field);
          printf("Value: %s\n", $email[$field] ?? '<EMPTY>');
          print PHP_EOL;
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
  protected static function emailExtractLinks(string $string): array {
    // Correct links before extraction.
    $pattern = '(?xi)\b((?:https?://|www\d{0,3}[.]|[a-z0-9.\-]+[.][a-z]{2,4}/)(?:[^\s()<>]+|\(([^\s()<>]+|(\([^\s()<>]+\)))*\))+(?:\(([^\s()<>]+|(\([^\s()<>]+\)))*\)|[^\s`!()\[\]{};:\'".,<>?«»“”‘’]))';
    $string = preg_replace_callback(sprintf('#%s#i', $pattern), function (array $matches): string {
      return preg_match('!^https?://!i', $matches[0]) ? $matches[0] : 'http://' . $matches[0];
    }, $string);

    preg_match_all(sprintf('#%s#i', $pattern), (string) $string, $matches);

    return empty($matches[0]) ? [] : $matches[0];
  }

  /**
   * Extract email types from tags.
   */
  protected static function emailExtractTypes(array $tags): array {
    $types = [];

    foreach ($tags as $tag) {
      if (str_starts_with((string) $tag, 'email')) {
        $parts = explode(':', (string) $tag);
        $types[] = count($parts) > 1 ? implode(':', array_slice($parts, 1)) : 'default';
      }
    }

    return $types;
  }

}
