<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Manager;

/**
 * Interface for authenticators that apply HTTP Basic auth.
 */
interface BasicAuthenticatorInterface {

  /**
   * Applies configured HTTP Basic authentication credentials to the session.
   *
   * Resetting a Mink session clears request headers, which drops any basic
   * auth credentials. Calling this restores them so requests to sites behind
   * webserver-level basic auth stay authenticated after a reset.
   *
   * Credentials come from the 'base_url' userinfo. For a driver that cannot
   * set basic auth, such as a JavaScript driver, the call is a no-op.
   */
  public function applyBasicAuth(): void;

}
