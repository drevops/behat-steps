<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Drupal\Capability;

/**
 * Capability: index the items waiting on the site's search indexes.
 */
interface SearchIndexCapabilityInterface {

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

}
