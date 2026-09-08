<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Hook\Scope;

/**
 * Scope dispatched after a language is created.
 */
final class AfterLanguageCreateScope extends LanguageScope {

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return self::AFTER;
  }

}
