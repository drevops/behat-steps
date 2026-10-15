<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Drupal;

use ConsumerProject\Backend\Alias\LoginAlias;
use DrevOps\BehatSteps\Backend\Alias\CreationAliasInterface;
use DrevOps\BehatSteps\Backend\Alias\PostCreateAliasInterface;
use DrevOps\BehatSteps\Backend\Alias\PreCreateAliasInterface;
use DrevOps\BehatSteps\Backend\Alias\RolesAlias;
use DrevOps\BehatSteps\Backend\Capability\CreationAliasCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\DrushBackend;
use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests creation-alias discovery and registration on 'DrushBackend'.
 */
#[CoversClass(DrushBackend::class)]
#[Group('backends')]
#[Group('drush')]
#[Group('aliases')]
class DrushBackendCreationAliasesTest extends UnitTestCase {

  public function testImplementsCreationAliasCapability(): void {
    $this->assertContains(CreationAliasCapabilityInterface::class, (array) class_implements(DrushBackend::class));
  }

  public function testRolesAliasRegisteredForUser(): void {
    $backend = new DrushBackend('test-alias');

    $aliases = $backend->getCreationAliases('user');

    $this->assertArrayHasKey('roles', $aliases);
    $this->assertInstanceOf(RolesAlias::class, $aliases['roles']);
  }

  public function testNoContentAliasesByDefault(): void {
    $backend = new DrushBackend('test-alias');

    $this->assertSame([], $backend->getCreationAliases('node'));
    $this->assertSame([], $backend->getCreationAliases('taxonomy_term'));
  }

  public function testGetCreationAliasesReturnsEmptyForUnknown(): void {
    $backend = new DrushBackend('test-alias');

    $this->assertSame([], $backend->getCreationAliases('unknown_type'));
  }

  public function testRegisterCreationAliasReplacesByName(): void {
    $backend = new DrushBackend('test-alias');

    $replacement = new class implements PostCreateAliasInterface {

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

      /**
       * {@inheritdoc}
       */
      public function applyAfterCreate(EntityStubInterface $stub, object $entity): void {}

    };

    $backend->registerCreationAlias($replacement);

    $aliases = $backend->getCreationAliases('user');

    $this->assertSame($replacement, $aliases['roles']);
  }

  public function testRegisterCreationAliasRegistersAnotherEntityType(): void {
    $backend = new DrushBackend('test-alias');
    $alias = new LoginAlias();

    $backend->registerCreationAlias($alias);

    $this->assertSame(['roles', 'login'], array_keys($backend->getCreationAliases('user')));
    $this->assertSame($alias, $backend->getCreationAliases('user')['login']);
  }

  public function testRegisterCreationAliasRejectsAliasThatNeverRuns(): void {
    $backend = new DrushBackend('test-alias');

    $alias = new class implements CreationAliasInterface {

      /**
       * {@inheritdoc}
       */
      public function getName(): string {
        return 'department';
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
        return 'Neither before nor after creation.';
      }

    };

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessageMatches('/^Creation alias class "' . preg_quote(CreationAliasInterface::class, '/') . '@anonymous.+" must implement "' . preg_quote(PreCreateAliasInterface::class, '/') . '" or "' . preg_quote(PostCreateAliasInterface::class, '/') . '"\.$/s');

    $backend->registerCreationAlias($alias);
  }

}
