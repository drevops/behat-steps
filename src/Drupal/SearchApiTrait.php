<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Drupal;

use Behat\Step\When;
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
   */
  #[When('I add the :content_type content with the title :title to the search index')]
  public function searchApiIndexContent(string $type, string $title): void {
    $nids = $this->contentLoadMultiple($type, [
      'title' => $title,
    ]);

    if (empty($nids)) {
      throw new \RuntimeException(sprintf('Unable to find "%s" page "%s".', $type, $title));
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
   */
  #[When('I run search indexing for :count item(s)')]
  public function searchApiDoIndex(string|int $limit): void {
    $limit = intval($limit);

    $index_storage = \Drupal::entityTypeManager()->getStorage('search_api_index');

    /** @var \Drupal\search_api\IndexInterface[] $indexes */
    $indexes = $index_storage->loadByProperties(['status' => TRUE]);

    // @codeCoverageIgnoreStart
    if (empty($indexes)) {
      throw new \RuntimeException('No active search indexes found.');
    }
    // @codeCoverageIgnoreEnd
    foreach ($indexes as $index) {
      $index->indexItems($limit);
    }
  }

  /**
   * Run the Search API module cron hook.
   *
   * Triggers the `search_api` module's `hook_cron` implementation, which
   * processes the tracker and indexes pending items as a real cron job would.
   *
   * @code
   * When I run the Search API cron
   * @endcode
   */
  #[When('I run the Search API cron')]
  public function searchApiRunCron(): void {
    if (!\Drupal::moduleHandler()->moduleExists('search_api')) {
      throw new \RuntimeException('The "search_api" module is not enabled.');
    }

    \Drupal::moduleHandler()->invoke('search_api', 'cron');
  }

  /**
   * Run the Search API Solr module cron hook.
   *
   * Triggers the `search_api_solr` module's `hook_cron` implementation. This
   * is a no-op when the `search_api_solr` module is not enabled, but requires
   * the parent `search_api` module to be enabled.
   *
   * @code
   * When I run the Search API Solr cron
   * @endcode
   */
  #[When('I run the Search API Solr cron')]
  public function searchApiRunSolrCron(): void {
    $module_handler = \Drupal::moduleHandler();

    if (!$module_handler->moduleExists('search_api')) {
      throw new \RuntimeException('The "search_api" module is not enabled.');
    }

    // @codeCoverageIgnoreStart
    if (!$module_handler->moduleExists('search_api_solr')) {
      return;
    }

    $module_handler->invoke('search_api_solr', 'cron');
    // @codeCoverageIgnoreEnd
  }

}
