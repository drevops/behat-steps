<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Drupal;

use Behat\Step\Given;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Database\Database;

/**
 * Invalidate specific Drupal caches from within a scenario.
 *
 * Provides targeted cache-clearing steps for single paths, path patterns, and
 * the render cache. A full cache clear is intentionally out of scope because
 * `DrupalContext::@Given the cache has been cleared` already covers it.
 */
trait CacheTrait {

  /**
   * Clear the page cache for a single path.
   *
   * Invalidates the `url:<path>` and `http_response` cache tags, which causes
   * the internal page cache to refresh the next time the path is requested.
   *
   * @code
   * Given the page cache for the path "/about" has been cleared
   * @endcode
   */
  #[Given('the page cache for the path :path has been cleared')]
  public function cacheClearPagePath(string $path): void {
    if ($path === '') {
      throw new \InvalidArgumentException('The path must not be empty.');
    }

    if (!str_starts_with($path, '/')) {
      throw new \InvalidArgumentException(sprintf('The path "%s" must start with a leading slash.', $path));
    }

    Cache::invalidateTags(['http_response', 'url:' . $path]);
  }

  /**
   * Clear the page cache for all paths matching a glob-style pattern.
   *
   * The pattern uses `*` as a wildcard. All other SQL `LIKE` metacharacters
   * (`%`, `_`, `\`) are escaped so they are treated literally.
   *
   * @code
   * Given the page cache for the paths matching "/news*" has been cleared
   * @endcode
   */
  #[Given('the page cache for the paths matching :path_pattern has been cleared')]
  public function cacheClearPagePathWildcard(string $path_pattern): void {
    if ($path_pattern === '') {
      throw new \InvalidArgumentException('The path pattern must not be empty.');
    }

    if (!str_starts_with($path_pattern, '/')) {
      throw new \InvalidArgumentException(sprintf('The path pattern "%s" must start with a leading slash.', $path_pattern));
    }

    $bin = $this->cacheGetPageCacheBin();
    $table = 'cache_' . $bin;

    $database = Database::getConnection();
    if (!$database->schema()->tableExists($table)) {
      throw new \RuntimeException(sprintf('The page cache table "%s" does not exist. Ensure the "%s" cache bin is configured.', $table, $bin));
    }

    // Escape SQL LIKE metacharacters that we do not want to treat as
    // wildcards, then convert the glob `*` to the SQL `%` wildcard.
    $like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $path_pattern);
    $like = str_replace('*', '%', $like);

    $database->delete($table)
      ->condition('cid', '%' . $like . '%', 'LIKE')
      ->execute();
  }

  /**
   * Clear the render cache.
   *
   * @code
   * Given the render cache has been cleared
   * @endcode
   */
  #[Given('the render cache has been cleared')]
  public function cacheClearRender(): void {
    \Drupal::cache('render')->deleteAll();
  }

  /**
   * Get the cache bin used for the page cache.
   *
   * Override in your `FeatureContext` if the site uses a custom internal page
   * cache bin name.
   */
  protected function cacheGetPageCacheBin(): string {
    return 'page';
  }

}
