<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Registry;

use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;
use DrevOps\BehatSteps\Behat\Registry\UserRegistry;
use DrevOps\BehatSteps\Behat\Registry\UserRegistryInterface;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests the register of users a scenario created.
 */
#[CoversClass(UserRegistry::class)]
class UserRegistryTest extends UnitTestCase {

  public function testImplementsInterface(): void {
    $registry = new UserRegistry();

    $this->assertInstanceOf(UserRegistryInterface::class, $registry);
  }

  public function testCurrentUserDefaultsToFalse(): void {
    $registry = new UserRegistry();

    $this->assertFalse($registry->getCurrentUser());
  }

  public function testSetAndGetCurrentUser(): void {
    $registry = new UserRegistry();
    $user = static::createUserStub(['name' => 'admin']);

    $registry->setCurrentUser($user);

    $this->assertSame($user, $registry->getCurrentUser());
  }

  public function testSetCurrentUserToFalse(): void {
    $registry = new UserRegistry();
    $registry->setCurrentUser(static::createUserStub(['name' => 'admin']));

    $registry->setCurrentUser(FALSE);

    $this->assertFalse($registry->getCurrentUser());
  }

  public function testAddAndGetUser(): void {
    $registry = new UserRegistry();
    $user = static::createUserStub(['name' => 'editor']);

    $registry->addUser($user);

    $this->assertSame($user, $registry->getUser('editor'));
  }

  public function testGetUserThrowsForUnknown(): void {
    $registry = new UserRegistry();

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('No user with the name "ghost" is registered with the backend.');

    $registry->getUser('ghost');
  }

  public function testRemoveUser(): void {
    $registry = new UserRegistry();
    $registry->addUser(static::createUserStub(['name' => 'editor']));

    $registry->removeUser('editor');

    $this->expectException(\RuntimeException::class);
    $registry->getUser('editor');
  }

  public function testGetUsersReturnsAll(): void {
    $registry = new UserRegistry();
    $user_a = static::createUserStub(['name' => 'alice']);
    $user_b = static::createUserStub(['name' => 'bob']);
    $registry->addUser($user_a);
    $registry->addUser($user_b);

    $users = $registry->getUsers();

    $this->assertCount(2, $users);
    $this->assertSame($user_a, $users['alice']);
    $this->assertSame($user_b, $users['bob']);
  }

  public function testGetUsersReturnsEmptyByDefault(): void {
    $registry = new UserRegistry();

    $this->assertSame([], $registry->getUsers());
  }

  public function testClearUsers(): void {
    $registry = new UserRegistry();
    $registry->setCurrentUser(static::createUserStub(['name' => 'admin']));
    $registry->addUser(static::createUserStub(['name' => 'editor']));

    $registry->clearUsers();

    $this->assertFalse($registry->getCurrentUser());
    $this->assertSame([], $registry->getUsers());
  }

  /**
   * Tests whether the registry reports holding any users.
   *
   * @param array<int, \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface> $users
   *   Users to add to the registry.
   * @param bool $expected
   *   Expected hasUsers() result.
   */
  #[DataProvider('dataProviderHasUsers')]
  public function testHasUsers(array $users, bool $expected): void {
    $registry = new UserRegistry();

    foreach ($users as $user) {
      $registry->addUser($user);
    }

    $this->assertSame($expected, $registry->hasUsers());
  }

  public static function dataProviderHasUsers(): \Iterator {
    yield 'no users' => [[], FALSE];
    yield 'one user' => [[static::createUserStub(['name' => 'alice'])], TRUE];
    yield 'multiple users' => [[static::createUserStub(['name' => 'alice']), static::createUserStub(['name' => 'bob'])], TRUE];
  }

  #[DataProvider('dataProviderCurrentUserIsAnonymous')]
  public function testCurrentUserIsAnonymous(EntityStubInterface|false $user, bool $expected): void {
    $registry = new UserRegistry();
    $registry->setCurrentUser($user);

    $this->assertSame($expected, $registry->currentUserIsAnonymous());
  }

  public static function dataProviderCurrentUserIsAnonymous(): \Iterator {
    yield 'false is anonymous' => [FALSE, TRUE];
    yield 'user stub is not anonymous' => [static::createUserStub(['name' => 'admin']), FALSE];
  }

  #[DataProvider('dataProviderCurrentUserHasRole')]
  public function testCurrentUserHasRole(EntityStubInterface|false $user, string $role, bool $expected): void {
    $registry = new UserRegistry();
    $registry->setCurrentUser($user);

    $this->assertSame($expected, $registry->currentUserHasRole($role));
  }

  public static function dataProviderCurrentUserHasRole(): \Iterator {
    yield 'anonymous has no role' => [FALSE, 'admin', FALSE];
    yield 'user without role property' => [static::createUserStub(['name' => 'alice']), 'editor', FALSE];
    yield 'user with matching role' => [static::createUserStub(['name' => 'alice', 'role' => 'editor']), 'editor', TRUE];
    yield 'user with non-matching role' => [static::createUserStub(['name' => 'alice', 'role' => 'editor']), 'admin', FALSE];
    yield 'user with empty role' => [static::createUserStub(['name' => 'alice', 'role' => '']), 'editor', FALSE];
    yield 'query is empty' => [static::createUserStub(['name' => 'alice', 'role' => 'editor']), '', FALSE];
    yield 'one of several held roles' => [static::createUserStub(['name' => 'alice', 'role' => 'editor, reviewer']), 'reviewer', TRUE];
    yield 'every queried role is held' => [static::createUserStub(['name' => 'alice', 'role' => 'editor, reviewer']), 'reviewer,editor', TRUE];
    yield 'one queried role is missing' => [static::createUserStub(['name' => 'alice', 'role' => 'editor, reviewer']), 'editor, admin', FALSE];
    yield 'whitespace around a role is ignored' => [static::createUserStub(['name' => 'alice', 'role' => ' editor ']), ' editor ', TRUE];
  }

  /**
   * Builds a user stub with the given values.
   *
   * @param array<string, mixed> $values
   *   The values to seed the stub with.
   */
  protected static function createUserStub(array $values): EntityStubInterface {
    return new EntityStub('user', NULL, $values);
  }

}
