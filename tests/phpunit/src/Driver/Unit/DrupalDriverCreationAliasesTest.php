<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Driver\Unit;

use DrevOps\BehatSteps\Driver\Alias\RolesAlias;
use DrevOps\BehatSteps\Driver\Capability\CreationAliasCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\UserCapabilityInterface;
use DrevOps\BehatSteps\Driver\Core\CoreInterface;
use DrevOps\BehatSteps\Driver\DrupalDriver;
use DrevOps\BehatSteps\Tests\Driver\Unit\Fixtures\AliasCapableCoreInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests creation-alias discovery on 'DrupalDriver'.
 *
 * The driver delegates to its 'Core' instance; behaviour here pins the
 * delegation contract without booting Drupal.
 *
 * @group drivers
 * @group drupal
 * @group aliases
 */
#[Group('drivers')]
#[Group('drupal')]
#[Group('aliases')]
class DrupalDriverCreationAliasesTest extends TestCase {

  /**
   * Tests that DrupalDriver implements the opt-in capability interface.
   */
  public function testImplementsCreationAliasCapability(): void {
    $this->assertContains(CreationAliasCapabilityInterface::class, (array) class_implements(DrupalDriver::class));
  }

  /**
   * Tests that 'getCreationAliases()' returns '[]' for a non-alias core.
   */
  public function testGetCreationAliasesReturnsEmptyForLegacyCore(): void {
    $core = $this->createMock(CoreInterface::class);
    $driver = $this->createDriverWithCore($core);

    $this->assertSame([], $driver->getCreationAliases('node'));
  }

  /**
   * Tests that 'getCreationAliases()' delegates to an alias-capable core.
   */
  public function testGetCreationAliasesDelegatesToCore(): void {
    $alias = new RolesAlias($this->createStubUserCapability());
    $alias_capable_core = $this->createMock(AliasCapableCoreInterface::class);
    $alias_capable_core->expects($this->once())
      ->method('getCreationAliases')
      ->with('user')
      ->willReturn(['roles' => $alias]);

    $driver = $this->createDriverWithCore($alias_capable_core);

    $this->assertSame(['roles' => $alias], $driver->getCreationAliases('user'));
  }

  /**
   * Returns a noop 'UserCapabilityInterface' double for RolesAlias.
   */
  protected function createStubUserCapability(): UserCapabilityInterface {
    return $this->createMock(UserCapabilityInterface::class);
  }

  /**
   * Creates a 'DrupalDriver' with an injected core.
   *
   * Bypasses the constructor (which requires a real Drupal installation)
   * and sets properties directly via reflection.
   */
  protected function createDriverWithCore(CoreInterface $core): DrupalDriver {
    $reflection = new \ReflectionClass(DrupalDriver::class);
    $driver = $reflection->newInstanceWithoutConstructor();

    $reflection->getProperty('drupalRoot')->setValue($driver, __DIR__);
    $reflection->getProperty('uri')->setValue($driver, 'default');
    $reflection->getProperty('version')->setValue($driver, 11);

    $driver->setCore($core);

    return $driver;
  }

}
