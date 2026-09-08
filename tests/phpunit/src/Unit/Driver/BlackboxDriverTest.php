<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Driver;

use DrevOps\BehatSteps\Driver\BlackboxDriver;
use DrevOps\BehatSteps\Driver\BlackboxDriverInterface;
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
use Drupal\Component\Utility\Random;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests BlackboxDriver's interface and capability surface.
 *
 * @group drivers
 * @group blackbox
 */
#[CoversClass(BlackboxDriver::class)]
#[Group('drivers')]
#[Group('blackbox')]
class BlackboxDriverTest extends TestCase {

  /**
   * Tests that BlackboxDriver satisfies its declared interfaces.
   */
  public function testImplementsExpectedInterfaces(): void {
    $driver = new BlackboxDriver();
    $this->assertInstanceOf(BlackboxDriverInterface::class, $driver);
    $this->assertInstanceOf(DriverInterface::class, $driver);
  }

  /**
   * Tests that BlackboxDriver considers itself bootstrapped.
   */
  public function testIsBootstrappedReturnsTrue(): void {
    $driver = new BlackboxDriver();
    $this->assertTrue($driver->isBootstrapped());
  }

  /**
   * Tests that bootstrap() is a no-op.
   */
  public function testBootstrapIsNoop(): void {
    $driver = new BlackboxDriver();
    $driver->bootstrap();
    $this->addToAssertionCount(1);
  }

  /**
   * Tests that getRandom() returns a usable generator.
   */
  public function testGetRandomReturnsInstance(): void {
    $driver = new BlackboxDriver();
    $this->assertInstanceOf(Random::class, $driver->getRandom());
  }

  /**
   * Tests that an injected random generator is returned as-is.
   */
  public function testGetRandomReturnsInjectedInstance(): void {
    $random = new Random();
    $driver = new BlackboxDriver($random);
    $this->assertSame($random, $driver->getRandom());
  }

  /**
 * Tests that BlackboxDriver does not claim unsupported capabilities.
 *
 * @param string $capability_class
 *   The fully qualified capability interface name.
 *
 * @dataProvider dataProviderDoesNotImplementCapability
 */
  #[DataProvider('dataProviderDoesNotImplementCapability')]
  public function testDoesNotImplementCapability(string $capability_class): void {
    $this->assertNotContains($capability_class, (array) class_implements(BlackboxDriver::class), sprintf(
      'BlackboxDriver must not claim to implement %s.',
      $capability_class
    ));
  }

  /**
   * Data provider listing every capability BlackboxDriver must not declare.
   */
  public static function dataProviderDoesNotImplementCapability(): \Iterator {
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

}
