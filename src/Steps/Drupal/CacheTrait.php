<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Step\Given;
use Behat\Step\When;
use DrevOps\BehatSteps\Driver\Capability\CacheCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\CronCapabilityInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Database\Database;

/**
 * Invalidate Drupal caches and run cron from within a scenario.
 *
 * - Clear every cache bin, or target a single path, a path pattern, or the
 *   render cache.
 * - Run cron, which also flushes the caches cron itself invalidates.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\RawContext
 */
trait CacheTrait {

  /**
   * Clear every cache bin.
   *
   * @code
   * Given the cache is empty
   * @endcode
   */
  #[Given('the cache is empty')]
  public function cacheClearAll(): void {
    $driver = $this->getDriver();

    if (!$driver instanceof CacheCapabilityInterface) {
      throw new \RuntimeException(sprintf('The active Drupal driver "%s" does not support cache clearing.', $driver::class));
    }

    $driver->cacheClear();
  }

  /**
   * Clear the page cache for a single path.
   *
   * Invalidates the `url:<path>` and `http_response` cache tags, which causes
   * the internal page cache to refresh the next time the path is requested.
   *
   * @code
   * Given the page cache for the path "/about" is empty
   * @endcode
   */
  #[Given('the page cache for the path :path is empty')]
  public function cacheClearPagePath(string $path): void {
    $this->drupal();

    if ($path === '') {
      throw new \RuntimeException('The path must not be empty.');
    }

    if (!str_starts_with($path, '/')) {
      throw new \RuntimeException(sprintf('The path "%s" must start with a leading slash.', $path));
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
   * Given the page cache for the paths matching "/news*" is empty
   * @endcode
   */
  #[Given('the page cache for the paths matching :path_pattern is empty')]
  public function cacheClearPagePathWildcard(string $path_pattern): void {
    $this->drupal();

    if ($path_pattern === '') {
      throw new \RuntimeException('The path pattern must not be empty.');
    }

    if (!str_starts_with($path_pattern, '/')) {
      throw new \RuntimeException(sprintf('The path pattern "%s" must start with a leading slash.', $path_pattern));
    }

    $bin = $this->cacheGetPageCacheBin();
    $table = 'cache_' . $bin;

    $database = Database::getConnection();
    if (!$database->schema()->tableExists($table)) {
      throw new \RuntimeException(sprintf('The page cache table "%s" does not exist. Ensure the "%s" cache bin is configured.', $table, $bin));
    }

    // Escape SQL LIKE metacharacters so they match literally, then convert
    // the glob `*` to the SQL `%` wildcard.
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
   * Given the render cache is empty
   * @endcode
   */
  #[Given('the render cache is empty')]
  public function cacheClearRender(): void {
    $this->drupal();

    \Drupal::cache('render')->deleteAll();
  }

  /**
   * Run cron.
   *
   * @code
   * When I run cron
   * @endcode
   */
  #[When('I run cron')]
  public function cacheRunCron(): void {
    $driver = $this->getDriver();

    if (!$driver instanceof CronCapabilityInterface) {
      throw new \RuntimeException(sprintf('The active Drupal driver "%s" does not support running cron.', $driver::class));
    }

    $driver->cronRun();
  }

  /**
   * Get the cache bin used for the page cache.
   *
   * Override in the consuming `FeatureContext` if the site uses a custom
   * internal page cache bin name.
   */
  protected function cacheGetPageCacheBin(): string {
    return 'page';
  }

}
