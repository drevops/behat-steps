<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Capability;

use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;

/**
 * Capability: create, delete, and assign roles to users.
 */
interface UserCapabilityInterface {

  /**
   * Creates a user.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The user stub. The backend writes the resolved 'uid' back onto the
   *   stub. A backend that holds the created account attaches it and flags
   *   the stub as saved.
   *
   * @return \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface
   *   The same stub.
   */
  public function createUser(EntityStubInterface $stub): EntityStubInterface;

  /**
   * Deletes a user.
   *
   * Does nothing when the user does not exist.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The stub returned from a previous 'createUser()' call, or one that
   *   carries a 'uid' value resolving to an existing user.
   */
  public function deleteUser(EntityStubInterface $stub): void;

  /**
   * Adds a role to a user.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The user stub.
   * @param string $role
   *   The role machine name or label.
   */
  public function addUserRole(EntityStubInterface $stub, string $role): void;

  /**
   * Returns the path of a user's one-time login page.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The user stub. Either the saved-entity slot or a 'uid' value must be
   *   populated so the backend can resolve the account.
   *
   * @return string
   *   The path, relative to the site root, of the page a password reset
   *   email links to, which logs the user in once.
   *
   * @throws \RuntimeException
   *   When the stub carries no id, or no user has the id.
   */
  public function getOneTimeLoginUrl(EntityStubInterface $stub): string;

}
