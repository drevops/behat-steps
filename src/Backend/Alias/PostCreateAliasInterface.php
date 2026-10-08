<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Alias;

use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;

/**
 * A creation alias that acts on the entity after it has been saved.
 *
 * This lifecycle is for side-effects that require the entity to exist
 * first. Examples are assigning roles to a saved user, or attaching
 * references to an entity that needs an id before it can be linked.
 */
interface PostCreateAliasInterface extends CreationAliasInterface {

  /**
   * Reads the alias value from the stub and applies it to the entity.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The stub used to create the entity. Must already carry a value under
   *   'getName()'.
   * @param object $entity
   *   The Drupal entity that was just persisted.
   *
   * @throws \DrevOps\BehatSteps\Backend\Exception\CreationAliasResolutionException
   *   When the value cannot be applied.
   */
  public function applyAfterCreate(EntityStubInterface $stub, object $entity): void;

}
