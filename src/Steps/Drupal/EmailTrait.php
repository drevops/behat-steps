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
  protected const string EMAIL_TAG = 'email';

  /**
   * The tag that prints each collected message as it is read.
   */
  protected const string EMAIL_DEBUG_TAG = 'debug';

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

    $this->emailEnableCollector();
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

    $this->emailDisableCollector();
  }

  /**
   * Clear test email system queue.
   *
   * @code
   * When I clear the test email system queue
   * @endcode
   */
  #[When('I clear the test email system queue')]
  public function emailClearTestQueue(): void {
    $this->backendFor(CoreCapabilityInterface::class);

    if (!static::emailFindMailSystemOriginal()) {
      throw new \RuntimeException('Clearing testing email system queue can be done only when email testing system is activated. Add @email tag or "When I enable the test email system" step definition to the scenario.');
    }

    $this->emailClearCollectedMessages();
  }

  /**
   * Follow the link at the 1-based index in an email with the given subject.
   *
   * @code
   * When I follow the link with the index "1" in the email with the subject "Account Verification"
   * @endcode
   */
  #[When('I follow the link with the index :index in the email with the subject :subject')]
  public function emailFollowLinkWithIndexWithSubject(string $index, string $subject): void {
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
  public function emailFollowLinkWithUrlContaining(string $partial_url): void {
    $link = $this->emailFindLinkContaining($partial_url);

    if ($link === NULL) {
      throw new ExpectationException(sprintf('No email contains a link with "%s" in its URL.', $partial_url), $this->getSession()->getDriver());
    }

    $this->getSession()->visit($link);
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
    $this->emailEnableCollector();
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
    $this->emailDisableCollector();
  }

  /**
   * Assert that an email should be sent to an address.
   *
   * @code
   * Then an email should be sent to the address "user@example.com"
   * @endcode
   */
  #[Then('an email should be sent to the address :address')]
  public function emailAssertMessageSentToAddress(string $address): void {
    $this->emailAssertMessageExistsToAddress($address);
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
  public function emailAssertMessageCount(string $count): void {
    $count = $this->stringParseInteger($count, 'count', 0);

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
  public function emailAssertMessageCountToAddress(string $count, string $address): void {
    $count = $this->stringParseInteger($count, 'count', 0);

    $actual = count($this->emailGetMessagesToAddress($address));

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
  public function emailAssertMessageCountWithSubject(string $count, string $subject): void {
    $count = $this->stringParseInteger($count, 'count', 0);

    $actual = count($this->emailGetMessagesWithSubject($subject));

    if ($actual !== $count) {
      throw new ExpectationException(sprintf('Expected %d email(s) to have been sent with the subject "%s", but %d were found.', $count, $subject, $actual), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that no email was sent.
   *
   * @code
   * Then an email should not be sent
   * @endcode
   */
  #[Then('an email should not be sent')]
  public function emailAssertMessageNotSent(): void {
    $messages = $this->emailGetCollectedMessages();
    if (count($messages) > 0) {
      throw new ExpectationException('An email was sent, but it should not have been:' . PHP_EOL . print_r($messages, TRUE), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that no email was sent to an address.
   *
   * @code
   * Then an email should not be sent to the address "user@example.com"
   * @endcode
   */
  #[Then('an email should not be sent to the address :address')]
  public function emailAssertMessageNotSentToAddress(string $address): void {
    $this->emailAssertMessageNotExistsToAddress($address);
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
  public function emailAssertMessageHeaderContains(string $header, PyStringNode $string): void {
    $this->emailAssertMessageExistsWithHeaderValue($header, $string, FALSE);
  }

  /**
   * Assert that the email message header should be the exact specified content.
   *
   * @code
   * Then the email header "Subject" should be:
   * """
   * Your Account Details
   * """
   * @endcode
   */
  #[Then('the email header :header should be:')]
  public function emailAssertMessageHeaderEquals(string $header, PyStringNode $string): void {
    $this->emailAssertMessageExistsWithHeaderValue($header, $string, TRUE);
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
    $this->emailAssertMessageExistsToAddress($address);
    $this->emailAssertMessageExistsWithFieldValue('body', $string, TRUE);
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
    $this->emailAssertMessageExistsToAddress($address);
    $this->emailAssertMessageExistsWithFieldValue('body', $string, FALSE);
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
    $this->emailAssertMessageExistsToAddress($address);
    $this->emailAssertMessageNotExistsWithFieldValue('body', $string, FALSE);
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
    $this->emailAssertMessageNotExistsToAddress($address);
    $this->emailAssertMessageNotExistsWithFieldValue('body', $string, TRUE);
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
    $this->emailAssertMessageNotExistsToAddress($address);
    $this->emailAssertMessageNotExistsWithFieldValue('body', $string, FALSE);
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
  public function emailAssertMessageFieldContains(string $field, PyStringNode $string): void {
    $this->emailAssertMessageExistsWithFieldValue($field, $string, FALSE);
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
    $this->emailAssertMessageExistsWithFieldValue($field, $string, TRUE);
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
  public function emailAssertMessageFieldNotContains(string $field, PyStringNode $string): void {
    $this->emailAssertMessageNotExistsWithFieldValue($field, $string, FALSE);
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
    $this->emailAssertMessageNotExistsWithFieldValue($field, $string, TRUE);
  }

  /**
   * Assert that a file is attached to an email message with specified subject.
   *
   * @code
   * Then the file "document.pdf" should be attached to the email with the subject "Your document"
   * @endcode
   */
  #[Then('the file :filename should be attached to the email with the subject :subject')]
  public function emailAssertMessageContainsAttachmentWithSubject(string $filename, string $subject): void {
    $this->emailAssertMessageContainsAttachmentBySubject($filename, $subject, FALSE);
  }

  /**
   * Assert that a file is attached to an email message with a subject containing the specified substring.
   *
   * @code
   * Then the file "report.xlsx" should be attached to the email with a subject containing "Monthly Report"
   * @endcode
   */
  #[Then('the file :filename should be attached to the email with a subject containing :partial_subject')]
  public function emailAssertMessageContainsAttachmentWithSubjectContaining(string $filename, string $partial_subject): void {
    $this->emailAssertMessageContainsAttachmentBySubject($filename, $partial_subject, TRUE);
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
   * @throws \RuntimeException
   *   When the index is not an integer of 1 or greater.
   */
  protected function emailFollowLinkWithIndexBySubject(string $index, string $subject, bool $is_partial): void {
    $index = $this->stringParseInteger($index, 'link index', 1);

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
   * @param string $filename
   *   The name of the attached file.
   * @param string $subject
   *   The subject, or the part of it to look for.
   * @param bool $is_partial
   *   Whether to search for a partial subject.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   When no email matches, or the file is not attached to it.
   */
  protected function emailAssertMessageContainsAttachmentBySubject(string $filename, string $subject, bool $is_partial): void {
    $message = $this->emailGetMessageBySubject($subject, $is_partial);

    foreach ($message['params']['attachments'] ?? [] as $attachment) {
      if (($attachment['filename'] ?? NULL) === $filename) {
        return;
      }
    }

    throw new ExpectationException(sprintf('The file "%s" is not attached to the email with subject%s "%s".', $filename, $is_partial ? ' containing' : '', $subject), $this->getSession()->getDriver());
  }

  /**
   * Switch every handler type to the test mail collector, and clear it.
   *
   * The handler types default to `default`. The mail system each type used
   * before is stored, so emailDisableCollector() can restore it.
   */
  public function emailEnableCollector(): void {
    $this->backendFor(CoreCapabilityInterface::class);

    $this->emailHandlerTypes = array_values(array_unique($this->emailHandlerTypes ?: ['default']));

    foreach ($this->emailHandlerTypes as $type) {
      $original_test_system = static::emailFindMailSystemDefault($type);
      if (!static::emailFindMailSystemOriginal($type)) {
        static::emailSetMailSystemOriginal($type, $original_test_system);
      }
      $this->emailSetMailSystemDefault($type, 'test_mail_collector');
    }

    $this->emailClearCollectedMessages();
  }

  /**
   * Restore the mail system of every handler type, and clear the collector.
   */
  public function emailDisableCollector(): void {
    $this->backendFor(CoreCapabilityInterface::class);

    foreach ($this->emailHandlerTypes as $type) {
      $original_test_system = static::emailFindMailSystemOriginal($type);
      $this->emailSetMailSystemDefault($type, $original_test_system);
    }

    static::emailDeleteMailSystemOriginal();
    $this->emailClearCollectedMessages();
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
        printf('----------------------------------------' . PHP_EOL);
        printf('Email message number: %s' . PHP_EOL, $index);
        printf('----------------------------------------' . PHP_EOL);
        foreach ($fields as $field) {
          printf('Field: %s' . PHP_EOL, $field);
          printf('Value: %s' . PHP_EOL, $messages[$index][$field] ?? '<EMPTY>');
          print PHP_EOL;
        }
      }
    }

    return $messages;
  }

  /**
   * Delete the email messages collected during the test.
   */
  protected function emailClearCollectedMessages(): void {
    \Drupal::state()->set('system.test_mail_collector', []);
  }

  /**
   * Find an email message whose field contains a value.
   *
   * @param string $field
   *   Field to search in.
   * @param \Behat\Gherkin\Node\PyStringNode $string
   *   String to search for.
   * @param bool $is_exact
   *   Whether to search for an exact match.
   *
   * @return array<string, string|array<string, mixed>>|null
   *   Email message or NULL if not found.
   */
  public function emailFindMessage(string $field, PyStringNode $string, bool $is_exact = FALSE): ?array {
    if (!in_array($field, ['subject', 'body', 'to', 'from', 'cc', 'bcc'], TRUE)) {
      throw new \RuntimeException(sprintf('Invalid email field "%s" was specified for assertion.', $field));
    }
    $string = (string) $string;
    $string = $is_exact ? $string : $this->stringNormalizeWhitespace($string);

    foreach ($this->emailGetCollectedMessages() as $message) {
      $value = $message[$field] ?? '';
      $field_string = $is_exact ? $value : $this->stringNormalizeWhitespace((string) $value);

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
      throw new ExpectationException(sprintf('Unable to find an email with the subject%s "%s" retrieved from test email collector.', $is_partial ? ' containing' : '', $subject), $this->getSession()->getDriver());
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
   * Get the collected emails sent to an address.
   *
   * @param string $address
   *   The email address, matched against the "to" recipients only.
   *
   * @return array<int|string, array<string, mixed>>
   *   The email messages, keyed as collected.
   */
  public function emailGetMessagesToAddress(string $address): array {
    return array_filter($this->emailGetCollectedMessages(), fn(array $message): bool => in_array($address, $this->stringSplitCommaSeparated((string) $message['to']), TRUE));
  }

  /**
   * Get the collected emails with a subject.
   *
   * @param string $subject
   *   The whole subject, compared case-sensitively.
   *
   * @return array<int|string, array<string, mixed>>
   *   The email messages, keyed as collected.
   */
  public function emailGetMessagesWithSubject(string $subject): array {
    return array_filter($this->emailGetCollectedMessages(), static fn(array $message): bool => (string) $message['subject'] === $subject);
  }

  /**
   * Find the first link in a collected email whose URL contains a value.
   *
   * @param string $partial_url
   *   The part of the URL to look for.
   *
   * @return string|null
   *   The link, or NULL when no collected email holds such a link.
   */
  public function emailFindLinkContaining(string $partial_url): ?string {
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
          return $link;
        }
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
    $string = preg_replace_callback(sprintf('#%s#i', $pattern), static fn(array $matches): string => preg_match('!^https?://!i', $matches[0]) ? $matches[0] : 'http://' . $matches[0], $string);

    preg_match_all(sprintf('#%s#i', $pattern), (string) $string, $matches);

    return empty($matches[0]) ? [] : $matches[0];
  }

  /**
   * Assert that a collected email was sent to an address.
   *
   * @param string $address
   *   The email address, matched against the "to" recipients only.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   When no collected email was sent to the address.
   */
  protected function emailAssertMessageExistsToAddress(string $address): void {
    if ($this->emailGetMessagesToAddress($address) === []) {
      throw new ExpectationException(sprintf('Unable to find an email that should be sent to "%s" retrieved from test email collector.', $address), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that no collected email names an address as a recipient.
   *
   * @param string $address
   *   The email address.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   When a collected email names the address as a "to", "cc" or "bcc"
   *   recipient.
   */
  protected function emailAssertMessageNotExistsToAddress(string $address): void {
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
   * Assert that a collected email has a header containing a value.
   *
   * @param string $header
   *   The header name.
   * @param \Behat\Gherkin\Node\PyStringNode $string
   *   The value to search for.
   * @param bool $is_exact
   *   Whether to compare whitespace as written rather than collapsed.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   When no collected email has the header containing the value.
   */
  protected function emailAssertMessageExistsWithHeaderValue(string $header, PyStringNode $string, bool $is_exact): void {
    $string_value = (string) $string;
    $string_value = $is_exact ? $string_value : $this->stringNormalizeWhitespace($string_value);

    foreach ($this->emailGetCollectedMessages() as $message) {
      $header_value = $message['headers'][$header] ?? '';
      $header_value = $is_exact ? $header_value : $this->stringNormalizeWhitespace((string) $header_value);

      if (str_contains((string) $header_value, (string) $string_value)) {
        return;
      }
    }

    throw new ExpectationException(sprintf('Unable to find an email where the header "%s" should contain%s text "%s" retrieved from test email collector.', $header, ($is_exact ? ' exact' : ''), $string), $this->getSession()->getDriver());
  }

  /**
   * Assert that a collected email has a field containing a value.
   *
   * @param string $field
   *   The field to search in.
   * @param \Behat\Gherkin\Node\PyStringNode $string
   *   The value to search for.
   * @param bool $is_exact
   *   Whether to compare whitespace as written rather than collapsed.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   When no collected email has the field containing the value.
   */
  protected function emailAssertMessageExistsWithFieldValue(string $field, PyStringNode $string, bool $is_exact): void {
    $message = $this->emailFindMessage($field, $string, $is_exact);

    if (!$message) {
      throw new ExpectationException(sprintf('Unable to find an email where the field "%s" should contain%s text "%s" retrieved from test email collector.', $field, ($is_exact ? ' exact' : ''), $string), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert that no collected email has a field containing a value.
   *
   * @param string $field
   *   The field to search in.
   * @param \Behat\Gherkin\Node\PyStringNode $string
   *   The value to search for.
   * @param bool $is_exact
   *   Whether to compare whitespace as written rather than collapsed.
   *
   * @throws \RuntimeException
   *   When the field is not an email field.
   * @throws \Behat\Mink\Exception\ExpectationException
   *   When a collected email has the field containing the value.
   */
  protected function emailAssertMessageNotExistsWithFieldValue(string $field, PyStringNode $string, bool $is_exact): void {
    if (!in_array($field, ['subject', 'body', 'to', 'from', 'cc', 'bcc'], TRUE)) {
      throw new \RuntimeException(sprintf('Invalid email field "%s" was specified for assertion.', $field));
    }
    $string = (string) $string;
    $string = $is_exact ? $string : $this->stringNormalizeWhitespace($string);

    foreach ($this->emailGetCollectedMessages() as $message) {
      $value = $message[$field] ?? '';
      $field_string = $is_exact ? $value : $this->stringNormalizeWhitespace((string) $value);

      if (str_contains((string) $field_string, (string) $string)) {
        throw new ExpectationException(sprintf('Found an email where the field "%s" contains%s text "%s" retrieved from test email collector, but it should not.', $field, ($is_exact ? ' exact' : ''), $string), $this->getSession()->getDriver());
      }
    }
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
