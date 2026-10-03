<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Core;

use DrevOps\BehatSteps\Backend\Core\Core;
use Drupal\Component\Utility\Random;
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
    parent::setUp();

    $this->installConfig(['system']);
    $this->core = new Core($this->root);
  }

  public function testCacheClearDispatches(): void {
    // Populate a cache entry so the clear has something to flush.
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

  public function testGetExtensionPathListIncludesEnabledModules(): void {
    $paths = $this->core->getExtensionPathList();

    $this->assertContains(
      $this->root . DIRECTORY_SEPARATOR . 'core/modules/system',
      $paths,
      'Enabled system module path should appear in the extension list.'
    );
  }

  public function testGetRandomReturnsInjectedGenerator(): void {
    $random = new Random();
    $core = new Core($this->root, 'default', $random);

    $this->assertSame($random, $core->getRandom());
  }

}
