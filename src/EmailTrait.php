<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\PyStringNode;
use Drupal\Core\Database\Database;
use Drupal\Core\Database\StatementInterface;
use Drupal\user\UserInterface;

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
   * @var array<int, string>
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
  * Enable the test email system.
  *
  * @When I enable the test email system
  */
  public function emailEnableTestEmailSystemStep(): void {
    $this->emailEnableTestEmailSystem();
  }

  /**
   * Disable the test email system.
   *
   * @When I disable the test email system
   */
  public function emailDisableTestEmailSystemStep(): void {
    $this->emailDisableTestEmailSystem();
  }

  /**
   * Assert that an email should be sent to an address.
   *
   * @Then an email should be sent to the :address
   */
  public function emailAssertEmailShouldBeSentTo(string $address): void {
    foreach (self::emailGetCollectedEmails() as $record) {
      $email_to = explode(',', (string) $record['to']);

      if (in_array($address, $email_to)) {
        return;
      }
    }

    throw new \Exception(sprintf('Unable to find email that should be sent to "%s" retrieved from test record collector.', $address));
  }

  /**
   * Assert that no email messages should be sent.
   *
   * @Then no emails should be sent
   */
  public function emailAssertNoEmailsShouldBeSent(): void {
    if (count(self::emailGetCollectedEmails()) > 0) {
      throw new \Exception('No emails should have been sent');
    }
  }

  /**
   * Assert that no email messages should be sent to a specified address.
   *
   * @Then no emails should be sent to the :address
   */
  public function emailAssertNoEmailsShouldBeSentToAddress(string $address): void {
    foreach ($this->emailGetCollectedEmails() as $record) {
      $email_to = explode(',', (string) $record['to']);
      if (in_array($address, $email_to)) {
        throw new \Exception(sprintf('An email was sent to "%s" retrieved from test email collector, but it should not have been.', $address));
      }

      if (!empty($record['headers']['Cc'])) {
        $email_cc = explode(',', (string) $record['headers']['Cc']);
        if (in_array($address, $email_cc)) {
          throw new \Exception(sprintf('An email was cc\'ed to "%s" retrieved from test email collector, but it should not have been.', $address));
        }
      }

      if (!empty($record['headers']['Bcc'])) {
        $email_bcc = explode(',', (string) $record['headers']['Bcc']);
        if (in_array($address, $email_bcc)) {
          throw new \Exception(sprintf('An email was bcc\'ed to "%s" retrieved from test email collector, but it should not have been.', $address));
        }
      }
    }
  }

  /**
   * Assert that the email message header should contain specified content.
   *
   * @Then the email header :header should contain:
   */
  public function emailAssertEmailHeaderShouldContain(string $header, PyStringNode $string, bool $exact = FALSE): void {
    $string_value = (string) $string;
    $string_value = $exact ? $string_value : trim((string) preg_replace('/\s+/', ' ', $string_value));

    foreach ($this->emailGetCollectedEmails() as $record) {
      $header_value = $record['headers'][$header] ?? '';
      $header_value = $exact ? $header_value : trim((string) preg_replace('/\s+/', ' ', (string) $header_value));

      if (str_contains((string) $header_value, $string_value)) {
        return;
      }
    }

    throw new \Exception(sprintf('Unable to find an email where the header "%s" should contain%s text "%s" retrieved from test email collector.', $header, ($exact ? ' exact' : ''), $string));
  }

  /**
   * Assert that the email message header should be the exact specified content.
   *
   * @Then the email header :header should be:
   */
  public function emailAssertEmailHeaderShouldBe(string $header, PyStringNode $string): void {
    $this->emailAssertEmailHeaderShouldContain($header, $string, TRUE);
  }

  /**
   * Assert that an email should be sent to an address with the exact content in the body.
   *
   * @Then an email should be sent to the address :address with the content:
   */
  public function emailAssertEmailShouldBeSentToAddressWithContent(string $address, PyStringNode $string): void {
    // Assert that an email was sent to the specified address.
    $this->emailAssertEmailShouldBeSentTo($address);
    // Assert that the email body matches the specified content exactly.
    $this->emailAssertEmailFieldShouldBe('body', $string);
  }

  /**
   * Assert that an email should be sent to an address with the body containing specific content.
   *
   * @Then an email should be sent to the address :address with the content containing:
   */
  public function emailAssertEmailShouldBeSentToAddressWithContentContaining(string $address, PyStringNode $string): void {
    // Assert that an email was sent to the specified address.
    $this->emailAssertEmailShouldBeSentTo($address);
    // Assert that the email body contains the specified content.
    $this->emailAssertEmailFieldShouldContain('body', $string);
  }

  /**
   * Assert that an email should be sent to an address with the body not containing specific content.
   *
   * @Then an email should be sent to the address :address with the content not containing:
   */
  public function emailAssertEmailShouldBeSentToAddressWithContentNotContaining(string $address, PyStringNode $string): void {
    // Assert that an email was sent to the specified address.
    $this->emailAssertEmailShouldBeSentTo($address);
    // Assert that the email body does not contain the specified content.
    $this->emailAssertEmailFieldShouldNotContain('body', $string);
  }

 /**
  * Assert that an email should not be sent to an address with the exact content in the body.
  *
  * @Then an email should not be sent to the address :address with the content:
  */
  public function emailAssertEmailShouldNotBeSentToAddressWithContent(string $address, PyStringNode $string): void {
     // Assert that no email was sent to the specified address.
     $this->emailAssertNoEmailsShouldBeSentToAddress($address);
     // Assert that no email contains the specified content in the body.
    $this->emailAssertEmailFieldShouldNotBe('body', $string);
  }

 /**
  * Assert that an email should not be sent to an address with the body containing specific content.
  *
  * @Then an email should not be sent to the address :address with the content containing:
  */
  public function emailAssertEmailShouldNotBeSentToAddressWithContentContaining(string $address, PyStringNode $string): void {
    // Assert that no email was sent to the specified address.
    $this->emailAssertNoEmailsShouldBeSentToAddress($address);
    // Assert that no email body contains the specified content as a substring.
    $this->emailAssertEmailFieldShouldNotContain('body', $string);
  }

  /**
   * Assert that the email field should contain a value.
   *
   * @Then the email field :field should contain:
   */
  public function emailAssertEmailFieldShouldContain(string $field, PyStringNode $string, bool $exact = FALSE): void {
    $email = $this->emailFind($field, $string, $exact);

    if (!$email) {
      throw new \Exception(sprintf('Unable to find an email where the field "%s" should contain%s text "%s" retrieved from test email collector.', $field, ($exact ? ' exact' : ''), $string));
    }
  }

  /**
   * Assert that the email field should exactly match a value.
   *
   * @Then the email field :field should be:
   */
  public function emailAssertEmailFieldShouldBe(string $field, PyStringNode $string): void {
    $this->emailAssertEmailFieldShouldContain($field, $string, TRUE);
  }

  /**
   * Assert that the email field should not contain a value.
   *
   * @Then the email field :field should not contain:
   */
  public function emailAssertEmailFieldShouldNotContain(string $field, PyStringNode $string, bool $exact = FALSE): void {
    if (!in_array($field, ['subject', 'body', 'to', 'from'])) {
      throw new \RuntimeException(sprintf('Invalid record field %s was specified for assertion', $field));
    }

    $string = strval($string);
    $string = $exact ? $string : trim((string) preg_replace('/\s+/', ' ', $string));

    foreach (self::emailGetCollectedEmails() as $record) {
      $field_string = $exact ? $record[$field] : trim((string) preg_replace('/\s+/', ' ', (string) $record[$field]));

      if (str_contains((string) $field_string, $string)) {
        throw new \Exception(sprintf('Found an email where the field "%s" contains%s text "%s" retrieved from test email collector, but it should not.', $field, ($exact ? ' exact' : ''), $string));
      }
    }
  }

  /**
   * Assert that the email field should not exactly match a value.
   *
   * @Then the email field :field should not be:
   */
  public function emailAssertEmailFieldShouldNotBe(string $field, PyStringNode $string): void {
    $this->emailAssertEmailFieldShouldNotContain($field, $string, TRUE);
  }

  /**
   * Follow a specific link number in an email with the given subject.
   *
   * @When I follow link number :link_number in the email with the subject :subject
   */
  public function emailFollowLinkNumber(string $link_number, string $subject): void {
    $link_number = intval($link_number);

    $email = $this->emailFind('subject', new PyStringNode([$subject], 0));

    if (!$email) {
      throw new \Exception(sprintf('Unable to find email with subject "%s" retrieved from test email collector.', $subject));
    }

    if (isset($email['params']['body']) && is_string($email['params']['body'])) {
      $body = $email['params']['body'];
    }
    elseif (is_string($email['body'])) {
      $body = $email['body'];
    }
    else {
      throw new \Exception('No body found in email');
    }

    $links = self::emailExtractLinks($body);

    if (empty($links)) {
      throw new \Exception(sprintf('No links were found in the email with subject "%s"', $subject));
    }

    if (count($links) < $link_number) {
      throw new \Exception(sprintf('The link with number %s was not found among %s links', $link_number, count($links)));
    }

    $link = $links[$link_number - 1];
    print $link;
    self::getSession()->visit($link);
  }

  /**
   * Print the list of sent emails for debugging.
   *
   * @When I print the list of sent emails
   */
  public function emailPrintSentEmails(): void {
    $emails = $this->emailGetCollectedEmails();

    if (empty($emails)) {
      print "No emails were sent.\n";
      return;
    }

    print "List of sent emails:\n";
    print "----------------------------------------\n";
    foreach ($emails as $index => $email) {
      print sprintf("Email #%d\n", $index + 1);
      print sprintf("To: %s\n", $email['to'] ?? '<EMPTY>');
      print sprintf("Subject: %s\n", $email['subject'] ?? '<EMPTY>');
      print sprintf("Body: %s\n", $email['body'] ?? '<EMPTY>');
      print "Headers:\n";
      if (!empty($email['headers'])) {
        foreach ($email['headers'] as $header => $value) {
          print sprintf("  %s: %s\n", $header, $value);
        }
      } else {
        print "  <NONE>\n";
      }
      print "----------------------------------------\n";
    }
  }

  /**
   * Follow a specific link number in an email whose subject contains the given substring.
   *
   * @When I follow link number :link_number in the email with the subject containing :subject
   */
  public function emailFollowLinkNumberWithSubjectContaining(string $link_number, string $subject): void {
    $link_number = intval($link_number);

    // Find an email where the subject contains the specified substring.
    $email = null;
    foreach (self::emailGetCollectedEmails() as $record) {
      if (stripos((string) $record['subject'], $subject) !== false) {
        $email = $record;
        break;
      }
    }

    if (!$email) {
      throw new \Exception(sprintf('Unable to find email with subject containing "%s" retrieved from test email collector.', $subject));
    }

    // Extract the body from the email.
    if (isset($email['params']['body']) && is_string($email['params']['body'])) {
      $body = $email['params']['body'];
    } elseif (is_string($email['body'])) {
      $body = $email['body'];
    } else {
      throw new \Exception('No body found in email');
    }

    $links = self::emailExtractLinks($body);

    if (empty($links)) {
      throw new \Exception(sprintf('No links were found in the email with subject containing "%s"', $subject));
    }

    if (count($links) < $link_number) {
      throw new \Exception(sprintf('The link with number %s was not found among %s links', $link_number, count($links)));
    }

    $link = $links[$link_number - 1];
    print $link;
    self::getSession()->visit($link);
  }

  /**
   * Assert that a file is attached to an email message with specified subject.
   *
   * @Then the file :file_name should be attached to the email with the subject :subject
   */
  public function emailAssertEmailContainsAttachmentWithName(string $name, PyStringNode $subject): void {
    $email = $this->emailFind('subject', $subject);

    if (!$email) {
      throw new \Exception(sprintf('Unable to find email with subject "%s" retrieved from test email collector.', $subject));
    }

    if (!empty($email['params']['attachments'])) {
      foreach ($email['params']['attachments'] as $attachment) {
        if ($attachment['filename'] == $name) {
          return;
        }
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
      // Restore the original system to after the scenario.
      self::emailSetMailSystemDefault($type, $original_test_system);
    }

    self::emailDeleteMailSystemOriginal();
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
   *
   * @return array<string,array<string,mixed>>
   *   Array of collected emails.
   */
  protected function emailGetCollectedEmails(): array {
    // Directly read data from the database to avoid cache invalidation that
    // may corrupt the system under test.
    $query = Database::getConnection()->query("SELECT name, value FROM {key_value} WHERE name = 'system.test_mail_collector'");

    if ($query instanceof StatementInterface) {
      $emails = array_map(unserialize(...), $query->fetchAllKeyed());
    }

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
   * Find an email message field containing a value.
   *
   * @param string $field
   *   Field to search in.
   * @param \Behat\Gherkin\Node\PyStringNode $string
   *   String to search for.
   * @param bool $exact
   *   Whether to search for an exact match.
   *
   * @return array<string,string|array<string,mixed>>|null
   *   Email record or NULL if not found.
   */
  protected function emailFind(string $field, PyStringNode $string, bool $exact = FALSE): ?array {
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

    return NULL;
  }

  /**
   * Extract all links from provided string.
   *
   * @param string $string
   *   String to extract links from.
   *
   * @return array<int, string>
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
   *
   * @param array<int, string> $tags
   *   Array of tags.
   *
   * @return array<int, string>
   *   Array of email types.
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
