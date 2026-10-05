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
   *   stub and marks it saved with the created account.
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

}
