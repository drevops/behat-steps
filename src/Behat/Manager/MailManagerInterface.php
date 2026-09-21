<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Manager;

/**
 * Interface for classes that manage mail created during tests.
 */
interface MailManagerInterface {

  /**
   * Collects outbound mail for analysis.
   */
  public function startCollectingMail(): void;

  /**
   * Stops collecting outbound mail.
   */
  public function stopCollectingMail(): void;

  /**
   * Allows mail to be actually sent out.
   */
  public function enableMail(): void;

  /**
   * Prevents mail from being actually sent out.
   */
  public function disableMail(): void;

  /**
   * Gets all collected mail.
   *
   * @return array<int, array<string, mixed>>
   *   An array of collected emails. Each item is a Drupal mail message
   *   array as produced by 'MailInterface::mail()' - the keys include
   *   'to', 'subject', 'body', 'headers', etc.
   */
  public function getMail(): array;

  /**
   * Empties the store of collected mail.
   */
  public function clearMail(): void;

}
