<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Gherkin\Node\TableNode;
use Drupal\user\Entity\Role;

/**
 * Trait RoleTrait.
 *
 * Role-related steps.
 *
 * @package DrevOps\BehatSteps
 */
trait RoleTrait {

  /**
   * Create a single role with specified permissions.
   *
   * @Given the role :role_name with the permissions :permissions
   */
  public function roleCreateSingle(string $role_name, string $permissions): void {
    $permissions = array_map(trim(...), explode(',', $permissions));

    $rid = strtolower($role_name);
    $role_name = trim($role_name);

    $existing_role = Role::load($rid);
    if ($existing_role) {
      $existing_role->delete();
    }

    /** @var \Drupal\user\RoleInterface $role */
    $role = \Drupal::entityTypeManager()->getStorage('user_role')->create([
      'id' => $rid,
      'label' => $role_name,
    ]);
    $saved = $role->save();

    if ($saved !== SAVED_NEW) {
      throw new \RuntimeException(sprintf('Failed to create a role with "%s" permission(s).', implode(', ', $permissions)));
    }
    $this->roles[(string) $role->id()] = (string) $role->id();

    user_role_grant_permissions($role->id(), $permissions);
  }

  /**
   * Create multiple roles from the specified table.
   *
   * @Given the following roles:
   */
  public function roleCreateMultiple(TableNode $table): void {
    foreach ($table->getHash() as $hash) {
      if (!isset($hash['name'])) {
        throw new \RuntimeException('Missing required column "name"');
      }

      $permissions = $hash['permissions'] ?: '';
      $this->roleCreateSingle($hash['name'], $permissions);
    }
  }

}
