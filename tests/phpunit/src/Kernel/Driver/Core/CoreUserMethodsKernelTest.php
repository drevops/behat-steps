<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Driver\Core;

use DrevOps\BehatSteps\Driver\Core\Core;
use DrevOps\BehatSteps\Driver\Entity\EntityStub;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for user-related methods on Core.
 *
 * The whole lifecycle runs inside one test method: KernelTestBase's setUp
 * runs per-method and costs roughly a second of bootstrap. Bundling closely
 * related assertions keeps CI time down without sacrificing coverage.
 *
 * A method is split out only when a scenario needs its own clean state, such
 * as a failure path that leaves the container dirty.
 *
 * To actually run this test, see the bootstrap/env notes in
 * DatetimeHandlerKernelTest.
 *
 * @group core
 */
#[CoversClass(Core::class)]
#[Group('core')]
#[RunTestsInSeparateProcesses]
class CoreUserMethodsKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = [
    'system',
    'user',
  ];

  /**
   * The Core driver under test.
   */
  protected Core $core;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    // users_data is used by user_cancel's batch callback; required for
    // synchronous userDelete to complete without hitting a missing table.
    $this->installSchema('user', ['users_data']);
    $this->installConfig(['user']);

    // Core's bootstrap() is not called: KernelTestBase has already booted the
    // kernel, so the API methods are exercised directly.
    $this->core = new Core($this->root);
  }

  /**
   * Tests the full user/role lifecycle in one bundled method.
   */
  public function testUserLifecycle(): void {
    $user_stub = new EntityStub('user', NULL, [
      'name' => 'alice',
      'mail' => 'alice@example.com',
      'pass' => 'correcthorsebatterystaple',
    ]);
    $this->core->userCreate($user_stub);

    $this->assertNotEmpty($user_stub->getValue('uid'), 'userCreate populated uid.');
    $this->assertTrue($user_stub->isSaved(), 'userCreate marked the stub saved.');
    $account = User::load($user_stub->getValue('uid'));
    $this->assertInstanceOf(User::class, $account);
    $this->assertSame('alice', $account->getAccountName());
    $this->assertSame(1, (int) $account->get('status')->value);

    // 'access user profiles' is provided by the user module enabled here, so
    // checkPermissions() can validate it in isolation without pulling in node.
    $permission = 'access user profiles';
    $role_id = $this->core->roleCreate([$permission]);
    $role = Role::load($role_id);
    $this->assertInstanceOf(Role::class, $role);
    $this->assertTrue($role->hasPermission($permission));

    $this->core->userAddRole($user_stub, $role_id);
    $account = User::load($user_stub->getValue('uid'));
    $this->assertContains($role_id, $account->getRoles());

    $this->core->userDelete($user_stub);
    $this->assertNull(\Drupal::entityTypeManager()->getStorage('user')->loadUnchanged($user_stub->getValue('uid')));

    $this->core->roleDelete($role_id);
    $this->assertNull(Role::load($role_id));
  }

  /**
   * Tests that 'userAddRole()' throws when the role name is unknown.
   */
  public function testUserAddRoleThrowsOnUnknownRole(): void {
    $stub = new EntityStub('user', NULL, [
      'name' => 'ghost',
      'mail' => 'ghost@example.com',
      'pass' => 'pw',
    ]);
    $this->core->userCreate($stub);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessageMatches('/No role "nonexistent-role" exists/');

    $this->core->userAddRole($stub, 'nonexistent-role');
  }

  /**
   * Tests that 'userAddRole()' throws when the stub's uid matches no account.
   */
  public function testUserAddRoleThrowsOnUnknownUser(): void {
    $role_id = $this->core->roleCreate(['access user profiles']);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessageMatches('/No user with id "999999" exists/');

    $this->core->userAddRole(new EntityStub('user', NULL, ['uid' => 999999]), $role_id);
  }

  /**
   * Tests that roleCreate rejects unknown permission strings.
   */
  public function testRoleCreateRejectsUnknownPermission(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Invalid permission "definitely not a real permission"');

    $this->core->roleCreate(['definitely not a real permission']);
  }

  /**
   * Tests 'roleCreate()' honours explicit id and label arguments.
   */
  public function testRoleCreateAcceptsExplicitIdAndLabel(): void {
    $role_id = $this->core->roleCreate(['access user profiles'], 'editor', 'Editor');

    $this->assertSame('editor', $role_id);
    $role = Role::load('editor');
    $this->assertInstanceOf(Role::class, $role);
    $this->assertSame('Editor', $role->label());
    $this->assertTrue($role->hasPermission('access user profiles'));
  }

  /**
   * Tests 'roleCreate()' falls back to the id as label when only id is given.
   */
  public function testRoleCreateFallsBackToIdAsLabel(): void {
    $role_id = $this->core->roleCreate([], 'content_editor');

    $this->assertSame('content_editor', $role_id);
    $role = Role::load('content_editor');
    $this->assertInstanceOf(Role::class, $role);
    $this->assertSame('content_editor', $role->label());
  }

  /**
   * Tests that 'userCreate()' honours the 'roles' creation alias.
   */
  public function testUserCreateAppliesRolesAlias(): void {
    $role_id = $this->core->roleCreate(['access user profiles'], 'editor');

    $stub = new EntityStub('user', NULL, [
      'name' => 'roleuser',
      'mail' => 'role@example.com',
      'pass' => 'pw',
      'roles' => [$role_id],
    ]);

    $this->core->userCreate($stub);

    $account = User::load($stub->getValue('uid'));
    $this->assertInstanceOf(User::class, $account);
    $this->assertContains($role_id, $account->getRoles());
  }

}
