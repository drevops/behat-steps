<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend;

use DrevOps\BehatSteps\Backend\BackendInterface;
use DrevOps\BehatSteps\Backend\Capability\AuthenticationCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\CacheCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\ConfigCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\ContentCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\CronCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\LanguageCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\MailCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\ModuleCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\RoleCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\UserCapabilityInterface;
use DrevOps\BehatSteps\Backend\DrupalBackend;
use DrevOps\BehatSteps\Backend\DrupalBackendInterface;
use DrevOps\BehatSteps\Backend\Exception\BootstrapException;
use DrevOps\BehatSteps\Tests\Unit\Backend\Fixtures\FakeVersionDrupalBackend;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests DrupalBackend's capability surface and 'detectMajorVersion()'.
 *
 * Behaviour past construction requires a real Drupal bootstrap and is
 * exercised by the Kernel test suite.
 */
#[CoversClass(DrupalBackend::class)]
#[Group('backends')]
#[Group('drupal')]
class DrupalBackendTest extends UnitTestCase {

  /**
   * A directory carrying both entry files 'detectMajorVersion()' requires.
   */
  protected const DRUPAL_ROOT = __DIR__ . '/../../../fixtures/backend/drupal-root';

  /**
   * Tests that DrupalBackend implements its composite contract.
   */
  public function testImplementsDrupalBackendInterface(): void {
    $interfaces = (array) class_implements(DrupalBackend::class);

    $this->assertContains(DrupalBackendInterface::class, $interfaces);
    $this->assertContains(BackendInterface::class, $interfaces);
  }

  /**
   * Tests that DrupalBackend advertises every capability.
   *
   * @param string $capability_class
   *   The capability interface name.
   */
  #[DataProvider('dataProviderImplementsCapability')]
  public function testImplementsCapability(string $capability_class): void {
    $this->assertTrue(is_subclass_of(DrupalBackend::class, $capability_class), sprintf(
      'DrupalBackend must implement %s.',
      $capability_class
    ));
  }

  /**
   * Data provider listing every capability the Drupal backend must support.
   */
  public static function dataProviderImplementsCapability(): \Iterator {
    yield 'authentication' => [AuthenticationCapabilityInterface::class];
    yield 'cache' => [CacheCapabilityInterface::class];
    yield 'config' => [ConfigCapabilityInterface::class];
    yield 'content' => [ContentCapabilityInterface::class];
    yield 'cron' => [CronCapabilityInterface::class];
    yield 'language' => [LanguageCapabilityInterface::class];
    yield 'mail' => [MailCapabilityInterface::class];
    yield 'module' => [ModuleCapabilityInterface::class];
    yield 'role' => [RoleCapabilityInterface::class];
    yield 'user' => [UserCapabilityInterface::class];
  }

  /**
   * Tests that 'detectMajorVersion()' rejects an unparseable version string.
   */
  public function testDetectMajorVersionRejectsNonNumeric(): void {
    $this->expectException(BootstrapException::class);
    $this->expectExceptionMessageMatches('/Unable to extract major Drupal core version/');

    FakeVersionDrupalBackend::$nextVersion = 'zz.x';
    new FakeVersionDrupalBackend(static::DRUPAL_ROOT, 'default');
  }

  public function testDetectMajorVersionRejectsPre11(): void {
    $this->expectException(BootstrapException::class);
    $this->expectExceptionMessageMatches('/Unsupported Drupal core version/');

    FakeVersionDrupalBackend::$nextVersion = '10.4.0';
    new FakeVersionDrupalBackend(static::DRUPAL_ROOT, 'default');
  }

  /**
   * Tests that a root without Drupal's entry files is rejected.
   */
  public function testDetectMajorVersionRejectsRootWithoutDrupal(): void {
    $this->expectException(BootstrapException::class);
    $this->expectExceptionMessageMatches('/No Drupal installation found at/');

    new FakeVersionDrupalBackend(__DIR__, 'default');
  }

  /**
   * Tests that a root missing either entry file is rejected.
   *
   * @param string $present
   *   The entry file the root carries, relative to it.
   * @param string $missing
   *   The entry file the root lacks, named in the expected message.
   */
  #[DataProvider('dataProviderDetectMajorVersionRejectsPartialRoot')]
  public function testDetectMajorVersionRejectsPartialRoot(string $present, string $missing): void {
    $root = static::DRUPAL_ROOT . '/../partial-root-' . md5($present);
    mkdir(dirname($root . $present), 0777, TRUE);
    touch($root . $present);

    try {
      $this->expectException(BootstrapException::class);
      $this->expectExceptionMessage($missing . ' is missing');

      new FakeVersionDrupalBackend($root, 'default');
    }
    finally {
      unlink($root . $present);
      $this->removeTree($root);
    }
  }

  public static function dataProviderDetectMajorVersionRejectsPartialRoot(): \Iterator {
    yield 'bootstrap include missing' => ['/autoload.php', '/core/includes/bootstrap.inc'];
    yield 'autoloader missing' => ['/core/includes/bootstrap.inc', '/autoload.php'];
  }

  /**
   * Removes a directory and every empty directory it contains.
   *
   * @param string $directory
   *   The directory to remove.
   */
  protected function removeTree(string $directory): void {
    $entries = (array) scandir($directory);

    foreach ($entries as $entry) {
      if ($entry === '.' || $entry === '..') {
        continue;
      }

      $this->removeTree($directory . '/' . $entry);
    }

    rmdir($directory);
  }

}
