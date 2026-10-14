<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Capability;

/**
 * Capability: collect and send mail.
 */
interface MailCapabilityInterface {

  /**
   * Starts collecting outgoing mail for later inspection.
   *
   * @param array<int, string> $handler_types
   *   The mail handler types to collect from, as the site's mail system names
   *   them. Defaults to 'default', the handler every site has.
   *
   * @throws \RuntimeException
   *   When no handler type is given.
   */
  public function mailStartCollecting(array $handler_types = ['default']): void;

  /**
   * Stops collecting mail and restores the handlers it replaced.
   *
   * Does nothing when no handler was replaced.
   */
  public function mailStopCollecting(): void;

  /**
   * Returns the mail that has been collected.
   *
   * @return array<int, array<string, mixed>>
   *   Collected mail messages, each as the array the site's mail system hands
   *   its sender, with 'to', 'subject', 'body', 'headers' and 'params' among
   *   its keys. A message the site cancelled, whose 'send' flag is FALSE, is
   *   returned too.
   */
  public function mailGet(): array;

  /**
   * Empties the collected-mail store.
   */
  public function mailClear(): void;

  /**
   * Sends a mail message.
   *
   * @param string $body
   *   The message body.
   * @param string $subject
   *   The message subject.
   * @param string $to
   *   The recipient email address.
   * @param string $langcode
   *   The language code for subject and body.
   * @param array<int, array<string, mixed>> $attachments
   *   Optional attachments. Each is an associative array carrying at least a
   *   'filename', in the shape the site's mail system reads. When provided,
   *   they are recorded on the sent message's 'params' under the
   *   'attachments' key.
   *
   * @return bool
   *   TRUE if the message was accepted for delivery.
   */
  public function mailSend(string $body, string $subject, string $to, string $langcode, array $attachments = []): bool;

}
