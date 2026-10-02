<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend;

use DrevOps\BehatSteps\Backend\Alias\CreationAliasInterface;
use DrevOps\BehatSteps\Backend\Alias\RolesAlias;
use DrevOps\BehatSteps\Backend\Capability\CreationAliasCapabilityInterface;
use DrevOps\BehatSteps\Backend\DrushBackend;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests creation-alias discovery on 'DrushBackend'.
 *
 * Drush owns 'RolesAlias' (post-create) and nothing else by default.
 */
#[CoversClass(DrushBackend::class)]
#[Group('backends')]
#[Group('drush')]
#[Group('aliases')]
class DrushBackendCreationAliasesTest extends TestCase {

  /**
   * Tests that DrushBackend implements the opt-in capability interface.
   */
  public function testImplementsCreationAliasCapability(): void {
    $this->assertContains(CreationAliasCapabilityInterface::class, (array) class_implements(DrushBackend::class));
  }

  /**
   * Tests that 'roles' is registered for the user entity type.
   */
  public function testRolesAliasRegisteredForUser(): void {
    $backend = new DrushBackend('test-alias');

    $aliases = $backend->getCreationAliases('user');

    $this->assertArrayHasKey('roles', $aliases);
    $this->assertInstanceOf(RolesAlias::class, $aliases['roles']);
  }

  /**
   * Tests that Drush ships no node/term aliases by default.
   */
  public function testNoContentAliasesByDefault(): void {
    $backend = new DrushBackend('test-alias');

    $this->assertSame([], $backend->getCreationAliases('node'));
    $this->assertSame([], $backend->getCreationAliases('taxonomy_term'));
  }

  /**
   * Tests that 'getCreationAliases()' returns '[]' for unknown entity types.
   */
  public function testGetCreationAliasesReturnsEmptyForUnknown(): void {
    $backend = new DrushBackend('test-alias');

    $this->assertSame([], $backend->getCreationAliases('unknown_type'));
  }

  /**
   * Tests that re-registering an alias replaces the previous instance.
   */
  public function testRegisterCreationAliasReplacesByName(): void {
    $backend = new DrushBackend('test-alias');

    $replacement = new class implements CreationAliasInterface {

      /**
       * {@inheritdoc}
       */
      public function getName(): string {
        return 'roles';
      }

      /**
       * {@inheritdoc}
       */
      public function getEntityType(): string {
        return 'user';
      }

      /**
       * {@inheritdoc}
       */
      public function getDescription(): string {
        return 'Override.';
      }

    };

    $backend->registerCreationAlias($replacement);

    $aliases = $backend->getCreationAliases('user');

    $this->assertSame($replacement, $aliases['roles']);
  }

}
