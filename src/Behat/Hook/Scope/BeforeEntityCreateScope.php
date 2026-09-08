<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Hook\Scope;

/**
 * Scope dispatched before a generic entity is created.
 */
final class BeforeEntityCreateScope extends BaseEntityScope {

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return self::BEFORE;
  }

}
