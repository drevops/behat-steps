<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Steps\Drupal;

use DrevOps\BehatSteps\Steps\Drupal\CacheTrait;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\DatabaseBackendFactory;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for deleting internal page cache entries through 'CacheTrait'.
 */
#[CoversTrait(CacheTrait::class)]
#[Group('behat')]
#[RunTestsInSeparateProcesses]
class CacheTraitKernelTest extends StepTraitKernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = ['system'];

  /**
   * Tests that a path or pattern deletes exactly the matching entries.
   *
   * @param string $path
   *   The path or pattern passed to the helper.
   * @param bool $is_pattern
   *   Whether the path is a pattern.
   * @param array<int, string> $deleted
   *   The labels of the entries the call deletes.
   */
  #[DataProvider('dataProviderDeletePagePath')]
  public function testDeletePagePath(string $path, bool $is_pattern, array $deleted): void {
    $entries = static::pageCacheEntries();
    $backend = $this->createPageCacheBackend();

    foreach ($entries as $cid) {
      $backend->set($cid, 'response');
    }

    $this->context->cacheDeletePagePath($path, $is_pattern);

    $cids = array_values($entries);
    $found = $backend->getMultiple($cids);
    $remaining = array_keys(array_filter($entries, static fn(string $cid): bool => isset($found[$cid])));

    $this->assertSame(array_values(array_diff(array_keys($entries), $deleted)), $remaining);
  }

  public static function dataProviderDeletePagePath(): \Iterator {
    yield 'a path on every host, query string and request format' => ['/about', FALSE, ['about', 'about as html', 'about on another host', 'about with a query string']];
    yield 'the front page' => ['/', FALSE, ['front page']];
    yield 'an underscore in a path matches itself' => ['/a_c', FALSE, ['a_c']];
    yield 'a percent sign in a path matches itself' => ['/sale/50%25', FALSE, ['sale/50%25']];
    yield 'a colon in a path' => ['/time:12', FALSE, ['time:12']];
    yield 'an asterisk in a path matches itself' => ['/star*', FALSE, ['star*']];
    yield 'a path held in a truncated cache ID' => ['/search', FALSE, ['search with a long query string']];
    yield 'a path with no entry' => ['/missing', FALSE, []];
    yield 'a pattern ending in a wildcard' => ['/about*', TRUE, ['about', 'about as html', 'about on another host', 'about with a query string', 'about/team', 'about-us']];
    yield 'a pattern ending in a wildcard segment' => ['/about/*', TRUE, ['about/team']];
    yield 'a pattern opening with a wildcard segment' => ['/*/about', TRUE, ['archive/about']];
    yield 'a pattern with a wildcard inside' => ['/a*c', TRUE, ['a_c', 'abc']];
    yield 'an underscore in a pattern matches itself' => ['/a_c*', TRUE, ['a_c']];
    yield 'a pattern matching every path' => ['/*', TRUE, array_values(array_diff(array_keys(static::pageCacheEntries()), ['not a URL', 'a URL with no path', 'a malformed URL']))];
  }

  /**
   * Tests that an argument that cannot match a cached path is rejected.
   *
   * @param string $path
   *   The path or pattern passed to the helper.
   * @param bool $is_pattern
   *   Whether the path is a pattern.
   * @param string $message
   *   The expected exception message.
   */
  #[DataProvider('dataProviderDeletePagePathRejectsInvalidPath')]
  public function testDeletePagePathRejectsInvalidPath(string $path, bool $is_pattern, string $message): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage($message);

    $this->context->cacheDeletePagePath($path, $is_pattern);
  }

  public static function dataProviderDeletePagePathRejectsInvalidPath(): \Iterator {
    yield 'an empty path' => ['', FALSE, 'The path must not be empty.'];
    yield 'an empty pattern' => ['', TRUE, 'The path pattern must not be empty.'];
    yield 'a path without a leading slash' => ['about', FALSE, 'The path "about" must start with a leading slash.'];
    yield 'a pattern without a leading slash' => ['news/*', TRUE, 'The path pattern "news/*" must start with a leading slash.'];
    yield 'a path with a query string' => ['/about?page=1', FALSE, 'The path "/about?page=1" must not contain a query string or a fragment.'];
    yield 'a path with a fragment' => ['/about#team', FALSE, 'The path "/about#team" must not contain a query string or a fragment.'];
    yield 'a pattern with a query string' => ['/news*?page=1', TRUE, 'The path pattern "/news*?page=1" must not contain a query string or a fragment.'];
  }

  public function testDeletePagePathFailsWithoutThePageCacheTable(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The page cache table "cache_page" does not exist. Ensure the "page" cache bin is configured.');

    $this->context->cacheDeletePagePath('/about');
  }

  /**
   * Returns the internal page cache IDs each test writes, keyed by a label.
   *
   * @return array<string, string>
   *   The cache IDs.
   */
  protected static function pageCacheEntries(): array {
    return [
      'front page' => 'http://nginx:8080/:',
      'about' => 'http://nginx:8080/about:',
      'about as html' => 'http://nginx:8080/about:html',
      'about on another host' => 'https://example.com/about:',
      'about with a query string' => 'http://nginx:8080/about?page=1:',
      'about/team' => 'http://nginx:8080/about/team:',
      'about-us' => 'http://nginx:8080/about-us:',
      'archive/about' => 'http://nginx:8080/archive/about:',
      'node with about in its query string' => 'http://nginx:8080/node?destination=/about:',
      'a_c' => 'http://nginx:8080/a_c:',
      'abc' => 'http://nginx:8080/abc:',
      'sale/50%25' => 'http://nginx:8080/sale/50%25:',
      'sale/50X25' => 'http://nginx:8080/sale/50X25:',
      'time:12' => 'http://nginx:8080/time:12:',
      'star*' => 'http://nginx:8080/star*:',
      'stars' => 'http://nginx:8080/stars:',
      // Over 255 characters, so the database backend stores a truncated ID
      // with a hash appended.
      'search with a long query string' => 'http://nginx:8080/search?q=' . str_repeat('a', 300) . ':',
      'not a URL' => 'not-a-page-cache-entry',
      'a URL with no path' => 'http://nginx:8080',
      'a malformed URL' => 'http:///:',
    ];
  }

  /**
   * Creates the database backend for the page cache bin.
   */
  protected function createPageCacheBackend(): CacheBackendInterface {
    $factory = $this->container->get('cache.backend.database');
    $this->assertInstanceOf(DatabaseBackendFactory::class, $factory);

    return $factory->get('page');
  }

}
