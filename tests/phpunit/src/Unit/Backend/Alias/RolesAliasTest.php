<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Alias;

use DrevOps\BehatSteps\Backend\Alias\PostCreateAliasInterface;
use DrevOps\BehatSteps\Backend\Alias\RolesAlias;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Backend\Exception\CreationAliasResolutionException;
use DrevOps\BehatSteps\Tests\Unit\Backend\Fixtures\RecordingUserCapability;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the 'RolesAlias' creation alias.
 */
#[CoversClass(RolesAlias::class)]
#[Group('aliases')]
class RolesAliasTest extends UnitTestCase {

  public function testMetadataAccessors(): void {
    $alias = new RolesAlias(new RecordingUserCapability());

    $this->assertInstanceOf(PostCreateAliasInterface::class, $alias);
    $this->assertSame('roles', $alias->getName());
    $this->assertSame('user', $alias->getEntityType());
    $this->assertNotSame('', $alias->getDescription());
  }

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

  public function testApplyAfterCreateUnwrapsResolvedRecordsAndTrims(): void {
    $backend = new RecordingUserCapability();
    $alias = new RolesAlias($backend);

    $stub = new EntityStub('user', NULL, ['name' => 'bob', 'roles' => [['target_id' => 'editor'], ' reviewer ']]);

    $alias->applyAfterCreate($stub, new \stdClass());

    $this->assertSame(['editor', 'reviewer'], $backend->roles);
  }

  /**
   * Tests that a 'roles' entry that names no role is rejected.
   *
   * @param mixed $role
   *   The entry placed in the 'roles' list.
   * @param string $expected_message
   *   The message the alias is expected to throw with.
   */
  #[DataProvider('dataProviderApplyAfterCreateRejectsUnusableEntry')]
  public function testApplyAfterCreateRejectsUnusableEntry(mixed $role, string $expected_message): void {
    $backend = new RecordingUserCapability();
    $alias = new RolesAlias($backend);

    $stub = new EntityStub('user', NULL, ['name' => 'bob', 'roles' => [$role]]);

    try {
      $alias->applyAfterCreate($stub, new \stdClass());
      $this->fail('Expected CreationAliasResolutionException.');
    }
    catch (CreationAliasResolutionException $exception) {
      $this->assertSame($expected_message, $exception->getMessage());
      $this->assertSame([], $backend->roles, 'No role should be assigned when an entry is rejected.');
    }
  }

  public static function dataProviderApplyAfterCreateRejectsUnusableEntry(): \Iterator {
    yield 'record without an id' => [['label' => 'Editor'], "Cannot assign role because one of the 'roles' entries is not a scalar or stringable value."];
    yield 'object' => [new \stdClass(), "Cannot assign role because one of the 'roles' entries is not a scalar or stringable value."];
    yield 'whitespace' => ['   ', "Cannot assign role because one of the 'roles' entries is empty after trimming."];
    yield 'record with an empty id' => [['target_id' => ''], "Cannot assign role because one of the 'roles' entries is empty after trimming."];
  }

}
