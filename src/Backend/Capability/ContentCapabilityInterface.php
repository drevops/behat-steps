<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Capability;

use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;

/**
 * Capability: create and delete content (nodes, terms, generic entities).
 */
interface ContentCapabilityInterface {

  /**
   * Creates a node.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The node stub. The bundle property selects the node type.
   *
   * @return \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface
   *   The same stub, now flagged as saved with the created node attached.
   */
  public function createNode(EntityStubInterface $stub): EntityStubInterface;

  /**
   * Deletes a node.
   *
   * Does nothing when the node does not exist.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The stub returned from a previous 'createNode()' call, or one that
   *   carries a 'nid' value resolving to an existing node.
   */
  public function deleteNode(EntityStubInterface $stub): void;

  /**
   * Creates a taxonomy term.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The term stub. The bundle property selects the vocabulary.
   *
   * @return \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface
   *   The same stub, now flagged as saved with the created term attached.
   */
  public function createTerm(EntityStubInterface $stub): EntityStubInterface;

  /**
   * Deletes a taxonomy term.
   *
   * Does nothing when the term does not exist.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The stub returned from a previous 'createTerm()' call, or one that
   *   carries a 'tid' value resolving to an existing term.
   */
  public function deleteTerm(EntityStubInterface $stub): void;

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
