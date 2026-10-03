<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Core;

use DrevOps\BehatSteps\Backend\Core\Core;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
   * The Core backend under test.
   */
  protected Core $core;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    // users_data is used by user_cancel's batch callback; without it a
    // synchronous userDelete fails with a missing-table error.
    $this->installSchema('user', ['users_data']);
    $this->installConfig(['user']);

    // Core's bootstrap() is not called: KernelTestBase has already booted the
    // kernel, so the API methods are exercised directly.
    $this->core = new Core($this->root);
  }

  /**
   * Tests the full user/role lifecycle in 1 bundled method.
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

  public function testUserAddRoleThrowsOnUnknownUser(): void {
    $role_id = $this->core->roleCreate(['access user profiles']);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessageMatches('/No user with id "999999" exists/');

    $this->core->userAddRole(new EntityStub('user', NULL, ['uid' => 999999]), $role_id);
  }

  public function testRoleCreateRejectsUnknownPermission(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Invalid permission "definitely not a real permission"');

    $this->core->roleCreate(['definitely not a real permission']);
  }

  /**
   * Tests that clearing caches discards the permission list read earlier.
   *
   * @param string $method
   *   The cache clearing method to call.
   */
  #[DataProvider('dataProviderClearingCachesForgetsThePermissionList')]
  public function testClearingCachesForgetsThePermissionList(string $method): void {
    $this->core->roleCreate(['access user profiles']);
    $this->enableModules(['block']);

    $this->core->{$method}();

    $role = Role::load($this->core->roleCreate(['administer blocks']));
    $this->assertInstanceOf(Role::class, $role);
    $this->assertTrue($role->hasPermission('administer blocks'));
  }

  public static function dataProviderClearingCachesForgetsThePermissionList(): \Iterator {
    yield 'the static caches' => ['cacheClearStatic'];
    yield 'every cache' => ['cacheClear'];
  }

  public function testModuleInstallForgetsThePermissionList(): void {
    $this->core->roleCreate(['access user profiles']);

    $this->core->moduleInstall('block');

    $role = Role::load($this->core->roleCreate(['administer blocks']));
    $this->assertInstanceOf(Role::class, $role);
    $this->assertTrue($role->hasPermission('administer blocks'));
  }

  public function testModuleUninstallForgetsThePermissionList(): void {
    $this->core->moduleInstall('block');
    $this->core->roleCreate(['access user profiles']);

    $this->core->moduleUninstall('block');

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Invalid permission "administer blocks".');

    $this->core->roleCreate(['administer blocks']);
  }

  public function testRoleCreateAcceptsExplicitIdAndLabel(): void {
    $role_id = $this->core->roleCreate(['access user profiles'], 'editor', 'Editor');

    $this->assertSame('editor', $role_id);
    $role = Role::load('editor');
    $this->assertInstanceOf(Role::class, $role);
    $this->assertSame('Editor', $role->label());
    $this->assertTrue($role->hasPermission('access user profiles'));
  }

  public function testRoleCreateFallsBackToIdAsLabel(): void {
    $role_id = $this->core->roleCreate([], 'content_editor');

    $this->assertSame('content_editor', $role_id);
    $role = Role::load('content_editor');
    $this->assertInstanceOf(Role::class, $role);
    $this->assertSame('content_editor', $role->label());
  }

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
