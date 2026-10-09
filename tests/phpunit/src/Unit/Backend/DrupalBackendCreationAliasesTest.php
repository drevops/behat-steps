<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend;

use DrevOps\BehatSteps\Backend\Alias\RolesAlias;
use DrevOps\BehatSteps\Backend\Capability\CreationAliasCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\UserCapabilityInterface;
use DrevOps\BehatSteps\Backend\Core\CoreInterface;
use DrevOps\BehatSteps\Backend\DrupalBackend;
use DrevOps\BehatSteps\Tests\Unit\Backend\Fixtures\AliasCapableCoreInterface;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests creation-alias discovery on 'DrupalBackend'.
 *
 * The backend delegates to its 'Core' instance; the tests pin the delegation
 * contract without booting Drupal.
 */
#[CoversClass(DrupalBackend::class)]
#[Group('backends')]
#[Group('drupal')]
#[Group('aliases')]
class DrupalBackendCreationAliasesTest extends UnitTestCase {

  public function testImplementsCreationAliasCapability(): void {
    $this->assertContains(CreationAliasCapabilityInterface::class, (array) class_implements(DrupalBackend::class));
  }

  /**
   * Tests that 'getCreationAliases()' returns '[]' for a non-alias core.
   */
  public function testGetCreationAliasesReturnsEmptyForNonAliasCore(): void {
    $core = $this->createMock(CoreInterface::class);
    $backend = $this->createBackendWithCore($core);

    $this->assertSame([], $backend->getCreationAliases('node'));
  }

  public function testGetCreationAliasesDelegatesToCore(): void {
    $alias = new RolesAlias($this->createStubUserCapability());
    $alias_capable_core = $this->createMock(AliasCapableCoreInterface::class);
    $alias_capable_core->expects($this->once())
      ->method('getCreationAliases')
      ->with('user')
      ->willReturn(['roles' => $alias]);

    $backend = $this->createBackendWithCore($alias_capable_core);

    $this->assertSame(['roles' => $alias], $backend->getCreationAliases('user'));
  }

  protected function createStubUserCapability(): UserCapabilityInterface {
    return $this->createMock(UserCapabilityInterface::class);
  }

  /**
   * Creates a 'DrupalBackend' with an injected core.
   *
   * Bypasses the constructor (which requires a real Drupal installation)
   * and sets properties directly via reflection.
   */
  protected function createBackendWithCore(CoreInterface $core): DrupalBackend {
    $reflection = new \ReflectionClass(DrupalBackend::class);
    $backend = $reflection->newInstanceWithoutConstructor();

    $reflection->getProperty('drupalRoot')->setValue($backend, __DIR__);
    $reflection->getProperty('uri')->setValue($backend, 'default');
    $reflection->getProperty('version')->setValue($backend, 11);

    $backend->setCore($core);

    return $backend;
  }

}
