<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Drupal\Capability;

/**
 * Capability: index 1 entity and run a search module's cron hook.
 *
 * Both reach into the Search API module in the site's process, which a
 * backend that indexes waiting items by count may not be able to do, so they
 * sit apart from 'SearchIndexCapabilityInterface'.
 */
interface SearchApiCapabilityInterface {

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
   * Runs the cron hook of a search module.
   *
   * @param string $module
   *   The module whose cron hook runs.
   */
  public function searchRunCron(string $module = 'search_api'): void;

}
