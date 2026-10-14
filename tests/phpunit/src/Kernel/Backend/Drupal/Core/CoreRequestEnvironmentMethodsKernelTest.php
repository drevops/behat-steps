<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Drupal\Core;

use DrevOps\BehatSteps\Backend\Drupal\Core\Core;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Kernel test for the request header methods on Core.
 */
#[CoversClass(Core::class)]
#[Group('core')]
#[RunTestsInSeparateProcesses]
class CoreRequestEnvironmentMethodsKernelTest extends KernelTestBase {

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

    $this->core = new Core($this->root);
  }

  public function testRequestHeaderSetReachesRequestBuiltFromTheServerVariables(): void {
    $this->core->requestHeaderSet('X-Config-No-Override', 'system.site');

    $this->assertSame('system.site', $_SERVER['HTTP_X_CONFIG_NO_OVERRIDE'] ?? NULL);
    $this->assertSame('system.site', Request::createFromGlobals()->headers->get('X-Config-No-Override'));
  }

  public function testRequestHeaderSetReplacesTheValueSetBefore(): void {
    $this->core->requestHeaderSet('X-Config-No-Override', 'system.site');

    $this->core->requestHeaderSet('X-Config-No-Override', 'system.site,user.settings');

    $this->assertSame('system.site,user.settings', $_SERVER['HTTP_X_CONFIG_NO_OVERRIDE'] ?? NULL);
  }

  public function testRequestHeaderUnsetRemovesTheServerVariable(): void {
    $this->core->requestHeaderSet('X-Config-No-Override', 'system.site');

    $this->core->requestHeaderUnset('X-Config-No-Override');

    $this->assertArrayNotHasKey('HTTP_X_CONFIG_NO_OVERRIDE', $_SERVER);
  }

  public function testRequestHeaderUnsetIgnoresHeaderNeverSet(): void {
    $this->core->requestHeaderUnset('X-Never-Set');

    $this->assertArrayNotHasKey('HTTP_X_NEVER_SET', $_SERVER);
  }

}
