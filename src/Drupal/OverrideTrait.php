<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Drupal;

use Behat\Gherkin\Node\TableNode;

/**
 * Override Drupal Extension behaviors.
 *
 * - Automated entity deletion before creation to avoid duplicates.
 * - Improved user authentication handling for anonymous users.
 *
 * Use with caution: depending on your version of Drupal Extension, PHP and
 * Composer, the step definition string (/^Given etc.../) may need to be defined
 * for these overrides. If you encounter errors about missing or duplicated
 * step definitions, do not include this trait and rather copy the contents of
 * this file into your feature context file and copy the step definition strings
 * from the Drupal Extension.
 */
trait OverrideTrait {

  /**
   * {@inheritdoc}
   */
  public function createNodes(mixed $type, TableNode $table): void {
    $type = (string) $type;
    $filtered_table = TableNode::fromList($table->getColumn(0));
    // Delete entities before creating them.
    $this->contentDelete($type, $filtered_table);
    parent::createNodes($type, $table);
  }

  /**
   * {@inheritdoc}
   */
  public function createUsers(TableNode $table): void {
    // Delete entities before creating them.
    $this->userDelete($table);
    parent::createUsers($table);
  }

  /**
   * {@inheritdoc}
   */
  public function assertAuthenticatedByRole(mixed $role): void {
    $role = (string) $role;
    // Override parent assertion to allow using 'anonymous user' role without
    // actually creating a user with role. By default,
    // assertAuthenticatedByRole() will create a user with 'authenticated role'
    // even if 'anonymous user' role is provided.
    if ($role === 'anonymous user' || $role === 'anonymous') {
      if (!empty($this->userManager->getCurrentUser())) {
        $this->logout();
      }
    }
    else {
      parent::assertAuthenticatedByRole($role);
    }
  }

}
