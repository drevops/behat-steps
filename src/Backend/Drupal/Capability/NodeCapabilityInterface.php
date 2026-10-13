<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Drupal\Capability;

use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;

/**
 * Capability: create and delete nodes.
 */
interface NodeCapabilityInterface {

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

}
