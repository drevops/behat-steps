<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Hook\Scope;

/**
 * Scope dispatched after a taxonomy term is created.
 */
final class AfterTermCreateScope extends TermScope {

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return self::AFTER;
  }

}
