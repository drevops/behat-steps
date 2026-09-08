<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Hook\Scope;

/**
 * Scope dispatched after a node is created.
 */
final class AfterNodeCreateScope extends NodeScope {

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return self::AFTER;
  }

}
