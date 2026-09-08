<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Hook\Scope;

/**
 * Scope names for the node creation hooks.
 */
abstract class NodeScope extends BaseEntityScope {

  public const BEFORE = 'node.create.before';

  public const AFTER = 'node.create.after';

}
