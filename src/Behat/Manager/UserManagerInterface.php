<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Manager;

use DrevOps\BehatSteps\Driver\Entity\EntityStubInterface;

/**
 * Interface for classes that manage users created during tests.
 */
interface UserManagerInterface {

  /**
   * Returns the currently logged in user.
   *
   * @return \DrevOps\BehatSteps\Driver\Entity\EntityStubInterface|false
   *   The user stub, or FALSE if the user is anonymous.
   */
  public function getCurrentUser(): EntityStubInterface|false;

  /**
   * Sets the currently logged in user.
   *
   * @param \DrevOps\BehatSteps\Driver\Entity\EntityStubInterface|false $user
   *   The user stub, or FALSE if the user has been logged out.
   */
  public function setCurrentUser(EntityStubInterface|false $user): void;

  /**
   * Adds a new user.
   *
   * Call this after creating a new user to keep track of all the users that are
   * created in a test scenario. They can then be cleaned up after completing
   * the test.
   *
   * @param \DrevOps\BehatSteps\Driver\Entity\EntityStubInterface $user
   *   The user stub.
   */
  public function addUser(EntityStubInterface $user): void;

  /**
   * Removes a user from the list of users that were created in the test.
   *
   * @param string $userName
   *   The name of the user to remove.
   */
  public function removeUser(string $userName): void;

  /**
   * Returns the list of users that were created in the test.
   *
   * @return array<string, \DrevOps\BehatSteps\Driver\Entity\EntityStubInterface>
   *   An array of user stubs keyed by user name.
   */
  public function getUsers(): array;

  /**
   * Returns the user with the given user name.
   *
   * @param string $userName
   *   The name of the user to return.
   *
   * @return \DrevOps\BehatSteps\Driver\Entity\EntityStubInterface
   *   The user stub.
   *
   * @throws \InvalidArgumentException
   *   Thrown when the user with the given name does not exist.
   */
  public function getUser(string $userName): EntityStubInterface;

  /**
   * Clears the list of users that were created in the test.
   */
  public function clearUsers(): void;

  /**
   * Returns whether any users were created in the test.
   */
  public function hasUsers(): bool;

  /**
   * Returns whether the current user is anonymous.
   */
  public function currentUserIsAnonymous(): bool;

  /**
   * Checks whether the current user holds the given roles.
   *
   * Both the query and the user's own role value are comma-separated lists,
   * and surrounding whitespace on either side is ignored.
   *
   * @param string $role
   *   A single role, or several roles as one comma-separated string.
   *
   * @return bool
   *   TRUE when the current user holds every role named in the query.
   */
  public function currentUserHasRole(string $role): bool;

}
