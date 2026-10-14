<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Steps\Drupal;

use DrevOps\BehatSteps\Extension\Registry\UserRegistry;
use DrevOps\BehatSteps\Steps\Drupal\UserTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for finding, changing and deleting users through 'UserTrait'.
 */
#[CoversTrait(UserTrait::class)]
#[Group('behat')]
#[RunTestsInSeparateProcesses]
class UserTraitKernelTest extends StepTraitKernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = ['system', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installConfig(['user']);
  }

  public function testLoadMultipleLoadsTheMatchingUsers(): void {
    $first = $this->createUser('first', 1);
    $second = $this->createUser('second', 1);
    $this->createUser('blocked', 0);

    $users = $this->context->userLoadMultiple(['status' => '1']);

    $this->assertStubSet([$first, $second], $users, 'uid');
  }

  public function testLoadMultipleReturnsAnEmptyArrayWhenNothingMatches(): void {
    $this->createUser('first', 1);

    $this->assertSame([], $this->context->userLoadMultiple(['name' => 'missing']));
  }

  public function testGetByNameReturnsTheStubOfTheUser(): void {
    $user = $this->createUser('jane', 1);

    $stub = $this->context->userGetByName('jane');

    $this->assertSame('user', $stub->getEntityType());
    $this->assertSame((string) $user->id(), $stub->getValue('uid'));
  }

  public function testGetByNameFailsForMissingUser(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The user "missing" does not exist.');

    $this->context->userGetByName('missing');
  }

  public function testSetPasswordStoresTheNewPassword(): void {
    $user = $this->createUser('jane', 1);

    $this->context->userSetPassword('jane', 'new-password');

    $this->assertTrue(\Drupal::service('password')->check('new-password', $this->reloadUser($user)->getPassword()));
  }

  public function testSetLastAccessAndLoginTimesStoreTheTimestamps(): void {
    $user = $this->createUser('jane', 1);

    $this->context->userSetLastAccessTime('jane', '1732319174');
    $this->context->userSetLastLoginTime('jane', 'Friday, 22 November 2024 13:46:14 UTC');

    $reloaded = $this->reloadUser($user);
    $this->assertSame('1732319174', (string) $reloaded->getLastAccessedTime());
    $this->assertSame((string) strtotime('Friday, 22 November 2024 13:46:14 UTC'), (string) $reloaded->getLastLoginTime());
  }

  /**
   * Tests that the role assertions pass on the roles the user holds.
   *
   * A failing assertion reports through the Mink session, which a kernel
   * test has none of, so the failures are covered by the Behat scenarios.
   */
  public function testRoleAssertionsPassOnTheStoredRoles(): void {
    Role::create(['id' => 'editor', 'label' => 'Editor'])->save();
    Role::create(['id' => 'approver', 'label' => 'Approver'])->save();
    $this->createUser('jane', 1, ['editor']);

    $this->context->userAssertHasRoles('jane', 'authenticated, editor');
    $this->context->userAssertNotHasRoles('jane', 'approver');

    $this->addToAssertionCount(2);
  }

  /**
   * Tests that the blocked assertions pass on the status of the account.
   *
   * A failing assertion reports through the Mink session, which a kernel
   * test has none of, so the failures are covered by the Behat scenarios.
   */
  public function testBlockedAssertionsPassOnTheStatus(): void {
    $this->createUser('active', 1);
    $this->createUser('blocked', 0);

    $this->context->userAssertNotBlocked('active');
    $this->context->userAssertBlocked('blocked');

    $this->addToAssertionCount(2);
  }

  public function testDeleteRemovesTheUsersAndDropsThemFromTheRegistry(): void {
    $registry = new UserRegistry();
    $this->context->authSetUserRegistry($registry);

    $deleted = $this->createUser('jane', 1, [], 'jane@example.com');
    $kept = $this->createUser('john', 1, [], 'john@example.com');
    $registry->addUser($this->context->userGetByName('jane')->setValue('name', 'jane'));

    $this->context->userDelete(['mail' => 'jane@example.com']);

    $this->assertNull(User::load($deleted->id()));
    $this->assertInstanceOf(UserInterface::class, User::load($kept->id()));
    $this->assertFalse($registry->hasUsers(), 'The deleted user was dropped from the registry.');
  }

  public function testCreateRoleGrantsThePermissions(): void {
    $this->context->userCreateRole('Editor', 'access user profiles, change own username');

    $role = Role::load('editor');
    $this->assertInstanceOf(Role::class, $role);
    $this->assertSame('Editor', $role->label());
    $this->assertSame(['access user profiles', 'change own username'], $role->getPermissions());
  }

  public function testCreateRoleWithoutPermissionsGrantsNothing(): void {
    $created = $this->context->userCreateRoleWithPermissions('Limited Editor', ['']);

    $this->assertSame('limited editor', $created->getValue('id'));

    $role = Role::load('limited editor');
    $this->assertInstanceOf(Role::class, $role);
    $this->assertSame('Limited Editor', $role->label());
    $this->assertSame([], $role->getPermissions());
  }

  public function testCreateRoleReplacesTheRoleWithTheSameId(): void {
    Role::create(['id' => 'editor', 'label' => 'Old editor'])->grantPermission('access user profiles')->save();

    $this->context->userCreateRole('Editor', 'change own username');

    $role = Role::load('editor');
    $this->assertInstanceOf(Role::class, $role);
    $this->assertSame('Editor', $role->label());
    $this->assertSame(['change own username'], $role->getPermissions());
  }

  /**
   * Creates a user.
   *
   * @param string $name
   *   The user name.
   * @param int $status
   *   The account status: 1 when active, 0 when blocked.
   * @param array<int, string> $roles
   *   The role IDs.
   * @param string|null $mail
   *   The email address, or NULL for none.
   */
  protected function createUser(string $name, int $status, array $roles = [], ?string $mail = NULL): UserInterface {
    $user = User::create(['name' => $name, 'status' => $status, 'roles' => $roles, 'mail' => $mail]);
    $user->save();

    return $user;
  }

  /**
   * Loads a user again, bypassing the static cache.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user.
   */
  protected function reloadUser(UserInterface $user): UserInterface {
    $reloaded = \Drupal::entityTypeManager()->getStorage('user')->loadUnchanged($user->id());
    $this->assertInstanceOf(UserInterface::class, $reloaded);

    return $reloaded;
  }

}
