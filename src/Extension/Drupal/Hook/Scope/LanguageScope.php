<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Extension\Drupal\Hook\Scope;

use DrevOps\BehatSteps\Extension\Hook\Scope\BaseEntityScope;

/**
 * Scope names for the language creation hooks.
 */
abstract class LanguageScope extends BaseEntityScope {

  public const string BEFORE = 'language.create.before';

  public const string AFTER = 'language.create.after';

}
