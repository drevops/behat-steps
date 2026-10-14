<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Steps\Drupal;

use DrevOps\BehatSteps\Backend\Drupal\DrupalBackendInterface;
use DrevOps\BehatSteps\Extension\Registry\BackendRegistry;
use DrevOps\BehatSteps\Steps\Drupal\CacheTrait;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use DrevOps\BehatSteps\WebRawContext;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests the cache steps against the cache and cron capabilities.
 */
#[CoversTrait(CacheTrait::class)]
class CacheTraitTest extends UnitTestCase {

  /**
   * Tests that the page cache steps pass the configured bin and the path on.
   *
   * @param string $method
   *   The step or helper to call.
   * @param array<int, mixed> $args
   *   The arguments to call it with.
   * @param array<string, array<string, mixed>> $config
   *   The trait options, keyed by trait group.
   * @param array<int, mixed> $expected
   *   The arguments the backend must receive.
   */
  #[DataProvider('dataProviderDeletePagePathForwardsTheBinAndThePath')]
  public function testDeletePagePathForwardsTheBinAndThePath(string $method, array $args, array $config, array $expected): void {
    $backend = $this->createMock(DrupalBackendInterface::class);
    $backend->expects($this->once())->method('cacheDeletePagePath')->with(...$expected);

    $this->createContext($backend, $config)->{$method}(...$args);
  }

  public static function dataProviderDeletePagePathForwardsTheBinAndThePath(): \Iterator {
    yield 'a path on the default bin' => ['cacheDeletePagePath', ['/about'], [], ['page', '/about', FALSE]];
    yield 'a pattern on the default bin' => ['cacheDeletePagePath', ['/news*', TRUE], [], ['page', '/news*', TRUE]];
    yield 'a path on the configured bin' => ['cacheDeletePagePath', ['/about'], ['cache' => ['page_cache_bin' => 'data']], ['data', '/about', FALSE]];
    yield 'the path step' => ['cacheClearPagePath', ['/about'], [], ['page', '/about', FALSE]];
    yield 'the pattern step' => ['cacheClearPagePathWildcard', ['/news*'], [], ['page', '/news*', TRUE]];
  }

  /**
   * Tests that an argument that cannot match a cached path is rejected.
   *
   * @param string $path
   *   The path or pattern passed to the helper.
   * @param bool $is_pattern
   *   Whether the path is a pattern.
   * @param string $expected_message
   *   The expected exception message.
   */
  #[DataProvider('dataProviderDeletePagePathRejectsInvalidPath')]
  public function testDeletePagePathRejectsInvalidPath(string $path, bool $is_pattern, string $expected_message): void {
    $backend = $this->createMock(DrupalBackendInterface::class);
    $backend->expects($this->never())->method('cacheDeletePagePath');

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage($expected_message);

    $this->createContext($backend)->cacheDeletePagePath($path, $is_pattern);
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

  public function testClearRenderEmptiesTheRenderBin(): void {
    $backend = $this->createMock(DrupalBackendInterface::class);
    $backend->expects($this->once())->method('cacheClearBin')->with('render');

    $this->createContext($backend)->cacheClearRender();
  }

  public function testClearAllClearsEveryCache(): void {
    $backend = $this->createMock(DrupalBackendInterface::class);
    $backend->expects($this->once())->method('cacheClear');

    $this->createContext($backend)->cacheClearAll();
  }

  public function testRunCronFailsWhenCronDidNotRun(): void {
    $backend = $this->createStub(DrupalBackendInterface::class);
    $backend->method('cronRun')->willReturn(FALSE);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Cron did not run. Another cron run may still hold the lock.');

    $this->createContext($backend)->cacheRunCron();
  }

  /**
   * Creates a context whose scenario lists the given backend.
   *
   * @param \DrevOps\BehatSteps\Backend\Drupal\DrupalBackendInterface $backend
   *   The backend the scenario lists.
   * @param array<string, array<string, mixed>> $config
   *   The trait options, keyed by trait group.
   */
  protected function createContext(DrupalBackendInterface $backend, array $config = []): CacheTraitTestImplementation {
    $registry = new BackendRegistry(['drupal' => $backend]);
    $registry->setScenarioBackends(['drupal' => 'drupal']);

    $context = new CacheTraitTestImplementation($config);
    $context->setBackendRegistry($registry);

    return $context;
  }

}

/**
 * Test implementation of CacheTrait.
 */
class CacheTraitTestImplementation extends WebRawContext {

  use CacheTrait;

}
