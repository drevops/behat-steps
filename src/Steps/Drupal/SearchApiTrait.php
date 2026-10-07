<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Step\When;
use DrevOps\BehatSteps\Backend\Capability\CoreCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\ModuleCapabilityInterface;
use DrevOps\BehatSteps\Behat\Prerequisite\Prerequisite;
use DrevOps\BehatSteps\Helper\Drupal\QueryTrait;
use DrevOps\BehatSteps\Helper\Web\StringTrait;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Run Drupal Search API indexing and cron hooks.
 *
 * - Add content to an index.
 * - Run indexing for a specific number of items.
 * - Run the Search API and Search API Solr cron hooks.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
 */
trait SearchApiTrait {

  use QueryTrait;
  use StringTrait;

  /**
   * Index a node of a specific content type with a specific title.
   *
   * @code
   * When I add the "article" content with the title "Test Article" to the search index
   * @endcode
   */
  #[When('I add the :content_type content with the title :title to the search index')]
  public function searchApiIndexContent(string $content_type, string $title): void {
    $this->backendFor(CoreCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    $node = $this->searchApiFindNodeByTitle($content_type, $title);

    if ($node === NULL) {
      throw new \RuntimeException(sprintf('Unable to find "%s" page "%s".', $content_type, $title));
    }

    $this->searchApiIndexNode($node);
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
  public function searchApiRunIndexing(string $count): void {
    $this->backendFor(CoreCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    $this->searchApiIndexItems($this->stringParseInteger($count, 'count', 0));
  }

  /**
   * Run the Search API module cron hook.
   *
   * @code
   * When I run the Search API cron
   * @endcode
   */
  #[When('I run the Search API cron')]
  public function searchApiRunCron(): void {
    $this->backendFor(CoreCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    \Drupal::moduleHandler()->invoke('search_api', 'cron');
  }

  /**
   * Run the Search API Solr module cron hook.
   *
   * The step is a no-op when `search_api_solr` is not enabled, but still
   * requires `search_api` to be enabled.
   *
   * @code
   * When I run the Search API Solr cron
   * @endcode
   */
  #[When('I run the Search API Solr cron')]
  public function searchApiRunSolrCron(): void {
    $this->backendFor(CoreCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    // @codeCoverageIgnoreStart
    if (!$this->anyBackendFor(ModuleCapabilityInterface::class)->moduleIsEnabled('search_api_solr')) {
      return;
    }

    \Drupal::moduleHandler()->invoke('search_api_solr', 'cron');
    // @codeCoverageIgnoreEnd
  }

  /**
   * Find a node of a content type by title.
   *
   * @param string $content_type
   *   The content type.
   * @param string $title
   *   The node title.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The node with the highest revision ID among those with the title, or
   *   NULL when none has it.
   */
  public function searchApiFindNodeByTitle(string $content_type, string $title): ?NodeInterface {
    $nids = $this->queryNodeIds($content_type, [
      'title' => $title,
    ]);

    if (empty($nids)) {
      return NULL;
    }

    ksort($nids);

    return Node::load(end($nids));
  }

  /**
   * Track a node in the search indexes, then index 1 item on each.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   */
  public function searchApiIndexNode(NodeInterface $node): void {
    search_api_entity_insert($node);

    $this->searchApiIndexItems(1);
  }

  /**
   * Index items on every active search index.
   *
   * @param int $count
   *   The most items to index on each index.
   *
   * @throws \RuntimeException
   *   When no search index is active.
   */
  public function searchApiIndexItems(int $count): void {
    $index_storage = \Drupal::entityTypeManager()->getStorage('search_api_index');

    /** @var \Drupal\search_api\IndexInterface[] $indexes */
    $indexes = $index_storage->loadByProperties(['status' => TRUE]);

    // @codeCoverageIgnoreStart
    if (empty($indexes)) {
      throw new \RuntimeException('No active search indexes found.');
    }
    // @codeCoverageIgnoreEnd
    foreach ($indexes as $index) {
      $index->indexItems($count);
    }
  }

  /**
   * Declares the prerequisites this trait asserts.
   *
   * @return array<int, \DrevOps\BehatSteps\Behat\Prerequisite\Prerequisite>
   *   The prerequisites this trait declares.
   */
  protected function searchApiPrerequisites(): array {
    return [
      Prerequisite::capability(CoreCapabilityInterface::class),
      Prerequisite::check(static fn(ModuleCapabilityInterface $backend): bool => $backend->moduleIsEnabled('search_api'), 'the "search_api" module from the "drupal/search_api" package is enabled'),
    ];
  }

}
