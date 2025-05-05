<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Drupal;

use Drupal\node\Entity\Node;

/**
 * Assert Drupal Search API with index and query operations.
 *
 * - Add content to an index
 * - Run indexing for a specific number of items.
 */
trait SearchApiTrait {

  use ContentTrait;

  /**
   * Index a node of a specific content type with a specific title.
   *
   * @code
   * When I add the "article" content with the title "Test Article" to the search index
   * @endcode
   *
   * @When I add the :content_type content with the title :title to the search index
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
   * Run indexing for a specific number of items.
   *
   * @code
   * When I run search indexing for 5 items
   * When I run search indexing for 1 item
   * @endcode
   *
   * @When I run search indexing for :count item(s)
   */
  public function searchApiDoIndex(string|int $limit): void {
    $limit = intval($limit);

    $index_storage = \Drupal::entityTypeManager()->getStorage('search_api_index');

    /** @var \Drupal\search_api\IndexInterface[] $indexes */
    $indexes = $index_storage->loadByProperties(['status' => TRUE]);

    if (empty($indexes)) {
      throw new \RuntimeException('No active search indexes found');
    }

    foreach ($indexes as $index) {
      $index->indexItems($limit);
    }
  }

}
