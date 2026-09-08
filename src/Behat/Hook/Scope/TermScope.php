<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Hook\Scope;

/**
 * Scope names for the taxonomy term creation hooks.
 */
abstract class TermScope extends BaseEntityScope {

  public const BEFORE = 'term.create.before';

  public const AFTER = 'term.create.after';

}
