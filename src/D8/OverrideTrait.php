<?php

namespace IntegratedExperts\BehatSteps\D8;

use Behat\Gherkin\Node\TableNode;

/**
 * Trait OverrideTrait.
 *
 * Used to override standard Drupal Extension methods.
 *
 * @package IntegratedExperts\BehatSteps\D8
 */
trait OverrideTrait {

  /**
   * {@inheritdoc}
   */
  public function createTerms($vocabulary, TableNode $table) {
    // Delete entities before creating them.
    $this->taxonomyDeleteTerms($vocabulary, $table);
    parent::createTerms($vocabulary, $table);
  }

  /**
   * {@inheritdoc}
   */
  public function createNodes($type, TableNode $table) {
    $filtered_table = TableNode::fromList($table->getColumn(0));
    // Delete entities before creating them.
    $this->contentDelete($type, $filtered_table);
    parent::createNodes($type, $table);
  }

  /**
   * {@inheritdoc}
   */
  public function createUsers(TableNode $table) {
    // Delete entities before creating them.
    $this->userDelete($table);
    parent::createUsers($table);
  }

  /**
   * {@inheritdoc}
   */
  public function assertAuthenticatedByRole($role) {
    // Override parent assertion to allow using 'anonymous user' role without
    // actually creating a user with role. By default,
    // assertAuthenticatedByRole() will create a user with 'authenticated role'
    // even if 'anonymous user' role is provided.
    if ($role == 'anonymous user' || $role == 'anonymous') {
      if (!empty($this->userManager->getCurrentUser())) {
        $this->logout();
      }
    }
    else {
      parent::assertAuthenticatedByRole($role);
    }
  }

}
