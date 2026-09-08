<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Hook\Scope;

/**
 * Scope dispatched after a generic entity is created.
 */
final class AfterEntityCreateScope extends BaseEntityScope {

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return self::AFTER;
  }

}
