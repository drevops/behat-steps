<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Drupal;

use DrevOps\BehatSteps\Backend\Alias\RolesAlias;
use DrevOps\BehatSteps\Backend\Capability\CreationAliasCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\UserCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Core\CoreInterface;
use DrevOps\BehatSteps\Backend\Drupal\DrupalBackend;
use DrevOps\BehatSteps\Backend\Exception\UnsupportedBackendActionException;
use DrevOps\BehatSteps\Tests\Unit\Backend\Fixtures\AliasCapableCoreInterface;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests creation-alias discovery and registration on 'DrupalBackend'.
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

  public function testRegisterCreationAliasDelegatesToCore(): void {
    $alias = new RolesAlias($this->createStubUserCapability());
    $alias_capable_core = $this->createMock(AliasCapableCoreInterface::class);
    $alias_capable_core->expects($this->once())->method('registerCreationAlias')->with($alias);

    $backend = $this->createBackendWithCore($alias_capable_core);

    $backend->registerCreationAlias($alias);
  }

  public function testRegisterCreationAliasThrowsForNonAliasCore(): void {
    $backend = $this->createBackendWithCore($this->createMock(CoreInterface::class));

    $this->expectException(UnsupportedBackendActionException::class);
    $this->expectExceptionMessageMatches('/^Creation aliases are not supported by ' . preg_quote(DrupalBackend::class, '/') . ': its Core \S+ does not implement ' . preg_quote(CreationAliasCapabilityInterface::class, '/') . '\.$/');

    $backend->registerCreationAlias(new RolesAlias($this->createStubUserCapability()));
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
