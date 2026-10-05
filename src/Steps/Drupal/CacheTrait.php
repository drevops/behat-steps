<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Step\Given;
use Behat\Step\When;
use DrevOps\BehatSteps\Backend\Capability\CacheCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\CoreCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\CronCapabilityInterface;
use DrevOps\BehatSteps\Behat\Config\Option;
use DrevOps\BehatSteps\Helper\Drupal\StaticCacheTrait;
use Drupal\Core\Database\Database;

/**
 * Invalidate Drupal caches and run cron from within a scenario.
 *
 * - Clear every cache bin, or target a single path, a path pattern, or the
 *   render cache.
 * - Run cron, which also flushes the caches cron itself invalidates.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
 */
trait CacheTrait {

  use StaticCacheTrait;

  /**
   * Clear every cache bin.
   *
   * @code
   * Given the cache is empty
   * @endcode
   */
  #[Given('the cache is empty')]
  public function cacheClearAll(): void {
    $this->backendFor(CacheCapabilityInterface::class)->cacheClear();
  }

  /**
   * Clear the page cache for a single path.
   *
   * Deletes the internal page cache entries for the path on any host, with
   * any query string and in any request format. Entries for other paths stay
   * cached.
   *
   * @code
   * Given the page cache for the path "/about" is empty
   * @endcode
   */
  #[Given('the page cache for the path :path is empty')]
  public function cacheClearPagePath(string $path): void {
    $this->cacheDeletePagePath($path);
  }

  /**
   * Clear the page cache for all paths matching a glob-style pattern.
   *
   * The pattern matches the whole path, and `*` is its only wildcard. `*`
   * matches any run of characters, including `/`, so "/news*" matches "/news"
   * and "/news/1" but not "/archive/news".
   *
   * Entries are deleted on any host, with any query string and in any request
   * format.
   *
   * @code
   * Given the page cache for the paths matching "/news*" is empty
   * @endcode
   */
  #[Given('the page cache for the paths matching :path_pattern is empty')]
  public function cacheClearPagePathWildcard(string $path_pattern): void {
    $this->cacheDeletePagePath($path_pattern, TRUE);
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
    $this->backendFor(CoreCapabilityInterface::class);

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
    if (!$this->backendFor(CronCapabilityInterface::class)->cronRun()) {
      throw new \RuntimeException('Cron did not run. Another cron run may still hold the lock.');
    }
  }

  /**
   * Get the cache bin used for the page cache.
   */
  public function cacheGetPageCacheBin(): string {
    return $this->getOptionString('cache', 'page_cache_bin');
  }

  /**
   * Delete the internal page cache entries stored for a path.
   *
   * The path is compared with the whole path of each cached URL, on any host,
   * with any query string and in any request format.
   *
   * @param string $path
   *   The path, starting with '/'.
   * @param bool $is_pattern
   *   Whether '*' in the path matches any run of characters, including '/'.
   *
   * @throws \RuntimeException
   *   When the path is empty, has no leading slash, carries a query string or
   *   a fragment, or when the page cache table does not exist.
   */
  public function cacheDeletePagePath(string $path, bool $is_pattern = FALSE): void {
    $this->backendFor(CoreCapabilityInterface::class);

    $noun = $is_pattern ? 'path pattern' : 'path';

    if ($path === '') {
      throw new \RuntimeException(sprintf('The %s must not be empty.', $noun));
    }

    if (!str_starts_with($path, '/')) {
      throw new \RuntimeException(sprintf('The %s "%s" must start with a leading slash.', $noun, $path));
    }

    // A cached URL's path never holds '?' or '#', so such a path matches
    // nothing.
    if (strpbrk($path, '?#') !== FALSE) {
      throw new \RuntimeException(sprintf('The %s "%s" must not contain a query string or a fragment.', $noun, $path));
    }

    $bin = $this->cacheGetPageCacheBin();
    $table = 'cache_' . $bin;
    $database = Database::getConnection();

    if (!$database->schema()->tableExists($table)) {
      throw new \RuntimeException(sprintf('The page cache table "%s" does not exist. Ensure the "%s" cache bin is configured.', $table, $bin));
    }

    $parts = $is_pattern ? explode('*', $path) : [$path];

    // LIKE cannot anchor the path after the host, so it only narrows the
    // candidates.
    $like = implode('%', array_map($database->escapeLike(...), $parts));
    $candidates = $database->select($table, 'c')->fields('c', ['cid'])->condition('cid', '%' . $like . '%', 'LIKE')->execute()->fetchCol();

    $regex = '#^' . implode('.*', array_map(static fn(string $part): string => preg_quote($part, '#'), $parts)) . '$#';
    $cids = array_filter($candidates, fn(string $cid): bool => preg_match($regex, $this->cacheFindPagePath($cid) ?? '') === 1);

    // Chunk the IN list to stay under the database placeholder limit.
    foreach (array_chunk($cids, 1000) as $chunk) {
      $database->delete($table)->condition('cid', $chunk, 'IN')->execute();
    }
  }

  /**
   * Find the path of the URL an internal page cache entry is stored for.
   *
   * @param string $cid
   *   The cache ID: the absolute URL, ':' and the request format.
   *
   * @return string|null
   *   The path, or NULL when the cache ID holds no URL path.
   */
  protected function cacheFindPagePath(string $cid): ?string {
    $url = parse_url($cid);

    if (!is_array($url) || !isset($url['path'])) {
      return NULL;
    }

    // With a query string, parse_url() returns the ':<format>' suffix inside
    // the query.
    if (isset($url['query'])) {
      return $url['path'];
    }

    // The format contains no ':', so the last ':' in the path starts it.
    $separator = strrpos($url['path'], ':');

    return $separator === FALSE ? $url['path'] : substr($url['path'], 0, $separator);
  }

  /**
   * Declares the options this trait reads.
   *
   * @return array<int, \DrevOps\BehatSteps\Behat\Config\Option>
   *   The options this trait declares.
   */
  protected function cacheConfigSchema(): array {
    return [
      new Option('page_cache_bin', default: 'page', description: 'Name of the cache bin holding the internal page cache.'),
    ];
  }

}
