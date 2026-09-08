<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Hook\Scope;

/**
 * Scope names for the language creation hooks.
 */
abstract class LanguageScope extends BaseEntityScope {

  public const BEFORE = 'language.create.before';

  public const AFTER = 'language.create.after';

}
