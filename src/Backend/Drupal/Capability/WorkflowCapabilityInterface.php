<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Drupal\Capability;

use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;

/**
 * Capability: read the content moderation workflows and move entities through them.
 */
interface WorkflowCapabilityInterface {

  /**
   * Whether a workflow applied to a bundle defines a state.
   *
   * @param string $entity_type
   *   The entity type id.
   * @param string $bundle
   *   The bundle.
   * @param string $state
   *   The state id, such as 'published'.
   *
   * @return bool
   *   TRUE when a workflow covering the bundle defines the state. FALSE when
   *   none does, or when the site has no workflows at all.
   */
  public function hasWorkflowState(string $entity_type, string $bundle, string $state): bool;

  /**
   * Moves an entity to a moderation state and saves it.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   A stub carrying the entity type's id key, or one returned from a
   *   previous create.
   * @param string $state
   *   The state id, such as 'published'.
   *
   * @throws \RuntimeException
   *   When the stub carries no id, the id names no entity, or the entity is
   *   not moderated.
   */
  public function setModerationState(EntityStubInterface $stub, string $state): void;

}
