<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Capability;

use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;

/**
 * Capability: create, update, read and delete entities of any type.
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
   * Updates an entity of any type.
   *
   * Every value in the bag goes through the field handlers, as on create,
   * and replaces the field of the same name. The id key and the bundle key
   * identify the entity, so neither is written.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   A stub carrying the entity type's id key, or one returned from a
   *   previous 'createEntity()' call. Its typed bundle may be NULL, because
   *   the values expand against the bundle of the stored entity.
   *
   * @return \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface
   *   The same stub, now holding the expanded values and flagged as saved
   *   with the updated entity attached.
   *
   * @throws \RuntimeException
   *   When the stub carries no id, the id names no entity, or a value names a
   *   field the entity does not have.
   */
  public function updateEntity(EntityStubInterface $stub): EntityStubInterface;

  /**
   * Returns field values of an entity of any type.
   *
   * @param string $entity_type
   *   The entity type id.
   * @param int|string $id
   *   The entity id.
   * @param array<int, string> $fields
   *   The field names to read.
   *
   * @return array<string, array<int, array<string, mixed>>>
   *   The values keyed by field name, each in its storage shape: a list of
   *   items keyed by property. Empty when the entity does not exist.
   *
   * @throws \RuntimeException
   *   When the entity type has no fields, or the entity has no field of a
   *   requested name.
   */
  public function getEntityValues(string $entity_type, int|string $id, array $fields): array;

  /**
   * Returns the bundle of an entity.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The stub returned from a previous 'createEntity()' call, or one that
   *   carries the entity type's id key resolving to an existing entity.
   *
   * @return string
   *   The bundle, or the entity type id for an entity type without bundles.
   *
   * @throws \RuntimeException
   *   When the stub carries no id, or the id names no entity.
   */
  public function getEntityBundle(EntityStubInterface $stub): string;

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

  /**
   * Returns the URL of an entity's page.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The stub returned from a previous 'createEntity()' call, or one that
   *   carries the entity type's id key resolving to an existing entity.
   * @param string $rel
   *   The link relationship, such as 'canonical' or 'edit-form'.
   *
   * @return string
   *   The URL, as a path relative to the site root.
   *
   * @throws \RuntimeException
   *   When the entity does not exist, or has no link of the relationship.
   */
  public function getEntityUrl(EntityStubInterface $stub, string $rel = 'canonical'): string;

}
