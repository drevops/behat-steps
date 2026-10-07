<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend;

use DrevOps\BehatSteps\Backend\Core\Core;
use DrevOps\BehatSteps\Backend\DrupalBackend;
use DrevOps\BehatSteps\Backend\Exception\BootstrapException;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for 'DrupalBackend' construction and version detection.
 *
 * Constructs a real 'DrupalBackend' against the Drupal root that the kernel
 * test framework already has on disk. That path contains an 'autoload.php'
 * and a 'core/includes/bootstrap.inc', so the internal version detection
 * runs end-to-end instead of being bypassed via reflection.
 */
#[CoversClass(DrupalBackend::class)]
#[Group('backends')]
#[Group('drupal')]
#[RunTestsInSeparateProcesses]
class DrupalBackendConstructionKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = ['system'];

  public function testConstructorDetectsVersion(): void {
    $backend = new DrupalBackend($this->root, 'default');

    $version = $backend->getDrupalVersion();

    $this->assertGreaterThanOrEqual(11, $version, 'Backend should detect Drupal 11 or higher.');
  }

  public function testConstructorRejectsMissingRoot(): void {
    $this->expectException(BootstrapException::class);
    $this->expectExceptionMessageMatches('/No Drupal installation found/');

    new DrupalBackend('/nonexistent/path/that/will/never/exist', 'default');
  }

  public function testSetCoreFromVersionSelectsDefaultCore(): void {
    $backend = new DrupalBackend($this->root, 'default');
    $backend->setCoreFromVersion();

    $this->assertInstanceOf(Core::class, $backend->getCore());
  }

}
