<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Hook\AfterScenario;
use Behat\Hook\BeforeScenario;
use Behat\Mink\Exception\ExpectationException;
use Behat\Step\Then;
use Behat\Step\When;
use DrevOps\BehatSteps\Backend\Capability\CoreCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\ModuleCapabilityInterface;
use DrevOps\BehatSteps\Behat\Config\Option;
use DrevOps\BehatSteps\Behat\Tag;
use DrevOps\BehatSteps\Helper\Web\StringTrait;
use Drupal\Core\Database\Database;
use Drupal\Core\Database\StatementInterface;

/**
 * Test Drupal email functionality with content verification.
 *
 * - Capture and examine outgoing emails with header and body validation.
 * - Follow links and test attachments within email content.
 * - Configure mail handler systems for proper test isolation.
 *
 * Skip processing with tag: `@behat-steps-skip:EmailTrait`.
 *
 * Special tags:
 * - `@email` - enable email tracking using a default handler
 * - `@email:{type}` - enable email tracking using a `{type}` handler
 * - `@debug` (enable detailed logs)
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
 */
trait EmailTrait {

  use StringTrait;

  /**
   * The tag that collects the scenario's email, with an optional handler type.
   */
  protected const EMAIL_TAG = 'email';

  /**
   * The tag that prints each collected message as it is read.
   */
  protected const EMAIL_DEBUG_TAG = 'debug';

  /**
   * List of email handler types.
   *
   * @var array<int, string>
   */
  protected array $emailHandlerTypes = [];

  /**
   * Whether email debug is enabled.
   */
  protected bool $emailDebug = FALSE;

  /**
   * Enable email tracking.
   */
  #[BeforeScenario]
  public function emailBeforeScenario(BeforeScenarioScope $scope): void {
    if ($this->skipTag(__TRAIT__, $scope)) {
      return;
    }

    if (!Tag::has($scope, self::EMAIL_TAG)) {
      return;
    }

    $this->backendFor(CoreCapabilityInterface::class);

    $this->emailDebug = Tag::has($scope, self::EMAIL_DEBUG_TAG);
    $this->emailHandlerTypes = Tag::values($scope, self::EMAIL_TAG);

    $this->emailEnableTestSystem();
  }

  /**
   * Disable email tracking.
   */
  #[AfterScenario]
  public function emailAfterScenario(AfterScenarioScope $scope): void {
    if ($this->skipTag(__TRAIT__, $scope)) {
      return;
    }

    // The step can enable the system without the '@email' tag, so the teardown
    // checks the enabled handler types instead.
    if ($this->emailHandlerTypes === []) {
      return;
    }

    $this->backendFor(CoreCapabilityInterface::class);

    $this->emailDisableTestEmailSystem();
  }

  /**
   * Clear test email system queue.
   *
   * @code
   * When I clear the test email system queue
   * @endcode
   */
  #[When('I clear the test email system queue')]
  public function emailClearTestQueue(bool $force = FALSE): void {
    $this->backendFor(CoreCapabilityInterface::class);

    if (!$force && !static::emailFindMailSystemOriginal()) {
      throw new \RuntimeException('Clearing testing email system queue can be done only when email testing system is activated. Add @email tag or "When I enable the test email system" step definition to the scenario.');
    }

    \Drupal::state()->set('system.test_mail_collector', []);
  }

  /**
   * Follow the link at the 1-based index in an email with the given subject.
   *
   * @code
   * When I follow the link with the index "1" in the email with the subject "Account Verification"
   * @endcode
   */
  #[When('I follow the link with the index :index in the email with the subject :subject')]
  public function emailFollowLinkWithIndex(string $index, string $subject): void {
    $this->emailFollowLinkWithIndexBySubject($index, $subject, FALSE);
  }

  /**
   * Follow the first link whose URL contains a fragment in an email.
   *
   * A one-time login or confirmation link can be followed without knowing its
   * position in the body.
   *
   * @code
   * When I follow the link with a URL containing "user/reset" in the email
   * @endcode
   */
  #[When('I follow the link with a URL containing :partial_url in the email')]
  public function emailFollowLinkContaining(string $partial_url): void {
    foreach ($this->emailGetCollectedMessages() as $message) {
      $body = $message['params']['body'] ?? NULL;

      // A handler that stores a structure in 'params.body' leaves the rendered
      // text in 'body', so 'body' is read in that case.
      if (!is_string($body)) {
        $body = $message['body'] ?? '';
      }

      if (!is_string($body)) {
        continue;
      }

      foreach (static::emailExtractLinks($body) as $link) {
        if (str_contains($link, $partial_url)) {
          $this->getSession()->visit($link);

          return;
        }
      }
    }

    throw new ExpectationException(sprintf('No email contains a link with "%s" in its URL.', $partial_url), $this->getSession()->getDriver());
  }

  /**
   * Follow the link at the 1-based index in an email whose subject contains the given substring.
   *
   * @code
   * When I follow the link with the index "1" in the email with a subject containing "Verification"
   * @endcode
   */
  #[When('I follow the link with the index :index in the email with a subject containing :partial_subject')]
  public function emailFollowLinkWithIndexWithSubjectContaining(string $index, string $partial_subject): void {
    $this->emailFollowLinkWithIndexBySubject($index, $partial_subject, TRUE);
  }

  /**
   * Enable the test email system.
   *
   * Collects with the handler types named by the scenario's `@email:TYPE` tags,
   * or with the `default` handler when none are named. The system is disabled
   * again once the scenario finishes, unless `@behat-steps-skip:EmailTrait`
   * switches the trait's hooks off.
   *
   * @code
   * When I enable the test email system
   * @endcode
   */
  #[When('I enable the test email system')]
  public function emailEnableTestSystem(): void {
    $this->backendFor(CoreCapabilityInterface::class);

    $this->emailHandlerTypes = array_values(array_unique($this->emailHandlerTypes ?: ['default']));

    foreach ($this->emailHandlerTypes as $type) {
      $original_test_system = static::emailFindMailSystemDefault($type);
      if (!static::emailFindMailSystemOriginal($type)) {
        static::emailSetMailSystemOriginal($type, $original_test_system);
      }
      $this->emailSetMailSystemDefault($type, 'test_mail_collector');
    }

    // Clearing on enable lets this step also reset existing mail.
    $this->emailClearTestQueue(TRUE);
  }

  /**
   * Disable test email system.
   *
   * @code
   * When I disable the test email system
   * @endcode
   */
  #[When('I disable the test email system')]
  public function emailDisableTestEmailSystem(): void {
    $this->backendFor(CoreCapabilityInterface::class);

    foreach ($this->emailHandlerTypes as $type) {
      $original_test_system = static::emailFindMailSystemOriginal($type);
      $this->emailSetMailSystemDefault($type, $original_test_system);
    }

    static::emailDeleteMailSystemOriginal();
    $this->emailClearTestQueue(TRUE);
  }

  /**
   * Assert that an email should be sent to an address.
   *
   * @code
   * Then an email should be sent to the address "user@example.com"
   * @endcode
   */
  #[Then('an email should be sent to the address :address')]
  public function emailAssertMessageSentTo(string $address): void {
    foreach ($this->emailGetCollectedMessages() as $message) {
      $to = $this->stringSplitCommaSeparated((string) $message['to']);

      if (in_array($address, $to, TRUE)) {
        return;
      }
    }

    throw new ExpectationException(sprintf('Unable to find email that should be sent to "%s" retrieved from test email collector.', $address), $this->getSession()->getDriver());
  }

  /**
   * Assert the number of emails sent.
   *
   * Counts every collected message since the queue was last cleared.
   *
   * @code
   * Then the number of sent emails should be 2
   * @endcode
   */
  #[Then('the number of sent emails should be :count')]
  public function emailAssertMessageCount(int $count): void {
    $actual = count($this->emailGetCollectedMessages());

    if ($actual !== $count) {
      throw new ExpectationException(sprintf('Expected %d email(s) to have been sent, but %d were found.', $count, $actual), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert the number of emails sent to an address.
   *
   * @code
   * Then the number of emails sent to the address "user@example.com" should be 2
   * @endcode
   */
  #[Then('the number of emails sent to the address :address should be :count')]
  public function emailAssertMessageCountToAddress(int $count, string $address): void {
    $actual = 0;

    foreach ($this->emailGetCollectedMessages() as $message) {
      if (in_array($address, $this->stringSplitCommaSeparated((string) $message['to']), TRUE)) {
        $actual++;
      }
    }

    if ($actual !== $count) {
      throw new ExpectationException(sprintf('Expected %d email(s) to have been sent to "%s", but %d were found.', $count, $address, $actual), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert the number of emails sent with a subject.
   *
   * @code
   * Then the number of emails sent with the subject "Welcome" should be 1
   * @endcode
   */
  #[Then('the number of emails sent with the subject :subject should be :count')]
  public function emailAssertMessageCountWithSubject(int $count, string $subject): void {
    $actual = 0;

    foreach ($this->emailGetCollectedMessages() as $message) {
      if ((string) $message['subject'] === $subject) {
        $actual++;
      }
    }

    if ($actual !== $count) {
      throw new ExpectationException(sprintf('Expected %d email(s) to have been sent with the subject "%s", but %d were found.', $count, $subject, $actual), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that no email messages should be sent.
   *
   * @code
   * Then no emails should have been sent
   * @endcode
   */
  #[Then('no emails should have been sent')]
  public function emailAssertMessagesNotSent(): void {
    $messages = $this->emailGetCollectedMessages();
    if (count($messages) > 0) {
      throw new ExpectationException('No emails should have been sent, but some were found: ' . PHP_EOL . print_r($messages, TRUE), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that no email messages should be sent to a specified address.
   *
   * @code
   * Then no emails should have been sent to the address "user@example.com"
   * @endcode
   */
  #[Then('no emails should have been sent to the address :address')]
  public function emailAssertMessagesNotSentToAddress(string $address): void {
    foreach ($this->emailGetCollectedMessages() as $message) {
      $to = $this->stringSplitCommaSeparated((string) $message['to']);
      if (in_array($address, $to, TRUE)) {
        throw new ExpectationException(sprintf('An email was sent to "%s" retrieved from test email collector, but it should not have been.', $address), $this->getSession()->getDriver());
      }

      if (!empty($message['headers']['Cc'] ?? $message['headers']['cc'] ?? NULL)) {
        $cc = $this->stringSplitCommaSeparated((string) ($message['headers']['Cc'] ?? $message['headers']['cc']));
        if (in_array($address, $cc, TRUE)) {
          throw new ExpectationException(sprintf('An email was cc\'ed to "%s" retrieved from test email collector, but it should not have been.', $address), $this->getSession()->getDriver());
        }
      }

      if (!empty($message['headers']['Bcc'] ?? $message['headers']['bcc'] ?? NULL)) {
        $bcc = $this->stringSplitCommaSeparated((string) ($message['headers']['Bcc'] ?? $message['headers']['bcc']));
        if (in_array($address, $bcc, TRUE)) {
          throw new ExpectationException(sprintf('An email was bcc\'ed to "%s" retrieved from test email collector, but it should not have been.', $address), $this->getSession()->getDriver());
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
   */
  #[Then('the email header :header should contain:')]
  public function emailAssertMessageHeaderContains(string $header, PyStringNode $string, bool $exact = FALSE): void {
    $string_value = (string) $string;
    $string_value = $exact ? $string_value : $this->stringNormalizeWhitespace($string_value);

    foreach ($this->emailGetCollectedMessages() as $message) {
      $header_value = $message['headers'][$header] ?? '';
      $header_value = $exact ? $header_value : $this->stringNormalizeWhitespace((string) $header_value);

      if (str_contains((string) $header_value, (string) $string_value)) {
        return;
      }
    }

    throw new ExpectationException(sprintf('Unable to find an email where the header "%s" should contain%s text "%s" retrieved from test email collector.', $header, ($exact ? ' exact' : ''), $string), $this->getSession()->getDriver());
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
   */
  #[Then('the email header :header should exactly be:')]
  public function emailAssertMessageHeaderEquals(string $header, PyStringNode $string): void {
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
   */
  #[Then('an email should be sent to the address :address with the content:')]
  public function emailAssertMessageSentToAddressWithContent(string $address, PyStringNode $string): void {
    $this->emailAssertMessageSentTo($address);
    $this->emailAssertMessageFieldEquals('body', $string);
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
   */
  #[Then('an email should be sent to the address :address with the content containing:')]
  public function emailAssertMessageSentToAddressWithContentContaining(string $address, PyStringNode $string): void {
    $this->emailAssertMessageSentTo($address);
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
   */
  #[Then('an email should be sent to the address :address with the content not containing:')]
  public function emailAssertMessageSentToAddressNotContains(string $address, PyStringNode $string): void {
    $this->emailAssertMessageSentTo($address);
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
   */
  #[Then('an email should not be sent to the address :address with the content:')]
  public function emailAssertMessageNotSentToAddressWithContent(string $address, PyStringNode $string): void {
    $this->emailAssertMessagesNotSentToAddress($address);
    $this->emailAssertMessageFieldNotEquals('body', $string);
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
   */
  #[Then('an email should not be sent to the address :address with the content containing:')]
  public function emailAssertMessageNotSentToAddressWithContentContaining(string $address, PyStringNode $string): void {
    $this->emailAssertMessagesNotSentToAddress($address);
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
   */
  #[Then('the email field :field should contain:')]
  public function emailAssertMessageFieldContains(string $field, PyStringNode $string, bool $exact = FALSE): void {
    $message = $this->emailFindMessage($field, $string, $exact);

    if (!$message) {
      throw new ExpectationException(sprintf('Unable to find an email where the field "%s" should contain%s text "%s" retrieved from test email collector.', $field, ($exact ? ' exact' : ''), $string), $this->getSession()->getDriver());
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
   */
  #[Then('the email field :field should be:')]
  public function emailAssertMessageFieldEquals(string $field, PyStringNode $string): void {
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
   */
  #[Then('the email field :field should not contain:')]
  public function emailAssertMessageFieldNotContains(string $field, PyStringNode $string, bool $exact = FALSE): void {
    if (!in_array($field, ['subject', 'body', 'to', 'from', 'cc', 'bcc'], TRUE)) {
      throw new \RuntimeException(sprintf('Invalid email field %s was specified for assertion.', $field));
    }
    $string = (string) $string;
    $string = $exact ? $string : $this->stringNormalizeWhitespace($string);

    foreach ($this->emailGetCollectedMessages() as $message) {
      $value = $message[$field] ?? '';
      $field_string = $exact ? $value : $this->stringNormalizeWhitespace((string) $value);

      if (str_contains((string) $field_string, (string) $string)) {
        throw new ExpectationException(sprintf('Found an email where the field "%s" contains%s text "%s" retrieved from test email collector, but it should not.', $field, ($exact ? ' exact' : ''), $string), $this->getSession()->getDriver());
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
   */
  #[Then('the email field :field should not be:')]
  public function emailAssertMessageFieldNotEquals(string $field, PyStringNode $string): void {
    $this->emailAssertMessageFieldNotContains($field, $string, TRUE);
  }

  /**
   * Assert that a file is attached to an email message with specified subject.
   *
   * @code
   * Then the file "document.pdf" should be attached to the email with the subject "Your document"
   * @endcode
   */
  #[Then('the file :file_name should be attached to the email with the subject :subject')]
  public function emailAssertMessageContainsAttachmentWithName(string $file_name, string $subject): void {
    $this->emailAssertMessageContainsAttachmentBySubject($file_name, $subject, FALSE);
  }

  /**
   * Assert that a file is attached to an email message with a subject containing the specified substring.
   *
   * @code
   * Then the file "report.xlsx" should be attached to the email with a subject containing "Monthly Report"
   * @endcode
   */
  #[Then('the file :file_name should be attached to the email with a subject containing :partial_subject')]
  public function emailAssertMessageContainsAttachmentWithSubjectContaining(string $file_name, string $partial_subject): void {
    $this->emailAssertMessageContainsAttachmentBySubject($file_name, $partial_subject, TRUE);
  }

  /**
   * Follow the link at the 1-based index in the first email with a subject.
   *
   * @param string $index
   *   The link index as provided in the step.
   * @param string $subject
   *   The subject, or the part of it to look for.
   * @param bool $is_partial
   *   Whether to search for a partial subject.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   When no email matches, or the email has no link at the index.
   */
  protected function emailFollowLinkWithIndexBySubject(string $index, string $subject, bool $is_partial): void {
    $index = $this->emailParseLinkIndex($index);

    $message = $this->emailGetMessageBySubject($subject, $is_partial);

    if (isset($message['params']['body']) && is_string($message['params']['body'])) {
      $body = $message['params']['body'];
    }
    // @codeCoverageIgnoreStart
    elseif (is_string($message['body'])) {
      $body = $message['body'];
    }
    else {
      throw new \RuntimeException('No body found in email.');
    }
    // @codeCoverageIgnoreEnd

    $links = static::emailExtractLinks($body);

    if ($links === []) {
      throw new ExpectationException(sprintf('No links were found in the email with subject%s "%s".', $is_partial ? ' containing' : '', $subject), $this->getSession()->getDriver());
    }

    if (count($links) < $index) {
      throw new ExpectationException(sprintf('The link with the index %s was not found among %s links.', $index, count($links)), $this->getSession()->getDriver());
    }

    $this->getSession()->visit($links[$index - 1]);
  }

  /**
   * Assert that a file is attached to the first email with a subject.
   *
   * @param string $file_name
   *   The name of the attached file.
   * @param string $subject
   *   The subject, or the part of it to look for.
   * @param bool $is_partial
   *   Whether to search for a partial subject.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   When no email matches, or the file is not attached to it.
   */
  protected function emailAssertMessageContainsAttachmentBySubject(string $file_name, string $subject, bool $is_partial): void {
    $message = $this->emailGetMessageBySubject($subject, $is_partial);

    foreach ($message['params']['attachments'] ?? [] as $attachment) {
      if (($attachment['filename'] ?? NULL) === $file_name) {
        return;
      }
    }

    throw new ExpectationException(sprintf('The file "%s" is not attached to the email with subject%s "%s".', $file_name, $is_partial ? ' containing' : '', $subject), $this->getSession()->getDriver());
  }

  /**
   * Find the default mail system value.
   */
  protected static function emailFindMailSystemDefault(string $type = 'default'): mixed {
    return \Drupal::config('system.mail')->get('interface.' . $type);
  }

  /**
   * Set the default mail system value.
   */
  protected function emailSetMailSystemDefault(string $type, mixed $value): void {
    \Drupal::configFactory()->getEditable('system.mail')->set('interface.' . $type, $value)->save();

    // The Mailsystem module replaces the default interface, so update its
    // configuration as well when the module is installed.
    // @codeCoverageIgnoreStart
    if ($this->anyBackendFor(ModuleCapabilityInterface::class)->moduleIsEnabled('mailsystem')) {
      \Drupal::configFactory()->getEditable('mailsystem.settings')
        ->set('defaults.sender', $value)
        ->save();
    }
    // @codeCoverageIgnoreEnd
  }

  /**
   * Find the original mail system value.
   */
  protected static function emailFindMailSystemOriginal(string $type = 'default'): mixed {
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
   * @return array<string, array<string, mixed>>
   *   Array of collected emails.
   */
  public function emailGetCollectedMessages(): array {
    $this->backendFor(CoreCapabilityInterface::class);

    // Directly read data from the database to avoid cache invalidation that
    // may corrupt the system under test.
    $query = Database::getConnection()->query("SELECT name, value FROM {key_value} WHERE name = 'system.test_mail_collector'");

    // @codeCoverageIgnoreStart
    if (!$query instanceof StatementInterface) {
      throw new \RuntimeException('The test email collector could not be read from the key_value store.');
    }
    // @codeCoverageIgnoreEnd
    $messages = array_map(unserialize(...), $query->fetchAllKeyed());

    $messages = $messages['system.test_mail_collector'] ?? [];

    $fields = ['subject', 'body', 'to', 'from', 'cc', 'bcc'];

    foreach ($messages as $index => $message) {
      $messages[$index] = array_change_key_case($message, CASE_LOWER);

      if ($this->emailDebug) {
        printf("----------------------------------------\n");
        printf("Email message number: %s\n", $index);
        printf("----------------------------------------\n");
        foreach ($fields as $field) {
          printf("Field: %s\n", $field);
          printf("Value: %s\n", $messages[$index][$field] ?? '<EMPTY>');
          print PHP_EOL;
        }
      }
    }

    return $messages;
  }

  /**
   * Find an email message whose field contains a value.
   *
   * @param string $field
   *   Field to search in.
   * @param \Behat\Gherkin\Node\PyStringNode $string
   *   String to search for.
   * @param bool $exact
   *   Whether to search for an exact match.
   *
   * @return array<string, string|array<string, mixed>>|null
   *   Email message or NULL if not found.
   */
  public function emailFindMessage(string $field, PyStringNode $string, bool $exact = FALSE): ?array {
    if (!in_array($field, ['subject', 'body', 'to', 'from', 'cc', 'bcc'], TRUE)) {
      throw new \RuntimeException(sprintf('Invalid email field %s was specified for assertion.', $field));
    }
    $string = (string) $string;
    $string = $exact ? $string : $this->stringNormalizeWhitespace($string);

    foreach ($this->emailGetCollectedMessages() as $message) {
      $value = $message[$field] ?? '';
      $field_string = $exact ? $value : $this->stringNormalizeWhitespace((string) $value);

      if (str_contains((string) $field_string, (string) $string)) {
        return $message;
      }
    }

    return NULL;
  }

  /**
   * Get the first collected email by exact or partial subject.
   *
   * @param string $subject
   *   The subject, or the part of it to look for.
   * @param bool $is_partial
   *   Whether to search for a partial subject.
   *
   * @return array<string, mixed>
   *   The email message.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   When no collected email matches.
   */
  public function emailGetMessageBySubject(string $subject, bool $is_partial = FALSE): array {
    $message = $this->emailFindMessageBySubject($subject, $is_partial);

    if ($message === NULL) {
      throw new ExpectationException(sprintf('Unable to find email with subject%s "%s" retrieved from test email collector.', $is_partial ? ' containing' : '', $subject), $this->getSession()->getDriver());
    }

    return $message;
  }

  /**
   * Find the first collected email by exact or partial subject.
   *
   * An exact search compares the whole subject, and a partial search matches
   * a substring of it. Both are case-sensitive.
   *
   * @param string $subject
   *   The subject, or the part of it to look for.
   * @param bool $is_partial
   *   Whether to search for a partial subject.
   *
   * @return array<string, mixed>|null
   *   The email message, or NULL when no collected email matches.
   */
  public function emailFindMessageBySubject(string $subject, bool $is_partial = FALSE): ?array {
    foreach ($this->emailGetCollectedMessages() as $message) {
      $message_subject = (string) ($message['subject'] ?? '');

      if ($is_partial ? str_contains($message_subject, $subject) : $message_subject === $subject) {
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
  public static function emailExtractLinks(string $string): array {
    $pattern = '(?xi)\b((?:https?://|www\d{0,3}[.]|[a-z0-9.\-]+[.][a-z]{2,4}/)(?:[^\s()<>]+|\(([^\s()<>]+|(\([^\s()<>]+\)))*\))+(?:\(([^\s()<>]+|(\([^\s()<>]+\)))*\)|[^\s`!()\[\]{};:\'".,<>?«»“”‘’]))';
    $string = preg_replace_callback(sprintf('#%s#i', $pattern), fn(array $matches): string => preg_match('!^https?://!i', $matches[0]) ? $matches[0] : 'http://' . $matches[0], $string);

    preg_match_all(sprintf('#%s#i', $pattern), (string) $string, $matches);

    return empty($matches[0]) ? [] : $matches[0];
  }

  /**
   * Parse a link index step argument into a positive integer.
   *
   * Links are indexed from 1, so an index below 1 is rejected.
   *
   * @param string $index
   *   The link index as provided in the step.
   *
   * @return int
   *   The link index as a positive integer.
   *
   * @throws \RuntimeException
   *   When the link index is not a positive integer.
   */
  protected function emailParseLinkIndex(string $index): int {
    if (!ctype_digit(trim($index)) || (int) $index < 1) {
      throw new \RuntimeException(sprintf('The link index must be a positive integer, but "%s" was provided.', $index));
    }

    return (int) $index;
  }

  /**
   * Declares the options this trait reads.
   *
   * @return array<int, \DrevOps\BehatSteps\Behat\Config\Option>
   *   The options this trait declares.
   */
  protected function emailConfigSchema(): array {
    return [
      new Option('enabled', default: TRUE, description: 'Collect email for an `@email` scenario and clear the queue around it.'),
    ];
  }

}
