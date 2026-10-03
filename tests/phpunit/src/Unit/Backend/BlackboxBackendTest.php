<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend;

use DrevOps\BehatSteps\Backend\BackendInterface;
use DrevOps\BehatSteps\Backend\BlackboxBackend;
use DrevOps\BehatSteps\Backend\BlackboxBackendInterface;
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
use Drupal\Component\Utility\Random;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests BlackboxBackend's interface and capability surface.
 */
#[CoversClass(BlackboxBackend::class)]
#[Group('backends')]
#[Group('blackbox')]
class BlackboxBackendTest extends TestCase {

  public function testImplementsExpectedInterfaces(): void {
    $backend = new BlackboxBackend();
    $this->assertInstanceOf(BlackboxBackendInterface::class, $backend);
    $this->assertInstanceOf(BackendInterface::class, $backend);
  }

  public function testIsBootstrappedReturnsTrue(): void {
    $backend = new BlackboxBackend();
    $this->assertTrue($backend->isBootstrapped());
  }

  public function testBootstrapIsNoop(): void {
    $backend = new BlackboxBackend();
    $backend->bootstrap();
    $this->addToAssertionCount(1);
  }

  public function testGetRandomReturnsInstance(): void {
    $backend = new BlackboxBackend();
    $this->assertInstanceOf(Random::class, $backend->getRandom());
  }

  public function testGetRandomReturnsInjectedInstance(): void {
    $random = new Random();
    $backend = new BlackboxBackend($random);
    $this->assertSame($random, $backend->getRandom());
  }

  /**
   * Tests that BlackboxBackend does not claim unsupported capabilities.
   *
   * @param string $capability_class
   *   The fully qualified capability interface name.
   */
  #[DataProvider('dataProviderDoesNotImplementCapability')]
  public function testDoesNotImplementCapability(string $capability_class): void {
    $this->assertNotContains($capability_class, (array) class_implements(BlackboxBackend::class), sprintf(
      'BlackboxBackend must not claim to implement %s.',
      $capability_class
    ));
  }

  /**
   * Data provider listing every capability BlackboxBackend must not declare.
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
  }

}
