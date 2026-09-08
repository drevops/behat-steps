<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Hook\Call;

use Behat\Testwork\Hook\Call\RuntimeFilterableHook;
use Behat\Testwork\Hook\Scope\HookScope;

/**
 * Base class for the entity creation hook calls.
 */
abstract class EntityHook extends RuntimeFilterableHook {

  /**
   * {@inheritdoc}
   *
   * An entity creation scope carries no tags to filter on, so a hook declared
   * with a filter string matches nothing.
   */
  public function filterMatches(HookScope $scope): bool {
    return $this->getFilterString() === NULL;
  }

}
