<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Driver\Capability;

use DrevOps\BehatSteps\Driver\Entity\EntityStubInterface;

/**
 * Capability: create and delete content (nodes, terms, generic entities).
 */
interface ContentCapabilityInterface {

  /**
   * Creates a node.
   *
   * @param \DrevOps\BehatSteps\Driver\Entity\EntityStubInterface $stub
   *   The node stub. The bundle property selects the node type.
   *
   * @return \DrevOps\BehatSteps\Driver\Entity\EntityStubInterface
   *   The same stub, now flagged as saved with the created node attached.
   */
  public function nodeCreate(EntityStubInterface $stub): EntityStubInterface;

  /**
   * Deletes a node.
   *
   * @param \DrevOps\BehatSteps\Driver\Entity\EntityStubInterface $stub
   *   The stub returned from a previous 'nodeCreate()' call, or one that
   *   carries a 'nid' value resolving to an existing node.
   */
  public function nodeDelete(EntityStubInterface $stub): void;

  /**
   * Creates a taxonomy term.
   *
   * @param \DrevOps\BehatSteps\Driver\Entity\EntityStubInterface $stub
   *   The term stub. The bundle property selects the vocabulary.
   *
   * @return \DrevOps\BehatSteps\Driver\Entity\EntityStubInterface
   *   The same stub, now flagged as saved with the created term attached.
   */
  public function termCreate(EntityStubInterface $stub): EntityStubInterface;

  /**
   * Deletes a taxonomy term.
   *
   * @param \DrevOps\BehatSteps\Driver\Entity\EntityStubInterface $stub
   *   The stub returned from a previous 'termCreate()' call, or one that
   *   carries a 'tid' value resolving to an existing term.
   *
   * @return bool
   *   TRUE when the term was deleted.
   */
  public function termDelete(EntityStubInterface $stub): bool;

  /**
   * Creates an entity of any type.
   *
   * @param \DrevOps\BehatSteps\Driver\Entity\EntityStubInterface $stub
   *   The entity stub. Its typed entity type (via 'getEntityType()') selects
   *   the storage, and its typed bundle (via 'getBundle()') selects the
   *   bundle. The values bag carries base properties and field values.
   *
   * @return \DrevOps\BehatSteps\Driver\Entity\EntityStubInterface
   *   The same stub, now flagged as saved with the created entity attached.
   */
  public function entityCreate(EntityStubInterface $stub): EntityStubInterface;

  /**
   * Deletes an entity of any type.
   *
   * @param \DrevOps\BehatSteps\Driver\Entity\EntityStubInterface $stub
   *   The stub returned from a previous 'entityCreate()' call, or one that
   *   carries the entity type's id key resolving to an existing entity.
   */
  public function entityDelete(EntityStubInterface $stub): void;

}
