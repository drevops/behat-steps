<?php

namespace DrevOps\BehatSteps;

use Behat\Gherkin\Node\TableNode;

/**
 * Trait OverrideTrait.
 *
 * Used to override standard Drupal Extension methods.
 *
 * @package DrevOps\BehatSteps
 */
trait OverrideTrait {

  /**
   * Creates one or more terms on an existing vocabulary.
   *
   * Provide term data in the following format:
   *
   * | name  | parent | description | weight | taxonomy_field_image |
   * | Snook | Fish   | Marine fish | 10     | snook-123.jpg        |
   * | ...   | ...    | ...         | ...    | ...                  |
   *
   * Only the 'name' field is required.
   *
   * @Given :vocabulary terms:
   */
  public function createTerms(mixed $vocabulary, TableNode $table): void {
    $vocabulary = (string) $vocabulary;
    // Delete entities before creating them.
    $this->taxonomyDeleteTerms($vocabulary, $table);
    parent::createTerms($vocabulary, $table);
  }

  /**
   * Creates content of a given type provided in the tabular form.
   *
   * @Given :type content:
   */
  public function createNodes(mixed $type, TableNode $table): void {
    $type = (string) $type;
    $filtered_table = TableNode::fromList($table->getColumn(0));
    // Delete entities before creating them.
    $this->contentDelete($type, $filtered_table);
    parent::createNodes($type, $table);
  }

  /**
   * Creates multiple users.
   *
   * @Given users:
   */
  public function createUsers(TableNode $table): void {
    // Delete entities before creating them.
    $this->userDelete($table);
    parent::createUsers($table);
  }

  /**
   * Creates and authenticates a user with the given role(s).
   *
   * @Given I am logged in as a user with the :role role(s)
   * @Given I am logged in as a/an :role
   */
  public function assertAuthenticatedByRole(mixed $role): void {
    $role = (string) $role;
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
