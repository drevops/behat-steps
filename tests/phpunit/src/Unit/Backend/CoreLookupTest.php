<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend;

use DrevOps\BehatSteps\Backend\Core\Core;
use DrevOps\BehatSteps\Backend\Core99\Core as Core99Core;
use DrevOps\BehatSteps\Backend\DrupalBackend;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests DrupalBackend::setCoreFromVersion() lookup chain.
 */
#[CoversClass(Core::class)]
#[Group('backends')]
#[Group('drupal')]
class CoreLookupTest extends UnitTestCase {

  public function testCore99ClassIsAutoloadable(): void {
    $this->assertTrue(
      class_exists(Core99Core::class),
      'DrevOps\\BehatSteps\\Backend\\Core99\\Core must be autoloadable via tests/phpunit/fixtures/backend/'
    );
  }

  public function testLookupPicksVersionOverride(): void {
    $backend = $this->createBackendWithVersion(99);
    $backend->setCoreFromVersion();

    $this->assertInstanceOf(Core99Core::class, $backend->getCore());
    $this->assertSame('Core99\\Core', $backend->getCore()::MARKER);
  }

  public function testLookupFallsBackToDefault(): void {
    // Version 50: no Core50 fixture exists, so the chain falls through.
    $backend = $this->createBackendWithVersion(50);
    $backend->setCoreFromVersion();

    $this->assertInstanceOf(Core::class, $backend->getCore());
  }

  /**
   * Creates a DrupalBackend instance with a fixed version, bypassing bootstrap.
   *
   * Uses ReflectionClass to skip the constructor (which requires a real Drupal
   * root) and injects the required protected properties directly.
   *
   * @param int $version
   *   The Drupal major version to report.
   *
   * @return \DrevOps\BehatSteps\Backend\DrupalBackend
   *   The prepared backend instance.
   */
  protected function createBackendWithVersion(int $version): DrupalBackend {
    $reflection = new \ReflectionClass(DrupalBackend::class);
    /** @var \DrevOps\BehatSteps\Backend\DrupalBackend $backend */
    $backend = $reflection->newInstanceWithoutConstructor();

    $root_property = $reflection->getProperty('drupalRoot');
    $root_property->setValue($backend, __DIR__);

    $uri_property = $reflection->getProperty('uri');
    $uri_property->setValue($backend, 'default');

    $version_property = $reflection->getProperty('version');
    $version_property->setValue($backend, $version);

    return $backend;
  }

}
