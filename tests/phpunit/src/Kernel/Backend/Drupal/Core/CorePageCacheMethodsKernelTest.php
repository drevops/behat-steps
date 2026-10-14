<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Drupal\Core;

use DrevOps\BehatSteps\Backend\Drupal\Core\Core;
use Drupal\Core\Cache\DatabaseBackend;
use Drupal\Core\Database\Database;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for deleting internal page cache entries through Core.
 */
#[CoversClass(Core::class)]
#[Group('core')]
#[RunTestsInSeparateProcesses]
class CorePageCacheMethodsKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = ['system', 'page_cache'];

  /**
   * The Core backend under test.
   */
  protected Core $core;

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);

    // The parent moves every bin to the memory backend, which cannot list its
    // entries, so the page bin is moved back to the database.
    $definition = $container->getDefinition('cache.page');
    $tags = $definition->getTags();
    $tags['cache.bin'][0]['default_backend'] = 'cache.backend.database';
    $definition->setTags($tags);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->core = new Core($this->root);
  }

  /**
   * Tests that a path or pattern deletes exactly the matching entries.
   *
   * @param string $path
   *   The path or pattern passed to the method.
   * @param bool $is_pattern
   *   Whether the path is a pattern.
   * @param array<int, string> $deleted
   *   The labels of the entries the call deletes.
   */
  #[DataProvider('dataProviderDeletePagePath')]
  public function testDeletePagePath(string $path, bool $is_pattern, array $deleted): void {
    $entries = static::listPageCacheEntries();
    $backend = \Drupal::cache('page');
    $this->assertInstanceOf(DatabaseBackend::class, $backend);

    foreach ($entries as $cid) {
      $backend->set($cid, 'response');
    }

    $this->core->cacheDeletePagePath('page', $path, $is_pattern);

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
    yield 'a pattern matching every path' => ['/*', TRUE, array_values(array_diff(array_keys(static::listPageCacheEntries()), ['not a URL', 'a URL with no path', 'a malformed URL']))];
  }

  public function testDeletePagePathIgnoresMissingTable(): void {
    $database = Database::getConnection();
    $this->assertFalse($database->schema()->tableExists('cache_page'));

    $this->core->cacheDeletePagePath('page', '/about');

    $this->assertFalse($database->schema()->tableExists('cache_page'));
  }

  public function testDeletePagePathEmptiesBinThatCannotListItsEntries(): void {
    $backend = \Drupal::cache('data');
    $this->assertNotInstanceOf(DatabaseBackend::class, $backend);

    $backend->set('http://nginx:8080/about:', 'response');
    $backend->set('http://nginx:8080/contact:', 'response');

    $this->core->cacheDeletePagePath('data', '/about');

    $this->assertFalse($backend->get('http://nginx:8080/about:'));
    $this->assertFalse($backend->get('http://nginx:8080/contact:'));
  }

  public function testDeletePagePathFailsWithoutTheBin(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The cache bin "missing" does not exist.');

    $this->core->cacheDeletePagePath('missing', '/about');
  }

  /**
   * Returns the internal page cache IDs each test writes, keyed by a label.
   *
   * @return array<string, string>
   *   The cache IDs.
   */
  protected static function listPageCacheEntries(): array {
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

}
