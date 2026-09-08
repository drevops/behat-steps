<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Hook\Scope;

/**
 * Scope names for the user creation hooks.
 */
abstract class UserScope extends BaseEntityScope {

  public const BEFORE = 'user.create.before';

  public const AFTER = 'user.create.after';

}
