<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Manager;

use DrevOps\BehatSteps\Behat\Manager\UserManager;
use DrevOps\BehatSteps\Behat\Manager\UserManagerInterface;
use DrevOps\BehatSteps\Driver\Entity\EntityStub;
use DrevOps\BehatSteps\Driver\Entity\EntityStubInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests the register of users a scenario created.
 */
#[CoversClass(UserManager::class)]
class UserManagerTest extends TestCase {

  public function testImplementsInterface(): void {
    $manager = new UserManager();

    $this->assertInstanceOf(UserManagerInterface::class, $manager);
  }

  public function testCurrentUserDefaultsToFalse(): void {
    $manager = new UserManager();

    $this->assertFalse($manager->getCurrentUser());
  }

  public function testSetAndGetCurrentUser(): void {
    $manager = new UserManager();
    $user = self::userStub(['name' => 'admin']);

    $manager->setCurrentUser($user);

    $this->assertSame($user, $manager->getCurrentUser());
  }

  public function testSetCurrentUserToFalse(): void {
    $manager = new UserManager();
    $manager->setCurrentUser(self::userStub(['name' => 'admin']));

    $manager->setCurrentUser(FALSE);

    $this->assertFalse($manager->getCurrentUser());
  }

  public function testAddAndGetUser(): void {
    $manager = new UserManager();
    $user = self::userStub(['name' => 'editor']);

    $manager->addUser($user);

    $this->assertSame($user, $manager->getUser('editor'));
  }

  public function testGetUserThrowsForUnknown(): void {
    $manager = new UserManager();

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('No user with ghost name is registered with the driver.');

    $manager->getUser('ghost');
  }

  public function testRemoveUser(): void {
    $manager = new UserManager();
    $manager->addUser(self::userStub(['name' => 'editor']));

    $manager->removeUser('editor');

    $this->expectException(\InvalidArgumentException::class);
    $manager->getUser('editor');
  }

  public function testGetUsersReturnsAll(): void {
    $manager = new UserManager();
    $user_a = self::userStub(['name' => 'alice']);
    $user_b = self::userStub(['name' => 'bob']);
    $manager->addUser($user_a);
    $manager->addUser($user_b);

    $users = $manager->getUsers();

    $this->assertCount(2, $users);
    $this->assertSame($user_a, $users['alice']);
    $this->assertSame($user_b, $users['bob']);
  }

  public function testGetUsersReturnsEmptyByDefault(): void {
    $manager = new UserManager();

    $this->assertSame([], $manager->getUsers());
  }

  public function testClearUsers(): void {
    $manager = new UserManager();
    $manager->setCurrentUser(self::userStub(['name' => 'admin']));
    $manager->addUser(self::userStub(['name' => 'editor']));

    $manager->clearUsers();

    $this->assertFalse($manager->getCurrentUser());
    $this->assertSame([], $manager->getUsers());
  }

  /**
   * Tests whether the manager reports holding any users.
   *
   * @param array<int, \DrevOps\BehatSteps\Driver\Entity\EntityStubInterface> $users
   *   Users to add to the manager.
   * @param bool $expected
   *   Expected hasUsers() result.
   */
  #[DataProvider('dataProviderHasUsers')]
  public function testHasUsers(array $users, bool $expected): void {
    $manager = new UserManager();
    foreach ($users as $user) {
      $manager->addUser($user);
    }

    $this->assertSame($expected, $manager->hasUsers());
  }

  public static function dataProviderHasUsers(): \Iterator {
    yield 'no users' => [[], FALSE];
    yield 'one user' => [[self::userStub(['name' => 'alice'])], TRUE];
    yield 'multiple users' => [[self::userStub(['name' => 'alice']), self::userStub(['name' => 'bob'])], TRUE];
  }

  #[DataProvider('dataProviderCurrentUserIsAnonymous')]
  public function testCurrentUserIsAnonymous(EntityStubInterface|false $user, bool $expected): void {
    $manager = new UserManager();
    $manager->setCurrentUser($user);

    $this->assertSame($expected, $manager->currentUserIsAnonymous());
  }

  public static function dataProviderCurrentUserIsAnonymous(): \Iterator {
    yield 'false is anonymous' => [FALSE, TRUE];
    yield 'user stub is not anonymous' => [self::userStub(['name' => 'admin']), FALSE];
  }

  #[DataProvider('dataProviderCurrentUserHasRole')]
  public function testCurrentUserHasRole(EntityStubInterface|false $user, string $role, bool $expected): void {
    $manager = new UserManager();
    $manager->setCurrentUser($user);

    $this->assertSame($expected, $manager->currentUserHasRole($role));
  }

  public static function dataProviderCurrentUserHasRole(): \Iterator {
    yield 'anonymous has no role' => [FALSE, 'admin', FALSE];
    yield 'user without role property' => [self::userStub(['name' => 'alice']), 'editor', FALSE];
    yield 'user with matching role' => [self::userStub(['name' => 'alice', 'role' => 'editor']), 'editor', TRUE];
    yield 'user with non-matching role' => [self::userStub(['name' => 'alice', 'role' => 'editor']), 'admin', FALSE];
    yield 'user with empty role' => [self::userStub(['name' => 'alice', 'role' => '']), 'editor', FALSE];
    yield 'query is empty' => [self::userStub(['name' => 'alice', 'role' => 'editor']), '', FALSE];
    yield 'one of several held roles' => [self::userStub(['name' => 'alice', 'role' => 'editor, reviewer']), 'reviewer', TRUE];
    yield 'every queried role is held' => [self::userStub(['name' => 'alice', 'role' => 'editor, reviewer']), 'reviewer,editor', TRUE];
    yield 'one queried role is missing' => [self::userStub(['name' => 'alice', 'role' => 'editor, reviewer']), 'editor, admin', FALSE];
    yield 'whitespace around a role is ignored' => [self::userStub(['name' => 'alice', 'role' => ' editor ']), ' editor ', TRUE];
  }

  /**
   * Builds a user stub with the given values.
   *
   * @param array<string, mixed> $values
   *   The values to seed the stub with.
   */
  protected static function userStub(array $values): EntityStubInterface {
    return new EntityStub('user', NULL, $values);
  }

}
