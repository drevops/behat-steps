<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Drupal;

use Behat\Gherkin\Node\TableNode;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Database\Database;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Order items in the Drupal Draggable Views.
 */
trait DraggableviewsTrait {

  /**
   * Save order of the Draggable Order items.
   *
   * @code
   * When I save the draggable views items of the view "draggableviews_demo" and the display "page_1" for the "article" content in the following order:
   *   | First Article  |
   *   | Second Article |
   *   | Third Article  |
   * @endcode
   *
   * @When I save the draggable views items of the view :view_id and the display :views_display_id for the :bundle content in the following order:
   */
  public function draggableViewsSaveBundleOrder(string $view_id, string $view_display_id, string $bundle, TableNode $order_table): void {
    $connection = Database::getConnection();

    foreach ($order_table->getColumn(0) as $weight => $title) {
      $node = $this->draggableViewsFindNode($bundle, ['title' => $title]);

      if (empty($node)) {
        throw new \RuntimeException(sprintf('Unable to find the node "%s"', $title));
      }

      $entity_id = $node->id();

      // Here and below: copied from draggableviews_views_submit().
      // Remove old data.
      $connection->delete('draggableviews_structure')
        ->condition('view_name', $view_id)
        ->condition('view_display', $view_display_id)
        ->condition('entity_id', $entity_id)
        ->execute();

      // Add new data.
      $record = [
        'view_name' => $view_id,
        'view_display' => $view_display_id,
        'args' => '[]',
        'entity_id' => $entity_id,
        'weight' => $weight,
      ];

      $connection->insert('draggableviews_structure')->fields($record)->execute();
    }

    // We invalidate the entity list cache, so other views are also aware of the
    // cache.
    $list_cache_tags = \Drupal::entityTypeManager()->getDefinition('node')->getListCacheTags();
    Cache::invalidateTags($list_cache_tags);
  }

  /**
   * Find a node using provided conditions.
   *
   * @param string $type
   *   The node type.
   * @param array<string, string> $conditions
   *   The conditions to search for.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The found node or NULL.
   */
  protected function draggableViewsFindNode(string $type, array $conditions): ?NodeInterface {
    $query = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('type', $type);

    foreach ($conditions as $k => $v) {
      $and = $query->andConditionGroup();
      $and->condition($k, $v);
      $query->condition($and);
    }

    $nids = $query->execute();

    if (empty($nids)) {
      return NULL;
    }

    $nid = current($nids);

    return Node::load($nid);
  }

}
