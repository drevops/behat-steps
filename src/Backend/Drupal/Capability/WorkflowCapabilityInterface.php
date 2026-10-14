<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Drupal\Capability;

/**
 * Capability: read the content moderation workflows.
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

}
