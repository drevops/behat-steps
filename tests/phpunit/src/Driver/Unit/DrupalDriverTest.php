<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Driver\Unit;

use DrevOps\BehatSteps\Driver\Capability\AuthenticationCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\CacheCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\ConfigCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\ContentCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\CronCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\LanguageCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\MailCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\ModuleCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\RoleCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\UserCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\WatchdogCapabilityInterface;
use DrevOps\BehatSteps\Driver\DriverInterface;
use DrevOps\BehatSteps\Driver\DrupalDriver;
use DrevOps\BehatSteps\Driver\DrupalDriverInterface;
use DrevOps\BehatSteps\Driver\Exception\BootstrapException;
use DrevOps\BehatSteps\Driver\SubDriverFinderInterface;
use DrevOps\BehatSteps\Tests\Driver\Unit\Fixtures\FakeVersionDrupalDriver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests that DrupalDriver declares the full capability surface.
 *
 * Class-level conformance only; runtime behaviour requires a real Drupal
 * bootstrap and is exercised by the Kernel test suite.
 *
 * @group drivers
 * @group drupal
 */
#[Group('drivers')]
#[Group('drupal')]
class DrupalDriverTest extends TestCase {

  /**
   * A directory carrying the entry file 'detectMajorVersion()' requires.
   */
  protected const DRUPAL_ROOT = __DIR__ . '/../../../fixtures/driver/drupal-root';

  /**
   * Tests that DrupalDriver implements its composite contract.
   */
  public function testImplementsDrupalDriverInterface(): void {
    $interfaces = (array) class_implements(DrupalDriver::class);

    $this->assertContains(DrupalDriverInterface::class, $interfaces);
    $this->assertContains(DriverInterface::class, $interfaces);
    $this->assertContains(SubDriverFinderInterface::class, $interfaces);
  }

  /**
 * Tests that DrupalDriver advertises every capability.
 *
 * @param string $capability_class
 *   The capability interface name.
 *
 * @dataProvider dataProviderImplementsCapability
 */
  #[DataProvider('dataProviderImplementsCapability')]
  public function testImplementsCapability(string $capability_class): void {
    $this->assertTrue(is_subclass_of(DrupalDriver::class, $capability_class), sprintf(
      'DrupalDriver must implement %s.',
      $capability_class
    ));
  }

  /**
   * Data provider listing every capability the Drupal driver must support.
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
    yield 'watchdog' => [WatchdogCapabilityInterface::class];
  }

  /**
   * Tests that 'detectMajorVersion()' rejects an unparseable version string.
   *
   * Uses a fixture subclass to inject a non-numeric version value without
   * touching the real '\Drupal::VERSION' constant.
   */
  public function testDetectMajorVersionRejectsNonNumeric(): void {
    $this->expectException(BootstrapException::class);
    $this->expectExceptionMessageMatches('/Unable to extract major Drupal core version/');

    FakeVersionDrupalDriver::$nextVersion = 'zz.x';
    new FakeVersionDrupalDriver(self::DRUPAL_ROOT, 'default');
  }

  /**
   * Tests that 'detectMajorVersion()' rejects pre-11 versions.
   */
  public function testDetectMajorVersionRejectsPre11(): void {
    $this->expectException(BootstrapException::class);
    $this->expectExceptionMessageMatches('/Unsupported Drupal core version/');

    FakeVersionDrupalDriver::$nextVersion = '10.4.0';
    new FakeVersionDrupalDriver(self::DRUPAL_ROOT, 'default');
  }

  /**
   * Tests that a root without Drupal's entry files is rejected.
   */
  public function testDetectMajorVersionRejectsRootWithoutDrupal(): void {
    $this->expectException(BootstrapException::class);
    $this->expectExceptionMessageMatches('/No Drupal installation found at/');

    new FakeVersionDrupalDriver(__DIR__, 'default');
  }

  /**
   * Tests that a root missing either entry file is rejected.
   *
   * @param string $present
   *   The entry file the root carries, relative to it.
   * @param string $missing
   *   The entry file the root lacks, named in the expected message.
   *
   * @dataProvider dataProviderDetectMajorVersionRejectsPartialRoot
   */
  #[DataProvider('dataProviderDetectMajorVersionRejectsPartialRoot')]
  public function testDetectMajorVersionRejectsPartialRoot(string $present, string $missing): void {
    $root = self::DRUPAL_ROOT . '/../partial-root-' . md5($present);
    mkdir(dirname($root . $present), 0777, TRUE);
    touch($root . $present);

    try {
      $this->expectException(BootstrapException::class);
      $this->expectExceptionMessage($missing . ' is missing');

      new FakeVersionDrupalDriver($root, 'default');
    }
    finally {
      unlink($root . $present);
      $this->removeTree($root);
    }
  }

  /**
   * Data provider for 'testDetectMajorVersionRejectsPartialRoot()'.
   */
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
