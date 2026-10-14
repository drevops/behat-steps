<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Drupal\Core;

use DrevOps\BehatSteps\Backend\Drupal\Core\Core;
use DrevOps\BehatSteps\Backend\Random\RandomGenerator;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for cache-related methods on Core via the backend.
 */
#[CoversClass(Core::class)]
#[Group('core')]
#[RunTestsInSeparateProcesses]
class CoreCacheMethodsKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = ['system'];

  /**
   * The Core backend under test.
   */
  protected Core $core;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    // 'behat_steps.test', the record the config factory test writes, has no
    // schema. Drupal 11 and 12 type the flag differently, so an override of
    // the property loads on only 1 of them.
    $this->strictConfigSchema = FALSE;

    parent::setUp();

    $this->installConfig(['system']);
    $this->core = new Core($this->root);
  }

  public function testCacheClearDispatches(): void {
    \Drupal::cache()->set('drupal_backend_test:sentinel', 'value');
    $this->assertNotFalse(\Drupal::cache()->get('drupal_backend_test:sentinel'));

    $this->core->cacheClear();

    $this->assertFalse(\Drupal::cache()->get('drupal_backend_test:sentinel'));
  }

  public function testCacheClearStaticResetsStatics(): void {
    $counter = &drupal_static('drupal_backend_test_counter');
    $counter = 7;
    $this->assertSame(7, drupal_static('drupal_backend_test_counter'));

    $this->core->cacheClearStatic();

    $this->assertNull(drupal_static('drupal_backend_test_counter'));
  }

  public function testCacheClearStaticEmptiesTheMemoryBin(): void {
    \Drupal::cache('memory')->set('drupal_backend_test:memory', 'value');
    $this->assertNotFalse(\Drupal::cache('memory')->get('drupal_backend_test:memory'));

    $this->core->cacheClearStatic();

    $this->assertFalse(\Drupal::cache('memory')->get('drupal_backend_test:memory'));
  }

  public function testCacheClearStaticResetsTheConfigFactory(): void {
    \Drupal::configFactory()->getEditable('behat_steps.test')->set('value', 'first')->save();
    $this->assertSame('first', \Drupal::config('behat_steps.test')->get('value'));

    // A write straight to the storage leaves the loaded object as it was, the
    // way a write from another process does.
    \Drupal::service('config.storage')->write('behat_steps.test', ['value' => 'second']);
    $this->assertSame('first', \Drupal::configFactory()->get('behat_steps.test')->get('value'));

    $this->core->cacheClearStatic();

    $this->assertSame('second', $this->config('behat_steps.test')->get('value'));
  }

  public function testCacheClearStaticEmptiesTheEntityMemoryCache(): void {
    \Drupal::service('entity.memory_cache')->set('drupal_backend_test:entity', 'value');
    $this->assertNotFalse(\Drupal::service('entity.memory_cache')->get('drupal_backend_test:entity'));

    $this->core->cacheClearStatic();

    $this->assertFalse(\Drupal::service('entity.memory_cache')->get('drupal_backend_test:entity'));
  }

  public function testCacheClearBinEmptiesOnlyTheBin(): void {
    \Drupal::cache('data')->set('drupal_backend_test:data', 'value');
    \Drupal::cache('default')->set('drupal_backend_test:default', 'value');

    $this->core->cacheClearBin('data');

    $this->assertFalse(\Drupal::cache('data')->get('drupal_backend_test:data'));
    $this->assertNotFalse(\Drupal::cache('default')->get('drupal_backend_test:default'));
  }

  public function testCacheClearBinFailsWithoutTheBin(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The cache bin "missing" does not exist.');

    $this->core->cacheClearBin('missing');
  }

  public function testCacheInvalidateTagsInvalidatesTheTaggedEntries(): void {
    \Drupal::cache()->set('drupal_backend_test:tagged', 'value', CacheBackendInterface::CACHE_PERMANENT, ['drupal_backend_test:tag']);
    \Drupal::cache()->set('drupal_backend_test:untagged', 'value');

    $this->core->cacheInvalidateTags(['drupal_backend_test:tag']);

    $this->assertFalse(\Drupal::cache()->get('drupal_backend_test:tagged'));
    $this->assertNotFalse(\Drupal::cache()->get('drupal_backend_test:untagged'));
  }

  public function testCacheInvalidateTagsWithNoTagsInvalidatesNothing(): void {
    \Drupal::cache()->set('drupal_backend_test:tagged', 'value', CacheBackendInterface::CACHE_PERMANENT, ['drupal_backend_test:tag']);

    $this->core->cacheInvalidateTags([]);

    $this->assertNotFalse(\Drupal::cache()->get('drupal_backend_test:tagged'));
  }

  public function testGetExtensionPathListIncludesEnabledModules(): void {
    $paths = $this->core->getExtensionPathList();

    $this->assertContains(
      $this->root . '/core/modules/system',
      $paths,
      'Enabled system module path should appear in the extension list.'
    );
  }

  public function testGetRandomReturnsInjectedGenerator(): void {
    $random = new RandomGenerator();
    $core = new Core($this->root, 'default', $random);

    $this->assertSame($random, $core->getRandom());
  }

}
