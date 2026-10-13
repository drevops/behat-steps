<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Extension\Drupal\Hook\Scope;

use DrevOps\BehatSteps\Extension\Hook\Scope\BaseEntityScope;

/**
 * Scope names for the taxonomy term creation hooks.
 */
abstract class TermScope extends BaseEntityScope {

  public const string BEFORE = 'term.create.before';

  public const string AFTER = 'term.create.after';

}
