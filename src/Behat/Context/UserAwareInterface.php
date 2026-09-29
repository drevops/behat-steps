<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Context;

use Behat\Behat\Context\Context;
use DrevOps\BehatSteps\Behat\Manager\AuthenticatorInterface;
use DrevOps\BehatSteps\Behat\Manager\UserRegistryInterface;

/**
 * Contract for a context that logs users in and tracks the ones it creates.
 *
 * A trait cannot implement an interface, so a context composing
 * 'AuthTrait' declares this one to receive the user registry.
 *
 * @see \DrevOps\BehatSteps\Helper\Drupal\AuthTrait
 */
interface UserAwareInterface extends Context {

  /**
   * Sets the user registry.
   *
   * @internal
   *   Injection point called by the context initializer.
   */
  public function authSetUserRegistry(UserRegistryInterface $user_registry): void;

  /**
   * Returns the user registry.
   */
  public function authGetUserRegistry(): UserRegistryInterface;

  /**
   * Sets the authenticator that logs a user in and out.
   *
   * @internal
   *   Injection point called by the context initializer.
   */
  public function authSetAuthenticator(AuthenticatorInterface $authenticator): void;

  /**
   * Returns the authenticator that logs a user in and out.
   */
  public function authGetAuthenticator(): AuthenticatorInterface;

}
