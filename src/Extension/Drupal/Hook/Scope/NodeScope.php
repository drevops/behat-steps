<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Extension\Drupal\Hook\Scope;

use DrevOps\BehatSteps\Extension\Hook\Scope\BaseEntityScope;

/**
 * Scope names for the node creation hooks.
 */
abstract class NodeScope extends BaseEntityScope {

  public const string BEFORE = 'node.create.before';

  public const string AFTER = 'node.create.after';

}
