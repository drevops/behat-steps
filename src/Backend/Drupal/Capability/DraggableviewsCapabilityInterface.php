<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Drupal\Capability;

/**
 * Capability: save the order of a Draggable Views display.
 */
interface DraggableviewsCapabilityInterface {

  /**
   * Saves the order of the entities a Draggable Views display lists.
   *
   * The entities are given the weights the keys hold. An entity the display
   * already ordered takes its new weight.
   *
   * @param string $view_id
   *   The view id.
   * @param string $display_id
   *   The view display id.
   * @param array<int, int|string> $entity_ids
   *   The entity ids, keyed by weight.
   */
  public function draggableviewsSetOrder(string $view_id, string $display_id, array $entity_ids): void;

}
