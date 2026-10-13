<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Extension\Hook\Attribute;

/**
 * Marks the entity creation hook attributes.
 *
 * An attribute takes no argument, because a hook runs for every entity
 * created in its scope.
 */
interface HookAttributeInterface {

  /**
   * Returns the hook call class the attribute registers.
   *
   * The reader builds a call only from a subclass of 'EntityHook' and skips
   * any other class.
   *
   * @return class-string
   *   The call class.
   */
  public function getCallClass(): string;

}
