<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Alias;

use DrevOps\BehatSteps\Backend\Alias\PostCreateAliasInterface;
use DrevOps\BehatSteps\Backend\Alias\RolesAlias;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Tests\Unit\Backend\Fixtures\RecordingUserCapability;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the 'RolesAlias' creation alias.
 */
#[CoversClass(RolesAlias::class)]
#[Group('aliases')]
class RolesAliasTest extends TestCase {

  public function testMetadataAccessors(): void {
    $alias = new RolesAlias(new RecordingUserCapability());

    $this->assertInstanceOf(PostCreateAliasInterface::class, $alias);
    $this->assertSame('roles', $alias->getName());
    $this->assertSame('user', $alias->getEntityType());
    $this->assertNotSame('', $alias->getDescription());
  }

  /**
   * Tests that every entry in 'roles' triggers a 'userAddRole()' call.
   */
  public function testApplyAfterCreateAssignsEachRole(): void {
    $backend = new RecordingUserCapability();
    $alias = new RolesAlias($backend);

    $stub = new EntityStub('user', NULL, ['name' => 'bob', 'roles' => ['editor', 'reviewer']]);
    $entity = new \stdClass();

    $alias->applyAfterCreate($stub, $entity);

    $this->assertSame(['editor', 'reviewer'], $backend->roles);
  }

  /**
   * Tests that non-array 'roles' values no-op without errors.
   *
   * @param mixed $roles
   *   The 'roles' value placed on the stub.
   */
  #[DataProvider('dataProviderApplyAfterCreateIgnoresNonArrayValues')]
  public function testApplyAfterCreateIgnoresNonArrayValues(mixed $roles): void {
    $backend = new RecordingUserCapability();
    $alias = new RolesAlias($backend);

    $stub = new EntityStub('user', NULL, ['name' => 'bob', 'roles' => $roles]);
    $entity = new \stdClass();

    $alias->applyAfterCreate($stub, $entity);

    $this->assertSame([], $backend->roles, 'No role assignment should occur for non-array values.');
  }

  /**
   * Data provider for 'testApplyAfterCreateIgnoresNonArrayValues()'.
   *
   * @return \Iterator<string, array<int, mixed>>
   *   Cases of non-array 'roles' value.
   */
  public static function dataProviderApplyAfterCreateIgnoresNonArrayValues(): \Iterator {
    yield 'string' => ['editor'];
    yield 'integer' => [42];
    yield 'null' => [NULL];
    yield 'boolean false' => [FALSE];
  }

  public function testApplyAfterCreateNoOpsOnEmptyArray(): void {
    $backend = new RecordingUserCapability();
    $alias = new RolesAlias($backend);

    $stub = new EntityStub('user', NULL, ['name' => 'bob', 'roles' => []]);
    $entity = new \stdClass();

    $alias->applyAfterCreate($stub, $entity);

    $this->assertSame([], $backend->roles);
  }

}
