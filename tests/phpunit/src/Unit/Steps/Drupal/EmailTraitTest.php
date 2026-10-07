<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Steps\Drupal;

use Behat\Mink\Driver\DriverInterface;
use Behat\Mink\Exception\ExpectationException;
use Behat\Mink\Mink;
use Behat\Mink\Session;
use DrevOps\BehatSteps\Behat\Context\WebRawContext;
use DrevOps\BehatSteps\Steps\Drupal\EmailTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests for EmailTrait.
 */
#[CoversTrait(EmailTrait::class)]
class EmailTraitTest extends UnitTestCase {

  /**
   * Collected messages the subject lookups and the subject steps read.
   */
  protected const MESSAGES = [
    ['subject' => 'Account Verification', 'params' => ['body' => 'Verify at http://example.com/verify']],
    ['subject' => 'Your Account Verification code', 'params' => ['body' => 'Codes: http://example.com/code/1 http://example.com/code/2']],
    ['subject' => 'Welcome', 'params' => ['body' => 'Welcome aboard.']],
    ['subject' => 'Monthly report', 'params' => ['body' => 'See attached.', 'attachments' => [['filename' => 'report.pdf'], ['content' => 'An entry without a file name']]]],
  ];

  #[DataProvider('dataProviderExtractLinks')]
  public function testExtractLinks(string $input, array $expected): void {
    $result = EmailTraitTestImplementation::emailExtractLinks($input);
    $this->assertSame($expected, $result);
  }

  public static function dataProviderExtractLinks(): array {
    return [
      'single link' => [
        'Please visit http://example.com for more information.',
        ['http://example.com'],
      ],
      'multiple links' => [
        'Please visit http://example.com or http://example.org for more information.',
        ['http://example.com', 'http://example.org'],
      ],
      'www link without protocol' => [
        'Please visit www.example.com for more information.',
        ['http://www.example.com'],
      ],
      'link with path' => [
        'Please visit http://example.com/page/123 for more information.',
        ['http://example.com/page/123'],
      ],
      'link with query parameters' => [
        'Please visit http://example.com/?param=value&another=123 for more information.',
        ['http://example.com/?param=value&another=123'],
      ],
      'link with hash' => [
        'Please visit http://example.com/#section for more information.',
        ['http://example.com/#section'],
      ],
      'link with parentheses' => [
        'Please visit (http://example.com) for more information.',
        ['http://example.com'],
      ],
      'no links' => [
        'This text does not contain any links.',
        [],
      ],
      'link with special characters' => [
        'Check this link: http://example.com/path/with-dash_underscore+plus~tilde?q=search&x=y#fragment',
        ['http://example.com/path/with-dash_underscore+plus~tilde?q=search&x=y#fragment'],
      ],
      'malformed link missing protocol' => [
        'This is a malformed link: example.com/page',
        ['http://example.com/page'],
      ],
      'links in HTML context' => [
        '<p>Visit our <a href="http://example.com">website</a> for more information.</p>',
        ['http://example.com'],
      ],
      'links in markdown context' => [
        'Visit our [website](http://example.com) or click on https://example.org directly.',
        ['http://example.com', 'https://example.org'],
      ],
    ];
  }

  public function testTeardownSkipsScenarioThatEnabledNothing(): void {
    $this->expectNotToPerformAssertions();

    // The context holds no backend registry, so resolving a backend would
    // throw.
    (new EmailTraitTestImplementation())->emailAfterScenario($this->createAfterScenarioScope(['email']));
  }

  #[DataProvider('dataProviderFindMessageBySubject')]
  public function testFindMessageBySubject(array $messages, string $subject, bool $is_partial, ?int $expected): void {
    $message = $this->createContext($messages)->emailFindMessageBySubject($subject, $is_partial);

    $this->assertSame($expected === NULL ? NULL : $messages[$expected], $message);
  }

  public static function dataProviderFindMessageBySubject(): \Iterator {
    yield 'whole subject' => [static::MESSAGES, 'Account Verification', FALSE, 0];
    yield 'whole subject of a later email' => [static::MESSAGES, 'Your Account Verification code', FALSE, 1];
    yield 'part of a subject' => [static::MESSAGES, 'Verification', FALSE, NULL];
    yield 'whole subject in another case' => [static::MESSAGES, 'account verification', FALSE, NULL];
    yield 'whole subject with collapsed whitespace' => [[['subject' => 'Account  Verification']], 'Account Verification', FALSE, NULL];
    yield 'whole subject with surrounding whitespace' => [[['subject' => ' Account Verification ']], 'Account Verification', FALSE, NULL];
    yield 'partial, part of a subject' => [static::MESSAGES, 'Verification', TRUE, 0];
    yield 'partial, part only a later email has' => [static::MESSAGES, 'code', TRUE, 1];
    yield 'partial, whole subject' => [static::MESSAGES, 'Welcome', TRUE, 2];
    yield 'partial, part in another case' => [static::MESSAGES, 'verification', TRUE, NULL];
    yield 'no collected emails' => [[], 'Account Verification', FALSE, NULL];
    yield 'email without a subject' => [[['params' => ['body' => 'No subject']]], 'Account Verification', TRUE, NULL];
    yield 'subject held as markup' => [[['subject' => static::createStringable('Account Verification')]], 'Account Verification', FALSE, 0];
  }

  public function testGetMessageBySubject(): void {
    $this->assertSame(static::MESSAGES[1], $this->createContext(static::MESSAGES)->emailGetMessageBySubject('code', TRUE));
  }

  #[DataProvider('dataProviderGetMessageBySubjectFails')]
  public function testGetMessageBySubjectFails(string $subject, bool $is_partial, string $expected_message): void {
    $context = $this->createContext(static::MESSAGES);

    $this->expectException(ExpectationException::class);
    $this->expectExceptionMessage($expected_message);

    $context->emailGetMessageBySubject($subject, $is_partial);
  }

  public static function dataProviderGetMessageBySubjectFails(): \Iterator {
    yield 'whole subject' => ['Verification', FALSE, 'Unable to find email with subject "Verification" retrieved from test email collector.'];
    yield 'part of a subject' => ['verification', TRUE, 'Unable to find email with subject containing "verification" retrieved from test email collector.'];
  }

  #[DataProvider('dataProviderFollowLinkWithIndexBySubject')]
  public function testFollowLinkWithIndexBySubject(string $method, string $index, string $subject, string $expected_url): void {
    $session = $this->createSession();
    $session->expects($this->once())->method('visit')->with($expected_url);

    $this->createContext(static::MESSAGES, $session)->{$method}($index, $subject);
  }

  public static function dataProviderFollowLinkWithIndexBySubject(): \Iterator {
    yield 'whole subject' => ['emailFollowLinkWithIndexWithSubject', '1', 'Account Verification', 'http://example.com/verify'];
    yield 'whole subject of a later email' => ['emailFollowLinkWithIndexWithSubject', '2', 'Your Account Verification code', 'http://example.com/code/2'];
    yield 'part of a subject' => ['emailFollowLinkWithIndexWithSubjectContaining', '1', 'Verification', 'http://example.com/verify'];
    yield 'part only a later email has' => ['emailFollowLinkWithIndexWithSubjectContaining', '2', 'code', 'http://example.com/code/2'];
  }

  #[DataProvider('dataProviderFollowLinkWithIndexBySubjectFails')]
  public function testFollowLinkWithIndexBySubjectFails(string $method, string $index, string $subject, string $expected_message): void {
    $session = $this->createSession();
    $session->expects($this->never())->method('visit');
    $context = $this->createContext(static::MESSAGES, $session);

    $this->expectException(ExpectationException::class);
    $this->expectExceptionMessage($expected_message);

    $context->{$method}($index, $subject);
  }

  public static function dataProviderFollowLinkWithIndexBySubjectFails(): \Iterator {
    yield 'part of a subject' => ['emailFollowLinkWithIndexWithSubject', '1', 'Verification', 'Unable to find email with subject "Verification" retrieved from test email collector.'];
    yield 'part in another case' => ['emailFollowLinkWithIndexWithSubjectContaining', '1', 'verification', 'Unable to find email with subject containing "verification" retrieved from test email collector.'];
    yield 'no links' => ['emailFollowLinkWithIndexWithSubject', '1', 'Welcome', 'No links were found in the email with subject "Welcome".'];
    yield 'no links, part of a subject' => ['emailFollowLinkWithIndexWithSubjectContaining', '1', 'Welc', 'No links were found in the email with subject containing "Welc".'];
    yield 'index past the last link' => ['emailFollowLinkWithIndexWithSubject', '3', 'Your Account Verification code', 'The link with the index 3 was not found among 2 links.'];
  }

  #[DataProvider('dataProviderAssertMessageContainsAttachmentBySubject')]
  public function testAssertMessageContainsAttachmentBySubject(string $method, string $file_name, string $subject): void {
    $this->expectNotToPerformAssertions();

    $this->createContext(static::MESSAGES)->{$method}($file_name, $subject);
  }

  public static function dataProviderAssertMessageContainsAttachmentBySubject(): \Iterator {
    yield 'whole subject' => ['emailAssertMessageContainsAttachmentWithSubject', 'report.pdf', 'Monthly report'];
    yield 'part of a subject' => ['emailAssertMessageContainsAttachmentWithSubjectContaining', 'report.pdf', 'Monthly'];
  }

  #[DataProvider('dataProviderAssertMessageContainsAttachmentBySubjectFails')]
  public function testAssertMessageContainsAttachmentBySubjectFails(string $method, string $file_name, string $subject, string $expected_message): void {
    $context = $this->createContext(static::MESSAGES);

    $this->expectException(ExpectationException::class);
    $this->expectExceptionMessage($expected_message);

    $context->{$method}($file_name, $subject);
  }

  public static function dataProviderAssertMessageContainsAttachmentBySubjectFails(): \Iterator {
    yield 'part of a subject' => ['emailAssertMessageContainsAttachmentWithSubject', 'report.pdf', 'Monthly', 'Unable to find email with subject "Monthly" retrieved from test email collector.'];
    yield 'part in another case' => ['emailAssertMessageContainsAttachmentWithSubjectContaining', 'report.pdf', 'monthly', 'Unable to find email with subject containing "monthly" retrieved from test email collector.'];
    yield 'email without attachments' => ['emailAssertMessageContainsAttachmentWithSubject', 'report.pdf', 'Welcome', 'The file "report.pdf" is not attached to the email with subject "Welcome".'];
    yield 'email with other attachments' => ['emailAssertMessageContainsAttachmentWithSubject', 'summary.pdf', 'Monthly report', 'The file "summary.pdf" is not attached to the email with subject "Monthly report".'];
    yield 'email with other attachments, part of a subject' => ['emailAssertMessageContainsAttachmentWithSubjectContaining', 'summary.pdf', 'report', 'The file "summary.pdf" is not attached to the email with subject containing "report".'];
  }

  /**
   * Builds a context whose test email collector holds the given messages.
   *
   * @param array<int, array<string, mixed>> $messages
   *   The collected messages.
   * @param \Behat\Mink\Session|null $session
   *   The session the context drives, or NULL for a new one.
   */
  protected function createContext(array $messages, ?Session $session = NULL): EmailTraitTestImplementation {
    $mink = new Mink(['default' => $session ?? $this->createSession()]);
    $mink->setDefaultSessionName('default');

    $context = new EmailTraitTestImplementation();
    $context->setMink($mink);
    $context->collectedMessages = $messages;

    return $context;
  }

  /**
   * Builds a session whose driver an assertion exception can carry.
   */
  protected function createSession(): Session&MockObject {
    $session = $this->createMock(Session::class);
    $session->method('getDriver')->willReturn($this->createStub(DriverInterface::class));

    return $session;
  }

  /**
   * Returns an anonymous Stringable that mimics a Drupal TranslatableMarkup.
   */
  protected static function createStringable(string $value): \Stringable {
    return new readonly class($value) {

      public function __construct(protected string $value) {}

      public function __toString(): string {
        return $this->value;
      }

    };
  }

}

/**
 * Test implementation of EmailTrait.
 *
 * Replaces the test email collector with the messages a test supplies.
 */
class EmailTraitTestImplementation extends WebRawContext {

  use EmailTrait;

  /**
   * Messages returned in place of the test email collector.
   *
   * @var array<int, array<string, mixed>>
   */
  public array $collectedMessages = [];

  /**
   * Returns the messages a test supplied.
   *
   * @return array<int, array<string, mixed>>
   *   The collected messages.
   */
  public function emailGetCollectedMessages(): array {
    return $this->collectedMessages;
  }

}
