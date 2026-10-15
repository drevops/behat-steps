<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Capability;

/**
 * Capability: clear caches, bins, tags and page cache entries.
 */
interface CacheCapabilityInterface {

  /**
   * Clears every cache of the site.
   */
  public function cacheClear(): void;

  /**
   * Clears every cache held in the backend's own process.
   */
  public function cacheClearStatic(): void;

  /**
   * Empties a cache bin.
   *
   * @param string $bin
   *   The bin name, such as 'render' or 'page'.
   *
   * @throws \RuntimeException
   *   When the bin does not exist.
   */
  public function cacheClearBin(string $bin): void;

  /**
   * Invalidates cache tags.
   *
   * @param array<int, string> $tags
   *   The cache tags, such as 'node_list'.
   */
  public function cacheInvalidateTags(array $tags): void;

  /**
   * Deletes the internal page cache entries stored for a path.
   *
   * The path is compared with the whole path of each cached URL, on any host,
   * with any query string and in any request format. Only a bin on the
   * database backend can list its entries, so a bin on any other backend is
   * emptied whole, as is a bin a backend reaches from outside the site's
   * process.
   *
   * @param string $bin
   *   The bin holding the page cache, 'page' on a site with core's defaults.
   * @param string $path
   *   The path, starting with '/'.
   * @param bool $is_pattern
   *   Whether '*' in the path matches any run of characters, including '/'.
   *
   * @throws \RuntimeException
   *   When the bin does not exist.
   */
  public function cacheDeletePagePath(string $bin, string $path, bool $is_pattern = FALSE): void;

}
