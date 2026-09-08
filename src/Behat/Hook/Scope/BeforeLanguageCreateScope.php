<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Hook\Scope;

/**
 * Scope dispatched before a language is created.
 */
final class BeforeLanguageCreateScope extends LanguageScope {

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return self::BEFORE;
  }

}
