<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Manager;

/**
 * Interface for authentication managers that support fast logout.
 */
interface FastLogoutInterface {

  /**
   * Logs out by directly resetting the session.
   *
   * Resetting the session does not bootstrap Drupal, so no logout hook fires.
   */
  public function fastLogout(): void;

}
