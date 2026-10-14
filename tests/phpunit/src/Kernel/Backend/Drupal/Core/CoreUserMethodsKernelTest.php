<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Drupal\Core;

use DrevOps\BehatSteps\Backend\Drupal\Core\Core;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\OneTimeAuthentication;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for user-related methods on Core.
 *
 * The whole lifecycle runs inside 1 test method: KernelTestBase's setUp()
 * runs per method and costs about 1 second of bootstrap. Bundling closely
 * related assertions keeps CI time down without sacrificing coverage.
 *
 * A method is split out only when a scenario needs its own clean state, such
 * as a failure path that leaves the container dirty.
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
    // synchronous deleteUser fails with a missing-table error.
    $this->installSchema('user', ['users_data']);
    $this->installConfig(['user']);

    // Core's bootstrap() is not called: KernelTestBase has already booted the
    // kernel, so the API methods are exercised directly.
    $this->core = new Core($this->root);
  }

  public function testUserLifecycle(): void {
    $user_stub = new EntityStub('user', NULL, [
      'name' => 'alice',
      'mail' => 'alice@example.com',
      'pass' => 'correcthorsebatterystaple',
    ]);
    $this->assertSame($user_stub, $this->core->createUser($user_stub));

    $this->assertNotEmpty($user_stub->getValue('uid'), 'createUser populated uid.');
    $this->assertTrue($user_stub->isSaved(), 'createUser marked the stub saved.');
    $account = User::load($user_stub->getValue('uid'));
    $this->assertInstanceOf(User::class, $account);
    $this->assertSame('alice', $account->getAccountName());
    $this->assertSame(1, (int) $account->get('status')->value);

    // 'access user profiles' is provided by the user module enabled here, so
    // checkPermissions() can validate it in isolation without pulling in node.
    $permission = 'access user profiles';
    $created = $this->core->createRole([$permission]);
    $role_id = (string) $created->getValue('id');
    $role = Role::load($role_id);
    $this->assertInstanceOf(Role::class, $role);
    $this->assertTrue($role->hasPermission($permission));
    $this->assertSame('user_role', $created->getEntityType());
    $this->assertSame($role->label(), $created->getValue('label'));
    $this->assertTrue($created->isSaved(), 'createRole marked the stub saved.');
    $saved_role = $created->getSavedEntity();
    $this->assertInstanceOf(Role::class, $saved_role);
    $this->assertSame($role_id, $saved_role->id());

    $this->core->addUserRole($user_stub, $role_id);
    $account = User::load($user_stub->getValue('uid'));
    $this->assertContains($role_id, $account->getRoles());

    $this->core->deleteUser($user_stub);
    $this->assertNull(\Drupal::entityTypeManager()->getStorage('user')->loadUnchanged($user_stub->getValue('uid')));

    $this->core->deleteRole($role_id);
    $this->assertNull(Role::load($role_id));
  }

  public function testAddUserRoleAssignsTheRoleByLabel(): void {
    $stub = new EntityStub('user', NULL, [
      'name' => 'labelled',
      'mail' => 'labelled@example.com',
      'pass' => 'pw',
    ]);
    $this->core->createUser($stub);
    $this->core->createRole([], 'content_editor', 'Content editor');

    $this->core->addUserRole($stub, 'Content editor');

    $account = User::load($stub->getValue('uid'));
    $this->assertInstanceOf(User::class, $account);
    $this->assertContains('content_editor', $account->getRoles());
  }

  public function testCreateRoleGrantsThePermissionGivenByLabel(): void {
    $role = Role::load($this->core->createRole(['View user information'])->getValue('id'));

    $this->assertInstanceOf(Role::class, $role);
    $this->assertSame(['access user profiles'], $role->getPermissions());
  }

  public function testAddUserRoleThrowsOnUnknownRole(): void {
    $stub = new EntityStub('user', NULL, [
      'name' => 'ghost',
      'mail' => 'ghost@example.com',
      'pass' => 'pw',
    ]);
    $this->core->createUser($stub);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessageMatches('/No role "nonexistent-role" exists/');

    $this->core->addUserRole($stub, 'nonexistent-role');
  }

  public function testAddUserRoleThrowsOnUnknownUser(): void {
    $role_id = (string) $this->core->createRole(['access user profiles'])->getValue('id');

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessageMatches('/No user with id "999999" exists/');

    $this->core->addUserRole(new EntityStub('user', NULL, ['uid' => 999999]), $role_id);
  }

  public function testDeletesTolerateMissingTargets(): void {
    $this->core->deleteUser(new EntityStub('user', NULL, ['uid' => 999999]));
    $this->core->deleteRole('nonexistent-role');

    $this->assertNull(User::load(999999));
    $this->assertNull(Role::load('nonexistent-role'));
    $this->assertSame([], \Drupal::messenger()->messagesByType('error'), 'No error was reported for the missing user.');
  }

  public function testCreateRoleRejectsUnknownPermission(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Invalid permission "definitely not a real permission"');

    $this->core->createRole(['definitely not a real permission']);
  }

  /**
   * Tests that clearing caches discards the permission list read earlier.
   *
   * @param string $method
   *   The cache clearing method to call.
   */
  #[DataProvider('dataProviderClearingCachesForgetsThePermissionList')]
  public function testClearingCachesForgetsThePermissionList(string $method): void {
    $this->core->createRole(['access user profiles']);
    $this->enableModules(['block']);

    $this->core->{$method}();

    $role = Role::load($this->core->createRole(['administer blocks'])->getValue('id'));
    $this->assertInstanceOf(Role::class, $role);
    $this->assertTrue($role->hasPermission('administer blocks'));
  }

  public static function dataProviderClearingCachesForgetsThePermissionList(): \Iterator {
    yield 'the static caches' => ['cacheClearStatic'];
    yield 'every cache' => ['cacheClear'];
  }

  public function testModuleInstallForgetsThePermissionList(): void {
    $this->core->createRole(['access user profiles']);

    $this->core->moduleInstall('block');

    $role = Role::load($this->core->createRole(['administer blocks'])->getValue('id'));
    $this->assertInstanceOf(Role::class, $role);
    $this->assertTrue($role->hasPermission('administer blocks'));
  }

  public function testModuleUninstallForgetsThePermissionList(): void {
    $this->core->moduleInstall('block');
    $this->core->createRole(['access user profiles']);

    $this->core->moduleUninstall('block');

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Invalid permission "administer blocks".');

    $this->core->createRole(['administer blocks']);
  }

  public function testCreateRoleAcceptsExplicitIdAndLabel(): void {
    $created = $this->core->createRole(['access user profiles'], 'editor', 'Editor');

    $this->assertSame(['id' => 'editor', 'label' => 'Editor'], $created->getValues());
    $role = Role::load('editor');
    $this->assertInstanceOf(Role::class, $role);
    $this->assertSame('Editor', $role->label());
    $this->assertTrue($role->hasPermission('access user profiles'));
  }

  public function testCreateRoleFallsBackToIdAsLabel(): void {
    $created = $this->core->createRole([], 'content_editor');

    $this->assertSame(['id' => 'content_editor', 'label' => 'content_editor'], $created->getValues());
    $role = Role::load('content_editor');
    $this->assertInstanceOf(Role::class, $role);
    $this->assertSame('content_editor', $role->label());
  }

  public function testCreateUserAppliesRolesAlias(): void {
    $role_id = (string) $this->core->createRole(['access user profiles'], 'editor')->getValue('id');

    $stub = new EntityStub('user', NULL, [
      'name' => 'roleuser',
      'mail' => 'role@example.com',
      'pass' => 'pw',
      'roles' => [$role_id],
    ]);

    $this->core->createUser($stub);

    $account = User::load($stub->getValue('uid'));
    $this->assertInstanceOf(User::class, $account);
    $this->assertContains($role_id, $account->getRoles());
  }

  public function testGetOneTimeLoginUrlLinksToTheResetPage(): void {
    $stub = new EntityStub('user', NULL, ['name' => 'resetter', 'mail' => 'resetter@example.com', 'pass' => 'pw']);
    $this->core->createUser($stub);
    $account = User::load($stub->getValue('uid'));
    $this->assertInstanceOf(User::class, $account);

    $path = $this->core->getOneTimeLoginUrl(new EntityStub('user', NULL, ['uid' => $stub->getValue('uid')]));

    $this->assertMatchesRegularExpression('#^/user/reset/\d+/\d+/[^/]+$#', $path);
    [, , , $uid, $timestamp, $hash] = explode('/', $path);
    $this->assertSame((string) $account->id(), $uid);
    $this->assertSame(\Drupal::service(OneTimeAuthentication::class)->generateHmac($account, (int) $timestamp), $hash);
  }

  public function testGetOneTimeLoginUrlRejectsMissingUser(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessageMatches('/No user with id "999999" exists/');

    $this->core->getOneTimeLoginUrl(new EntityStub('user', NULL, ['uid' => 999999]));
  }

  public function testGetUserRolesReturnsTheRolesLedByAuthenticated(): void {
    $this->core->createRole([], 'editor', 'Editor');
    $stub = new EntityStub('user', NULL, ['name' => 'roles', 'mail' => 'roles@example.com', 'pass' => 'pw', 'roles' => ['editor']]);
    $this->core->createUser($stub);

    $this->assertSame(['authenticated', 'editor'], $this->core->getUserRoles(new EntityStub('user', NULL, ['uid' => $stub->getValue('uid')])));
  }

  public function testSetUserPasswordStoresThePassword(): void {
    $uid = $this->createAccount('password');

    $this->core->setUserPassword(new EntityStub('user', NULL, ['uid' => $uid]), 'new-password');

    $this->assertTrue(\Drupal::service('password')->check('new-password', $this->reloadAccount($uid)->getPassword()));
  }

  public function testSetUserLastAccessAndLoginTimesStoreTheTimestamps(): void {
    $uid = $this->createAccount('activity');
    $stub = new EntityStub('user', NULL, ['uid' => $uid]);

    $this->core->setUserLastAccessTime($stub, 1732319174);
    $this->core->setUserLastLoginTime($stub, 1732319175);

    $account = $this->reloadAccount($uid);
    $this->assertSame('1732319174', (string) $account->getLastAccessedTime());
    $this->assertSame('1732319175', (string) $account->getLastLoginTime());
  }

  /**
   * Tests that a user method rejects a stub naming no account.
   *
   * @param string $method
   *   The Core method to call.
   * @param array<int, mixed> $args
   *   The arguments after the stub.
   */
  #[DataProvider('dataProviderUserMethodsRejectMissingUser')]
  public function testUserMethodsRejectMissingUser(string $method, array $args): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('No user with id "999999" exists.');

    $this->core->{$method}(new EntityStub('user', NULL, ['uid' => 999999]), ...$args);
  }

  public static function dataProviderUserMethodsRejectMissingUser(): \Iterator {
    yield 'getUserRoles' => ['getUserRoles', []];
    yield 'setUserPassword' => ['setUserPassword', ['new-password']];
    yield 'setUserLastAccessTime' => ['setUserLastAccessTime', [1732319174]];
    yield 'setUserLastLoginTime' => ['setUserLastLoginTime', [1732319174]];
  }

  /**
   * Creates an account through Core.
   *
   * @param string $name
   *   The user name.
   *
   * @return int|string
   *   The user id.
   */
  protected function createAccount(string $name): int|string {
    $stub = new EntityStub('user', NULL, ['name' => $name, 'mail' => $name . '@example.com', 'pass' => 'pw']);
    $this->core->createUser($stub);

    $uid = $stub->getValue('uid');
    $this->assertTrue(is_int($uid) || is_string($uid));

    return $uid;
  }

  /**
   * Loads an account again, bypassing the static cache.
   *
   * @param int|string $uid
   *   The user id.
   */
  protected function reloadAccount(int|string $uid): User {
    $account = \Drupal::entityTypeManager()->getStorage('user')->loadUnchanged($uid);
    $this->assertInstanceOf(User::class, $account);

    return $account;
  }

}
