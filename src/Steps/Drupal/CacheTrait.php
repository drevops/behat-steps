<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Step\Given;
use Behat\Step\When;
use DrevOps\BehatSteps\Backend\Capability\CacheCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\CronCapabilityInterface;
use DrevOps\BehatSteps\Extension\Config\Option;
use DrevOps\BehatSteps\Extension\Prerequisite\Prerequisite;
use DrevOps\BehatSteps\Steps\Helper\Drupal\StaticCacheTrait;

/**
 * Invalidate Drupal caches and run cron from within a scenario.
 *
 * - Clear every cache bin, or target a single path, a path pattern, or the
 *   render cache.
 * - Run cron, which also flushes the caches cron itself invalidates.
 *
 * @title Caches and cron
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\WebRawContext
 */
trait CacheTrait {

  use StaticCacheTrait;

  /**
   * Clear every cache bin.
   *
   * @code
   * Given the cache has been cleared
   * @endcode
   *
   * @actor:system
   */
  #[Given('the cache has been cleared')]
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
   * A page cache bin stored outside the database cannot list its entries, so
   * it is emptied whole.
   *
   * @code
   * Given the page cache for the path "/about" has been cleared
   * @endcode
   *
   * @actor:system
   */
  #[Given('the page cache for the path :path has been cleared')]
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
   * format. A page cache bin stored outside the database cannot list its
   * entries, so it is emptied whole.
   *
   * @code
   * Given the page cache for the paths matching "/news*" has been cleared
   * @endcode
   *
   * @actor:system
   */
  #[Given('the page cache for the paths matching :path_pattern has been cleared')]
  public function cacheClearPagePathWildcard(string $path_pattern): void {
    $this->cacheDeletePagePath($path_pattern, TRUE);
  }

  /**
   * Clear the render cache.
   *
   * @code
   * Given the render cache has been cleared
   * @endcode
   *
   * @actor:system
   */
  #[Given('the render cache has been cleared')]
  public function cacheClearRender(): void {
    $backend = $this->backendFor(CacheCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    $backend->cacheClearBin('render');
  }

  /**
   * Run cron.
   *
   * @code
   * When cron runs
   * @endcode
   *
   * @actor:system
   */
  #[When('cron runs')]
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
   * Only a bin on the database backend can list its entries, so a bin on any
   * other backend is emptied whole. A bin is also emptied whole on a backend
   * that reaches the site from outside its process.
   *
   * @param string $path
   *   The path, starting with '/'.
   * @param bool $is_pattern
   *   Whether '*' in the path matches any run of characters, including '/'.
   *
   * @throws \RuntimeException
   *   When the path is empty, has no leading slash, carries a query string or
   *   a fragment, or when the page cache bin does not exist. Enable the
   *   `page_cache` module or set the `cache.page_cache_bin` option for the
   *   bin.
   */
  public function cacheDeletePagePath(string $path, bool $is_pattern = FALSE): void {
    $backend = $this->backendFor(CacheCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

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

    $backend->cacheDeletePagePath($this->cacheGetPageCacheBin(), $path, $is_pattern);
  }

  /**
   * Declares the options this trait reads.
   *
   * @return array<int, \DrevOps\BehatSteps\Extension\Config\Option>
   *   The options this trait declares.
   */
  protected function cacheConfigSchema(): array {
    return [
      new Option('page_cache_bin', default: 'page', description: "The cache bin that holds Drupal's internal page cache, which the steps clearing the page cache for a path delete entries from. The Drush backend empties the whole bin."),
    ];
  }

  /**
   * Declares the prerequisites this trait asserts.
   *
   * The clear-all step resolves the same capability itself and the cron step
   * the cron capability, so only the page and render cache steps check.
   *
   * @return array<int, \DrevOps\BehatSteps\Extension\Prerequisite\Prerequisite>
   *   The prerequisites this trait declares.
   */
  protected function cachePrerequisites(): array {
    return [
      Prerequisite::capability(CacheCapabilityInterface::class),
    ];
  }

}
