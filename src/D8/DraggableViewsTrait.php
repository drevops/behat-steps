<?php

namespace DrevOps\BehatSteps\D8;

use Behat\Gherkin\Node\TableNode;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Database\Database;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;

/**
 * Trait DraggableViewsTrait.
 *
 * @note: This is currently limited to nodes only.
 */
trait DraggableViewsTrait {

  /**
   * @Then I save draggable views :view_id view :views_display_id display :bundle items in the following order:
   */
  public function draggableViewsSaveBundleOrder($view_id, $view_display_id, $bundle, TableNode $order_table) {
    $connection = Database::getConnection();

    foreach ($order_table->getColumn(0) as $weight => $title) {
      /** @var \Drupal\node\Entity\Node $node */
      $node = $this->draggableViewsFindNode($bundle, ['title' => $title]);

      if (!$node) {
        throw new \RuntimeException(sprintf('Unable to find node "%s"', $node->getTitle()));
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
   */
  protected function draggableViewsFindNode($type, $conditions) {
    $query = \Drupal::entityQuery('node')
      ->condition('type', $type)
      ->addMetaData('account', User::load(1));

    foreach ($conditions as $k => $v) {
      $and = $query->andConditionGroup();
      $and->condition($k, $v);
      $query->condition($and);
    }

    $nids = $query->execute();

    if (empty($nids)) {
      throw new \Exception(sprintf('Unable to find node that matches conditions: "%s"', print_r($conditions, TRUE)));
    }

    $nid = current($nids);

    return Node::load($nid);
  }

}
