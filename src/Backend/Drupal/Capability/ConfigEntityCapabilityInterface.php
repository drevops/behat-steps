<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Drupal\Capability;

use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;

/**
 * Capability: create, update, copy and delete config entities.
 *
 * Every method goes through the entity storage, so the entity type's hooks
 * run, as they would not for a write to the config object.
 */
interface ConfigEntityCapabilityInterface {

  /**
   * Creates a config entity.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The config entity stub. Its typed entity type selects the storage, and
   *   the values bag carries the entity's properties, its id included.
   *
   * @return \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface
   *   The same stub, now flagged as saved with the created entity attached.
   *
   * @throws \RuntimeException
   *   When the entity type is not a config entity type.
   */
  public function createConfigEntity(EntityStubInterface $stub): EntityStubInterface;

  /**
   * Updates a config entity.
   *
   * The entity is loaded without overrides, so a value a module or
   * 'settings.php' overrides is not written into the stored configuration.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   A stub carrying the entity type's id key. Every other value replaces
   *   the property of the same name.
   *
   * @return \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface
   *   The same stub, now flagged as saved with the updated entity attached.
   *
   * @throws \RuntimeException
   *   When the entity type is not a config entity type, the stub carries no
   *   id, or the id names no entity.
   */
  public function updateConfigEntity(EntityStubInterface $stub): EntityStubInterface;

  /**
   * Deletes a config entity.
   *
   * Does nothing when the entity does not exist.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The stub returned from a previous 'createConfigEntity()' call, or one
   *   that carries the entity type's id key.
   *
   * @throws \RuntimeException
   *   When the entity type is not a config entity type, or the stub carries
   *   no id.
   */
  public function deleteConfigEntity(EntityStubInterface $stub): void;

  /**
   * Creates a config entity as a copy of another.
   *
   * The source is loaded without overrides, as on update. A copy takes no id
   * from its source, so the overrides carry the new id.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $source
   *   A stub carrying the id key of the config entity to copy.
   * @param array<string, mixed> $overrides
   *   Properties that replace the copied ones, the new id under the entity
   *   type's id key included.
   *
   * @return \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface
   *   A stub of the copy carrying the overrides, flagged as saved with the
   *   copy attached.
   *
   * @throws \RuntimeException
   *   When the entity type is not a config entity type, the source carries no
   *   id or does not exist, or the overrides carry no new id.
   */
  public function duplicateConfigEntity(EntityStubInterface $source, array $overrides): EntityStubInterface;

}
