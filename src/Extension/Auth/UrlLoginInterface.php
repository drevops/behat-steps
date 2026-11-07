<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Extension\Auth;

use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;

/**
 * Interface for authenticators that log in by visiting a URL.
 */
interface UrlLoginInterface {

  /**
   * Logs in as the given user by visiting a URL that logs them in.
   *
   * The browser stays on the page the URL leads to when that page shows the
   * logged-in selector.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $user
   *   The user stub the URL logs in.
   * @param string $path
   *   The path, relative to the base URL, that logs the user in.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   When no user is logged in after the visit.
   */
  public function loginWithUrl(EntityStubInterface $user, string $path): void;

}
