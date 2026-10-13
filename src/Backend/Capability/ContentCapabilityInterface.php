<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Capability;

use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;

/**
 * Capability: create and delete entities of any type.
 */
interface ContentCapabilityInterface {

  /**
   * Creates an entity of any type.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The entity stub. Its typed entity type (via 'getEntityType()') selects
   *   the storage, and its typed bundle (via 'getBundle()') selects the
   *   bundle. The values bag carries base properties and field values.
   *
   * @return \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface
   *   The same stub, now flagged as saved with the created entity attached.
   */
  public function createEntity(EntityStubInterface $stub): EntityStubInterface;

  /**
   * Deletes an entity of any type.
   *
   * Does nothing when the entity does not exist.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The stub returned from a previous 'createEntity()' call, or one that
   *   carries the entity type's id key resolving to an existing entity.
   */
  public function deleteEntity(EntityStubInterface $stub): void;

}
