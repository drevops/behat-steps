<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Core;

use DrevOps\BehatSteps\Backend\Core\Core;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for config-related methods on Core via the backend.
 */
#[CoversClass(Core::class)]
#[Group('core')]
#[RunTestsInSeparateProcesses]
class CoreConfigMethodsKernelTest extends KernelTestBase {

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

  public function testConfigSetAndGetRoundTrip(): void {
    $this->core->configSet('system.site', 'name', 'DrupalBackend Test Site');

    $this->assertSame('DrupalBackend Test Site', $this->core->configGet('system.site', 'name'));
  }

  /**
   * Tests that configGetOriginal() returns the value as stored.
   *
   * Config::getOriginal() reads the data as loaded from storage, before any
   * unsaved change, so after a save it matches configGet().
   */
  public function testConfigGetOriginalExposesStoredValue(): void {
    $this->core->configSet('system.site', 'name', 'Pinned');

    $this->assertSame('Pinned', $this->core->configGetOriginal('system.site', 'name'));
  }

}
