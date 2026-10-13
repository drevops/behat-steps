<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Extension\Hook\Scope;

/**
 * Scope dispatched before a user is created.
 */
final class BeforeUserCreateScope extends UserScope {

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return self::BEFORE;
  }

}
