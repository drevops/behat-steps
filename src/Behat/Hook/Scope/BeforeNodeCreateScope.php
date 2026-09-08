<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Hook\Scope;

/**
 * Scope dispatched before a node is created.
 */
final class BeforeNodeCreateScope extends NodeScope {

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return self::BEFORE;
  }

}
