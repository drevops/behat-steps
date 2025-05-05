<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Drupal;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\PyStringNode;
use Drupal\Core\Database\Database;
use Drupal\Core\Database\StatementInterface;

/**
 * Test Drupal email functionality with content verification.
 *
 * - Capture and examine outgoing emails with header and body validation.
 * - Follow links and test attachments within email content.
 * - Configure mail handler systems for proper test isolation.
 *
 * Skip processing with tags: `@behat-steps-skip:emailBeforeScenario` or
 * `@behat-steps-skip:emailAfterScenario`
 *
 * Special tags:
 * - `@email` - enable email tracking using a default handler
 * - `@email:{type}` - enable email tracking using a `{type}` handler
 * - `@debug` (enable detailed logs)
 */
trait EmailTrait {

  /**
   * List of email handler types.
   *
   * @var array<int, string>
   */
  protected array $emailHandlerTypes = [];

  /**
   * Enable email debug.
   */
  protected bool $emailDebug = FALSE;

  /**
   * Enable email tracking.
   *
   * @BeforeScenario
   */
  public function emailBeforeScenario(BeforeScenarioScope $scope): void {
    if ($scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__)) {
      return;
    }

    if (!$scope->getScenario()->hasTag('email')) {
      return;
    }

    if ($scope->getScenario()->hasTag('debug')) {
      $this->emailDebug = TRUE;
    }

    foreach ($scope->getScenario()->getTags() as $tag) {
      if (str_starts_with($tag, 'email:')) {
        $parts = explode(':', $tag);
        $this->emailHandlerTypes[] = count($parts) > 1 ? implode(':', array_slice($parts, 1)) : 'default';
      }
    }

    if (empty($this->emailHandlerTypes)) {
      $this->emailHandlerTypes[] = 'default';
    }

    $this->emailHandlerTypes = array_unique($this->emailHandlerTypes);

    self::emailEnableTestSystem();
  }

  /**
   * Disable email tracking.
   *
   * @AfterScenario
   */
  public function emailAfterScenario(AfterScenarioScope $scope): void {
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
   * @code
   * When I clear the test email system queue
   * @endcode
   *
   * @When I clear the test email system queue
   */
  public function emailClearTestQueue(bool $force = FALSE): void {
    if (!$force && !self::emailGetMailSystemOriginal()) {
      throw new \RuntimeException('Clearing testing email system queue can be done only when email testing system is activated. Add @email tag or "When I enable the test email system" step definition to the scenario.');
    }

    \Drupal::state()->set('system.test_mail_collector', []);
  }

  /**
   * Assert that an email should be sent to an address.
   *
   * @code
   * Then an email should be sent to the "user@example.com"
   * @endcode
   *
   * @Then an email should be sent to the :address
   */
  public function emailAssertMessageSentTo(string $address): void {
    foreach ($this->emailGetCollectedMessages() as $message) {
      $to = array_map('trim', explode(',', (string) $message['to']));

      if (in_array($address, $to)) {
        return;
      }
    }

    throw new \Exception(sprintf('Unable to find email that should be sent to "%s" retrieved from test message collector.', $address));
  }

  /**
   * Assert that no email messages should be sent.
   *
   * @code
   * Then no emails should have been sent
   * @endcode
   *
   * @Then no emails should have been sent
   */
  public function emailAssertNoMessagesSent(): void {
    $messages = $this->emailGetCollectedMessages();
    if (count($messages) > 0) {
      throw new \Exception('No emails should have been sent, but some were found: ' . PHP_EOL . print_r($messages, TRUE));
    }
  }

  /**
   * Assert that no email messages should be sent to a specified address.
   *
   * @code
   * Then no emails should have been sent to the "user@example.com"
   * @endcode
   *
   * @Then no emails should have been sent to the :address
   */
  public function emailAssertNoMessagesSentToAddress(string $address): void {
    foreach ($this->emailGetCollectedMessages() as $message) {
      $to = array_map('trim', explode(',', (string) $message['to']));
      if (in_array($address, $to)) {
        throw new \Exception(sprintf('An email was sent to "%s" retrieved from test email collector, but it should not have been.', $address));
      }

      if (!empty($message['headers']['Cc'] ?? $message['headers']['cc'] ?? NULL)) {
        $cc = array_map('trim', explode(',', (string) ($message['headers']['Cc'] ?? $message['headers']['cc'])));
        if (in_array($address, $cc)) {
          throw new \Exception(sprintf('An email was cc\'ed to "%s" retrieved from test email collector, but it should not have been.', $address));
        }
      }

      if (!empty($message['headers']['Bcc'] ?? $message['headers']['bcc'] ?? NULL)) {
        $bcc = array_map('trim', explode(',', (string) ($message['headers']['Bcc'] ?? $message['headers']['bcc'])));
        if (in_array($address, $bcc)) {
          throw new \Exception(sprintf('An email was bcc\'ed to "%s" retrieved from test email collector, but it should not have been.', $address));
        }
      }
    }
  }

  /**
   * Assert that the email message header should contain specified content.
   *
   * @code
   * Then the email header "Subject" should contain:
   * """
   * Account details
   * """
   * @endcode
   *
   * @Then the email header :header should contain:
   */
  public function emailAssertMessageHeaderContains(string $header, PyStringNode $string, bool $exact = FALSE): void {
    $string_value = (string) $string;
    $string_value = $exact ? $string_value : trim((string) preg_replace('/\s+/', ' ', $string_value));

    foreach ($this->emailGetCollectedMessages() as $message) {
      $header_value = $message['headers'][$header] ?? '';
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
   * @code
   * Then the email header "Subject" should exactly be:
   * """
   * Your Account Details
   * """
   * @endcode
   *
   * @Then the email header :header should exactly be:
   */
  public function emailAssertMessageHeader(string $header, PyStringNode $string): void {
    $this->emailAssertMessageHeaderContains($header, $string, TRUE);
  }

  /**
   * Assert that an email should be sent to an address with the exact content in the body.
   *
   * @code
   * Then an email should be sent to the address "user@example.com" with the content:
   * """
   * Welcome to our site!
   * Click the link below to verify your account.
   * """
   * @endcode
   *
   * @Then an email should be sent to the address :address with the content:
   */
  public function emailAssertMessageSentToAddressWithContent(string $address, PyStringNode $string): void {
    // Assert that an email was sent to the specified address.
    $this->emailAssertMessageSentTo($address);
    // Assert that the email body matches the specified content exactly.
    $this->emailAssertMessageField('body', $string);
  }

  /**
   * Assert that an email should be sent to an address with the body containing specific content.
   *
   * @code
   * Then an email should be sent to the address "user@example.com" with the content containing:
   * """
   * verification link
   * """
   * @endcode
   *
   * @Then an email should be sent to the address :address with the content containing:
   */
  public function emailAssertMessageSentToAddressWithContentContaining(string $address, PyStringNode $string): void {
    // Assert that an email was sent to the specified address.
    $this->emailAssertMessageSentTo($address);
    // Assert that the email body contains the specified content.
    $this->emailAssertMessageFieldContains('body', $string);
  }

  /**
   * Assert that an email should be sent to an address with the body not containing specific content.
   *
   * @code
   * Then an email should be sent to the address "user@example.com" with the content not containing:
   * """
   * password
   * """
   * @endcode
   *
   * @Then an email should be sent to the address :address with the content not containing:
   */
  public function emailAssertMessageSentToAddressWithContentNotContaining(string $address, PyStringNode $string): void {
    // Assert that an email was sent to the specified address.
    $this->emailAssertMessageSentTo($address);
    // Assert that the email body does not contain the specified content.
    $this->emailAssertMessageFieldNotContains('body', $string);
  }

  /**
   * Assert that an email should not be sent to an address with the exact content in the body.
   *
   * @code
   * Then an email should not be sent to the address "wrong@example.com" with the content:
   * """
   * Welcome to our site!
   * """
   * @endcode
   *
   * @Then an email should not be sent to the address :address with the content:
   */
  public function emailAssertMessageNotSentToAddressWithContent(string $address, PyStringNode $string): void {
    // Assert that no email was sent to the specified address.
    $this->emailAssertNoMessagesSentToAddress($address);
    // Assert that no email contains the specified content in the body.
    $this->emailAssertMessageFieldNotExact('body', $string);
  }

  /**
   * Assert that an email should not be sent to an address with the body containing specific content.
   *
   * @code
   * Then an email should not be sent to the address "wrong@example.com" with the content containing:
   * """
   * verification link
   * """
   * @endcode
   *
   * @Then an email should not be sent to the address :address with the content containing:
   */
  public function emailAssertMessageNotSentToAddressWithContentContaining(string $address, PyStringNode $string): void {
    // Assert that no email was sent to the specified address.
    $this->emailAssertNoMessagesSentToAddress($address);
    // Assert that no email body contains the specified content as a substring.
    $this->emailAssertMessageFieldNotContains('body', $string);
  }

  /**
   * Assert that the email field should contain a value.
   *
   * @code
   * Then the email field "body" should contain:
   * """
   * Please verify your account
   * """
   * @endcode
   *
   * @Then the email field :field should contain:
   */
  public function emailAssertMessageFieldContains(string $field, PyStringNode $string, bool $exact = FALSE): void {
    $message = $this->emailFindMessage($field, $string, $exact);

    if (!$message) {
      throw new \Exception(sprintf('Unable to find an email where the field "%s" should contain%s text "%s" retrieved from test email collector.', $field, ($exact ? ' exact' : ''), $string));
    }
  }

  /**
   * Assert that the email field should exactly match a value.
   *
   * @code
   * Then the email field "subject" should be:
   * """
   * Account Verification
   * """
   * @endcode
   *
   * @Then the email field :field should be:
   */
  public function emailAssertMessageField(string $field, PyStringNode $string): void {
    $this->emailAssertMessageFieldContains($field, $string, TRUE);
  }

  /**
   * Assert that the email field should not contain a value.
   *
   * @code
   * Then the email field "body" should not contain:
   * """
   * password
   * """
   * @endcode
   *
   * @Then the email field :field should not contain:
   */
  public function emailAssertMessageFieldNotContains(string $field, PyStringNode $string, bool $exact = FALSE): void {
    if (!in_array($field, ['subject', 'body', 'to', 'from', 'cc', 'bcc'])) {
      throw new \RuntimeException(sprintf('Invalid message field %s was specified for assertion', $field));
    }

    $string = strval($string);
    $string = $exact ? $string : trim((string) preg_replace('/\s+/', ' ', $string));

    foreach ($this->emailGetCollectedMessages() as $message) {
      $value = $message[$field] ?? '';
      $field_string = $exact ? $value : trim((string) preg_replace('/\s+/', ' ', (string) $value));

      if (str_contains((string) $field_string, $string)) {
        throw new \Exception(sprintf('Found an email where the field "%s" contains%s text "%s" retrieved from test email collector, but it should not.', $field, ($exact ? ' exact' : ''), $string));
      }
    }
  }

  /**
   * Assert that the email field should not exactly match a value.
   *
   * @code
   * Then the email field "subject" should not be:
   * """
   * Password Reset
   * """
   * @endcode
   *
   * @Then the email field :field should not be:
   */
  public function emailAssertMessageFieldNotExact(string $field, PyStringNode $string): void {
    $this->emailAssertMessageFieldNotContains($field, $string, TRUE);
  }

  /**
   * Follow a specific link number in an email with the given subject.
   *
   * @code
   * When I follow link number "1" in the email with the subject "Account Verification"
   * @endcode
   *
   * @When I follow link number :link_number in the email with the subject :subject
   */
  public function emailFollowLinkNumber(string $link_number, string $subject): void {
    $link_number = intval($link_number);

    $message = $this->emailFindMessage('subject', new PyStringNode([$subject], 0));

    if (!$message) {
      throw new \Exception(sprintf('Unable to find email with subject "%s" retrieved from test email collector.', $subject));
    }

    if (isset($message['params']['body']) && is_string($message['params']['body'])) {
      $body = $message['params']['body'];
    }
    elseif (is_string($message['body'])) {
      $body = $message['body'];
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
    $this->getSession()->visit($link);
  }

  /**
   * Follow a specific link number in an email whose subject contains the given substring.
   *
   * @code
   * When I follow link number "1" in the email with the subject containing "Verification"
   * @endcode
   *
   * @When I follow link number :link_number in the email with the subject containing :subject
   */
  public function emailFollowLinkNumberWithSubjectContaining(string $link_number, string $subject): void {
    $link_number = intval($link_number);

    $message = NULL;
    foreach ($this->emailGetCollectedMessages() as $m) {
      if (str_contains(strtolower((string) $m['subject']), strtolower($subject))) {
        $message = $m;
        break;
      }
    }

    if (!$message) {
      throw new \Exception(sprintf('Unable to find email with subject containing "%s" retrieved from test email collector.', $subject));
    }

    // Extract the body from the email.
    if (isset($message['params']['body']) && is_string($message['params']['body'])) {
      $body = $message['params']['body'];
    }
    elseif (is_string($message['body'])) {
      $body = $message['body'];
    }
    else {
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

    $this->getSession()->visit($link);
  }

  /**
   * Assert that a file is attached to an email message with specified subject.
   *
   * @code
   * Then the file "document.pdf" should be attached to the email with the subject "Your document"
   * @endcode
   *
   * @Then the file :file_name should be attached to the email with the subject :subject
   */
  public function emailAssertMessageContainsAttachmentWithName(string $file_name, string $subject): void {
    $message = $this->emailFindMessage('subject', new PyStringNode([$subject], 0));

    if (!$message) {
      throw new \Exception(sprintf('Unable to find email with subject "%s" retrieved from test email collector.', $subject));
    }

    if (!empty($message['params']['attachments'])) {
      foreach ($message['params']['attachments'] as $attachment) {
        if ($attachment['filename'] == $file_name) {
          return;
        }
      }
    }

    throw new \Exception(sprintf('No attachments were found in the email with subject %s', $subject));
  }

  /**
   * Assert that a file is attached to an email message with a subject containing the specified substring.
   *
   * @code
   * Then the file "report.xlsx" should be attached to the email with the subject containing "Monthly Report"
   * @endcode
   *
   * @Then the file :file_name should be attached to the email with the subject containing :subject
   */
  public function emailAssertMessageContainsAttachmentWithSubjectContaining(string $file_name, string $subject): void {
    $message = NULL;
    foreach ($this->emailGetCollectedMessages() as $m) {
      if (str_contains(strtolower((string) $m['subject']), strtolower($subject))) {
        $message = $m;
        break;
      }
    }

    if (!$message) {
      throw new \Exception(sprintf('Unable to find email with subject containing "%s" retrieved from test email collector.', $subject));
    }

    if (!empty($message['params']['attachments'])) {
      foreach ($message['params']['attachments'] as $attachment) {
        if ($attachment['filename'] == $file_name) {
          return;
        }
      }
    }

    throw new \Exception(sprintf('No attachments were found in the email with subject containing "%s"', $subject));
  }

  /**
   * Enable the test email system.
   *
   * @code
   * When I enable the test email system
   * @endcode
   *
   * @When I enable the test email system
   */
  public function emailEnableTestSystem(): void {
    foreach ($this->emailHandlerTypes as $type) {
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
    $this->emailClearTestQueue(TRUE);
  }

  /**
   * Disable test email system.
   *
   * @code
   * When I disable the test email system
   * @endcode
   *
   * @When I disable the test email system
   */
  public function emailDisableTestEmailSystem(): void {
    foreach ($this->emailHandlerTypes as $type) {
      $original_test_system = self::emailGetMailSystemOriginal($type);
      // Restore the original system to after the scenario.
      self::emailSetMailSystemDefault($type, $original_test_system);
    }

    self::emailDeleteMailSystemOriginal();
    $this->emailClearTestQueue(TRUE);
  }

  /**
   * Get the default mail system value.
   */
  protected static function emailGetMailSystemDefault(string $type = 'default'): mixed {
    return \Drupal::config('system.mail')->get('interface.' . $type);
  }

  /**
   * Set the default mail system value.
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
   * Get the original mail system value.
   */
  protected static function emailGetMailSystemOriginal(string $type = 'default'): mixed {
    return \Drupal::config('system.mail_original')->get('interface.' . $type);
  }

  /**
   * Set the original mail system value.
   */
  protected static function emailSetMailSystemOriginal(string $type, mixed $value): void {
    \Drupal::configFactory()->getEditable('system.mail_original')->set('interface.' . $type, $value)->save();
  }

  /**
   * Remove the original mail system value.
   */
  protected static function emailDeleteMailSystemOriginal(): void {
    \Drupal::configFactory()->getEditable('system.mail_original')->delete();
  }

  /**
   * Get email messages collected during the test.
   *
   * @return array<string,array<string,mixed>>
   *   Array of collected emails.
   */
  protected function emailGetCollectedMessages(): array {
    // Directly read data from the database to avoid cache invalidation that
    // may corrupt the system under test.
    $query = Database::getConnection()->query("SELECT name, value FROM {key_value} WHERE name = 'system.test_mail_collector'");

    $messages = [];
    if ($query instanceof StatementInterface) {
      $messages = array_map('unserialize', $query->fetchAllKeyed());
    }
    $messages = $messages['system.test_mail_collector'] ?? [];

    $fields = ['subject', 'body', 'to', 'from', 'cc', 'bcc'];

    // Normalize the keys to lowercase.
    foreach ($messages as $idx => $message) {
      $messages[$idx] = array_change_key_case($message, CASE_LOWER);

      if ($this->emailDebug) {
        printf("----------------------------------------\n");
        printf("Email message number: %s\n", $idx);
        printf("----------------------------------------\n");
        foreach ($fields as $field) {
          printf("Field: %s\n", $field);
          printf("Value: %s\n", $messages[$idx][$field] ?? '<EMPTY>');
          print PHP_EOL;
        }
      }
    }

    return $messages;
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
   *   Email message or NULL if not found.
   */
  protected function emailFindMessage(string $field, PyStringNode $string, bool $exact = FALSE): ?array {
    if (!in_array($field, ['subject', 'body', 'to', 'from', 'cc', 'bcc'])) {
      throw new \RuntimeException(sprintf('Invalid email field %s was specified for assertion', $field));
    }

    $string = (string) $string;
    $string = $exact ? $string : trim((string) preg_replace('/\s+/', ' ', $string));

    foreach ($this->emailGetCollectedMessages() as $message) {
      $field_string = $message[$field] ?? '';
      $field_string = $exact ? $field_string : trim((string) preg_replace('/\s+/', ' ', (string) $field_string));

      if (str_contains((string) $field_string, $string)) {
        return $message;
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

}
