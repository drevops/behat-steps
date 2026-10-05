<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Fixtures;

use DrevOps\BehatSteps\Backend\Capability\UserCapabilityInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;

/**
 * Recording test double for 'UserCapabilityInterface'.
 *
 * Records the roles assigned to a user so a test can assert them without
 * booting a real backend. 'userCreate()' returns the stub untouched and
 * 'userDelete()' does nothing; only 'userAddRole()' records.
 */
class RecordingUserCapability implements UserCapabilityInterface {

  /**
   * Roles assigned during the test, in the order they were applied.
   *
   * @var array<int, string>
   */
  public array $roles = [];

  /**
   * {@inheritdoc}
   */
  public function userCreate(EntityStubInterface $stub): EntityStubInterface {
    return $stub;
  }

  /**
   * {@inheritdoc}
   */
  public function userDelete(EntityStubInterface $stub): void {
  }

  /**
   * {@inheritdoc}
   */
  public function userAddRole(EntityStubInterface $stub, string $role): void {
    $this->roles[] = $role;
  }

}
