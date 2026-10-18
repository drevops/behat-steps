<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Step\When;
use DrevOps\BehatSteps\Backend\Capability\ContentCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\ModuleCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\SearchApiCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\SearchIndexCapabilityInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;
use DrevOps\BehatSteps\Extension\Prerequisite\Prerequisite;
use DrevOps\BehatSteps\Steps\Helper\Drupal\QueryTrait;
use DrevOps\BehatSteps\Steps\Helper\Web\StringTrait;

/**
 * Run Drupal Search API indexing and cron hooks.
 *
 * - Add content to an index.
 * - Run indexing for a specific number of items.
 * - Run the Search API and Search API Solr cron hooks.
 *
 * @title Search API
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\WebRawContext
 */
trait SearchApiTrait {

  use QueryTrait;
  use StringTrait;

  /**
   * Index a node of a specific content type with a specific title.
   *
   * Only that node is indexed, so other items waiting in an index stay
   * queued. The step fails when no active search index includes the node.
   *
   * @code
   * When the "article" content with the title "Test Article" is indexed
   * @endcode
   *
   * @actor:system
   */
  #[When('the :content_type content with the title :title is indexed')]
  public function searchApiIndexContent(string $content_type, string $title): void {
    $this->backendFor(SearchApiCapabilityInterface::class);

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
   * When search indexing runs for 5 items
   * When search indexing runs for 1 item
   * @endcode
   *
   * @actor:system
   */
  #[When('search indexing runs for :count item(s)')]
  public function searchApiRunIndexing(string $count): void {
    $this->backendFor(SearchIndexCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    $this->searchApiIndexItems($this->stringParseInteger($count, 'count', 0));
  }

  /**
   * Run the Search API module cron hook.
   *
   * @code
   * When the Search API cron runs
   * @endcode
   *
   * @actor:system
   */
  #[When('the Search API cron runs')]
  public function searchApiRunCron(): void {
    $backend = $this->backendFor(SearchApiCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    $backend->searchRunCron();
  }

  /**
   * Run the Search API Solr module cron hook.
   *
   * The step is a no-op when `search_api_solr` is not enabled, but still
   * requires `search_api` to be enabled.
   *
   * @code
   * When the Search API Solr cron runs
   * @endcode
   *
   * @actor:system
   */
  #[When('the Search API Solr cron runs')]
  public function searchApiRunSolrCron(): void {
    $backend = $this->backendFor(SearchApiCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    // @codeCoverageIgnoreStart
    if (!$this->anyBackendFor(ModuleCapabilityInterface::class)->moduleIsEnabled('search_api_solr')) {
      return;
    }

    $backend->searchRunCron('search_api_solr');
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
   * @return \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface|null
   *   The stub of the newest node among those with the title, carrying its
   *   'nid', or NULL when none has it.
   */
  public function searchApiFindNodeByTitle(string $content_type, string $title): ?EntityStubInterface {
    $nid = $this->queryFindNewestEntityId('node', ['title' => $title], $content_type);

    return $nid === NULL ? NULL : new EntityStub('node', $content_type, ['nid' => $nid]);
  }

  /**
   * Track a node in the search indexes, then index it on each.
   *
   * Only the node's own items are indexed, so other items waiting in an index
   * stay queued. An index that does not include the node is skipped.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $node
   *   The node stub, carrying its 'nid'.
   *
   * @throws \RuntimeException
   *   When the node does not exist, or no active search index includes it.
   */
  public function searchApiIndexNode(EntityStubInterface $node): void {
    $nid = $this->queryGetEntityId($node, 'nid');

    if ($this->backendFor(SearchApiCapabilityInterface::class)->searchIndexEntity('node', $nid)) {
      return;
    }

    $values = $this->backendFor(ContentCapabilityInterface::class)->getEntityValues('node', $nid, ['type', 'title']);

    throw new \RuntimeException(sprintf('No active search index includes the "%s" content with the title "%s".', $values['type'][0]['target_id'] ?? '', $values['title'][0]['value'] ?? ''));
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
    $this->backendFor(SearchIndexCapabilityInterface::class)->searchIndexItems($count);
  }

  /**
   * Declares the prerequisites this trait asserts.
   *
   * @return array<int, \DrevOps\BehatSteps\Extension\Prerequisite\Prerequisite>
   *   The prerequisites this trait declares.
   */
  protected function searchApiPrerequisites(): array {
    return [
      Prerequisite::capability(SearchIndexCapabilityInterface::class),
      Prerequisite::check(static fn(ModuleCapabilityInterface $backend): bool => $backend->moduleIsEnabled('search_api'), 'the "search_api" module from the "drupal/search_api" package is enabled'),
    ];
  }

}
