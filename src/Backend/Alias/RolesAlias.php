<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Alias;

use DrevOps\BehatSteps\Backend\Capability\UserCapabilityInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;
use DrevOps\BehatSteps\Backend\Exception\CreationAliasResolutionException;

/**
 * Assigns roles to a user after the user has been created.
 *
 * Reads the 'roles' value (expected to be an array of role machine
 * names or labels) and calls 'userAddRole()' for each entry on the
 * backend supplied at construction. No-ops when the value is missing or
 * not an array.
 */
class RolesAlias implements PostCreateAliasInterface {

  /**
   * Constructs the alias with the backend that receives role calls.
   *
   * @param \DrevOps\BehatSteps\Backend\Capability\UserCapabilityInterface $backend
   *   The backend whose 'userAddRole()' will be called per role.
   */
  public function __construct(protected readonly UserCapabilityInterface $backend) {
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return 'roles';
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityType(): string {
    return 'user';
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return "Assigns roles to a user after creation. Accepts an array of role machine names or labels; ignores non-array values.";
  }

  /**
   * {@inheritdoc}
   */
  public function applyAfterCreate(EntityStubInterface $stub, object $entity): void {
    $roles = $stub->getValue('roles');

    if (!is_array($roles)) {
      return;
    }

    foreach ($roles as $role) {
      // EntityReferenceHandler expands 'roles' into records like
      // ['target_id' => 'editor'], so the record is unwrapped back to the
      // role name the caller supplied.
      if (is_array($role) && array_key_exists('target_id', $role)) {
        $role = $role['target_id'];
      }

      if (!is_scalar($role) && !$role instanceof \Stringable) {
        throw new CreationAliasResolutionException("Cannot assign role because one of the 'roles' entries is not a scalar or stringable value.");
      }

      $name = trim((string) $role);

      if ($name === '') {
        throw new CreationAliasResolutionException("Cannot assign role because one of the 'roles' entries is empty after trimming.");
      }

      $this->backend->userAddRole($stub, $name);
    }
  }

}
