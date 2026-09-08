<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Hook\Scope;

use Behat\Behat\Context\Context;
use Behat\Testwork\Hook\Scope\HookScope;
use DrevOps\BehatSteps\Driver\Entity\EntityStubInterface;

/**
 * Contract for the scopes dispatched around entity creation.
 */
interface EntityScopeInterface extends HookScope {

  public const BEFORE = 'entity.create.before';

  public const AFTER = 'entity.create.after';

  /**
   * Returns the context that started the creation.
   */
  public function getContext(): Context;

  /**
   * Returns the entity stub flowing through the create hooks.
   */
  public function getStub(): EntityStubInterface;

}
