<?php

declare(strict_types=1);

namespace Drupal\mysite_core\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for providing test content.
 */
class TestContent extends ControllerBase {

  /**
   * Provides example content for route specific authentication.
   *
   * @return array<mixed>
   *   The username of the current logged in user.
   */
  public function testCurrentUser(): array {
    $account = $this->currentUser();

    return ['#markup' => $account->getAccountName()];
  }

}
