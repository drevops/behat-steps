<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Hook\Scope;

/**
 * Scope dispatched before a taxonomy term is created.
 */
final class BeforeTermCreateScope extends TermScope {

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return self::BEFORE;
  }

}
