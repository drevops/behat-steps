<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Hook\Scope;

/**
 * Scope names for the language creation hooks.
 */
abstract class LanguageScope extends BaseEntityScope {

  public const string BEFORE = 'language.create.before';

  public const string AFTER = 'language.create.after';

}
