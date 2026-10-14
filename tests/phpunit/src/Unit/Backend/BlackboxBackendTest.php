<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend;

use DrevOps\BehatSteps\Backend\BackendInterface;
use DrevOps\BehatSteps\Backend\BlackboxBackend;
use DrevOps\BehatSteps\Backend\BlackboxBackendInterface;
use DrevOps\BehatSteps\Backend\Capability\AuthenticationCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\CacheCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\ContentCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\CronCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\EntityLookupCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\LanguageCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\MailCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\RoleCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\UserCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\BatchCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\BlockCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\ConfigCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\ConfigEntityCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\CoreCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\DrushCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\EntityDefinitionCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\ModuleCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\NodeCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\PathAliasCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\StateCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\TermCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\UserActivityCapabilityInterface;
use DrevOps\BehatSteps\Backend\Random\RandomGenerator;
use DrevOps\BehatSteps\Backend\Random\RandomGeneratorInterface;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests BlackboxBackend's interface and capability surface.
 */
#[CoversClass(BlackboxBackend::class)]
#[Group('backends')]
#[Group('blackbox')]
class BlackboxBackendTest extends UnitTestCase {

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
    $this->assertInstanceOf(RandomGeneratorInterface::class, $backend->getRandom());
  }

  public function testGetRandomReturnsInjectedInstance(): void {
    $random = new RandomGenerator();
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

  public static function dataProviderDoesNotImplementCapability(): \Iterator {
    yield 'authentication' => [AuthenticationCapabilityInterface::class];
    yield 'batch' => [BatchCapabilityInterface::class];
    yield 'block' => [BlockCapabilityInterface::class];
    yield 'cache' => [CacheCapabilityInterface::class];
    yield 'config' => [ConfigCapabilityInterface::class];
    yield 'config entity' => [ConfigEntityCapabilityInterface::class];
    yield 'content' => [ContentCapabilityInterface::class];
    yield 'core' => [CoreCapabilityInterface::class];
    yield 'cron' => [CronCapabilityInterface::class];
    yield 'drush' => [DrushCapabilityInterface::class];
    yield 'entity definition' => [EntityDefinitionCapabilityInterface::class];
    yield 'entity lookup' => [EntityLookupCapabilityInterface::class];
    yield 'language' => [LanguageCapabilityInterface::class];
    yield 'mail' => [MailCapabilityInterface::class];
    yield 'module' => [ModuleCapabilityInterface::class];
    yield 'node' => [NodeCapabilityInterface::class];
    yield 'path alias' => [PathAliasCapabilityInterface::class];
    yield 'role' => [RoleCapabilityInterface::class];
    yield 'state' => [StateCapabilityInterface::class];
    yield 'term' => [TermCapabilityInterface::class];
    yield 'user activity' => [UserActivityCapabilityInterface::class];
    yield 'user' => [UserCapabilityInterface::class];
  }

}
