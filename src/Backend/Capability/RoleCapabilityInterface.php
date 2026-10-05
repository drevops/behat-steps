<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Capability;

use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;

/**
 * Capability: create and delete roles.
 */
interface RoleCapabilityInterface {

  /**
   * Creates a role with the given permissions.
   *
   * @param array<string> $permissions
   *   Permission machine names or labels.
   * @param string|null $id
   *   Optional role machine name. If omitted, a random lowercase id is
   *   generated.
   * @param string|null $label
   *   Optional human-readable role label. Defaults to the id when omitted;
   *   falls back to a random string only when both this and $id are NULL.
   *
   * @return \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface
   *   A 'user_role' stub carrying the created role's 'id' and 'label'. A
   *   backend that holds the role object attaches it and flags the stub as
   *   saved.
   */
  public function roleCreate(array $permissions, ?string $id = NULL, ?string $label = NULL): EntityStubInterface;

  /**
   * Deletes a role.
   *
   * @param string $role_name
   *   The role machine name to delete.
   */
  public function roleDelete(string $role_name): void;

}
