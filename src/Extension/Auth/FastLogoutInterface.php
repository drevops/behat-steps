<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Extension\Auth;

/**
 * Interface for authenticators that support fast logout.
 */
interface FastLogoutInterface {

  /**
   * Logs out by directly resetting the session.
   *
   * Resetting the session reaches no backend, so the site runs no logout hook.
   */
  public function fastLogout(): void;

}
