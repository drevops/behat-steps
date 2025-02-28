<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Drupal\node\Entity\Node;

/**
 * Trait SearchApiTrait.
 *
 * Search API-related steps.
 *
 * @package DrevOps\BehatSteps
 *
 * @SuppressWarnings(PHPMD.UnusedLocalVariable)
 */
trait SearchApiTrait {

  use ContentTrait;

  /**
   * Index a node with all Search API indices.
   *
   * @When I index :type :title for search
   */
  public function searchApiIndexContent(string $type, string $title): void {
    $nids = $this->contentLoadMultiple($type, [
      'title' => $title,
    ]);

    if (empty($nids)) {
      throw new \RuntimeException(sprintf('Unable to find %s page "%s"', $type, $title));
    }

    ksort($nids);
    $nid = end($nids);
    $node = Node::load($nid);

    search_api_entity_insert($node);

    $this->searchApiDoIndex(1);
  }

  /**
   * Index a number of items across all active Search API indices.
   *
   * @When I index :limit Search API items
   * @When I index 1 Search API item
   */
  public function searchApiDoIndex(string|int $limit = 1): void {
    $limit = intval($limit);
    $index_storage = \Drupal::entityTypeManager()->getStorage('search_api_index');
    /** @var \Drupal\search_api\IndexInterface[] $indexes */
    $indexes = $index_storage->loadByProperties(['status' => TRUE]);
    if (!$indexes) {
      return;
    }

    foreach ($indexes as $index) {
      $index->indexItems($limit);
    }
  }

}
