<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Drupal\Capability;

/**
 * Capability: index content on the site's search indexes.
 */
interface SearchIndexCapabilityInterface {

  /**
   * Tracks an entity in the search indexes and indexes it on each.
   *
   * Only the entity's own items are indexed, so other items waiting in an
   * index stay queued. An index that is disabled or does not include the
   * entity is skipped.
   *
   * @param string $entity_type
   *   The entity type id.
   * @param int|string $id
   *   The entity id.
   *
   * @return bool
   *   TRUE when at least 1 active index includes the entity.
   *
   * @throws \RuntimeException
   *   When the entity does not exist.
   */
  public function searchIndexEntity(string $entity_type, int|string $id): bool;

  /**
   * Indexes the items waiting on every active search index.
   *
   * @param int $limit
   *   The most items to index on each index. 0 indexes nothing.
   *
   * @throws \RuntimeException
   *   When no search index is active.
   */
  public function searchIndexItems(int $limit): void;

  /**
   * Runs the cron hook of a search module.
   *
   * @param string $module
   *   The module whose cron hook runs.
   */
  public function searchRunCron(string $module = 'search_api'): void;

}
