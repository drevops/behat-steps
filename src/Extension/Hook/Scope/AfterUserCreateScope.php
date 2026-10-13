<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Extension\Hook\Scope;

/**
 * Scope dispatched after a user is created.
 */
final class AfterUserCreateScope extends UserScope {

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return self::AFTER;
  }

}
