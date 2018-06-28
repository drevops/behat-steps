<?php

namespace IntegratedExperts\BehatSteps\D7;

/**
 * Trait OverrideTrait.
 *
 * Used to override standard Drupal Extension methods.
 *
 * @package IntegratedExperts\BehatSteps\D7
 */
trait OverrideTrait {

  /**
   * {@inheritdoc}
   */
  public function assertAuthenticatedByRole($role) {
    // Override parent assertion to allow using 'anonymous user' role without
    // actually creating a user with role. By default,
    // assertAuthenticatedByRole() will create a user with 'authenticated role'
    // even if 'anonymous user' role is provided.
    if ($role == 'anonymous user') {
      if (!empty($this->loggedInUser)) {
        $this->logout();
      }
    }
    else {
      parent::assertAuthenticatedByRole($role);
    }
  }

}
