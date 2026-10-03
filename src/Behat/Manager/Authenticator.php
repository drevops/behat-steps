<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Manager;

use Behat\Mink\Element\DocumentElement;
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\DriverException;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Exception\ExpectationException;
use Behat\Mink\Mink;
use DrevOps\BehatSteps\Backend\Capability\AuthenticationCapabilityInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;
use DrevOps\BehatSteps\Behat\MinkAwareTrait;
use DrevOps\BehatSteps\Behat\ParametersTrait;

/**
 * Logs a user in and out of the site under test.
 *
 * Takes a basic-auth applier instead of applying basic auth itself. A session
 * reset drops request headers, so the credentials are reapplied afterwards;
 * that is the only overlap between the 2 concerns.
 */
class Authenticator implements AuthenticatorInterface, FastLogoutInterface {

  use MinkAwareTrait;
  use ParametersTrait;

  /**
   * Constructs an Authenticator object.
   *
   * @param \Behat\Mink\Mink $mink
   *   The Mink instance.
   * @param \DrevOps\BehatSteps\Behat\Manager\UserRegistryInterface $userRegistry
   *   The user registry.
   * @param \DrevOps\BehatSteps\Behat\Manager\BackendRegistryInterface $backendRegistry
   *   The backend registry.
   * @param \DrevOps\BehatSteps\Behat\Manager\BasicAuthenticatorInterface $basicAuthenticator
   *   Reapplies basic auth after a session reset clears the request headers.
   * @param array<string, mixed> $mink_parameters
   *   Mink configuration parameters.
   * @param array<string, mixed> $parameters
   *   Extension parameters.
   */
  public function __construct(
    Mink $mink,
    protected UserRegistryInterface $userRegistry,
    protected BackendRegistryInterface $backendRegistry,
    protected BasicAuthenticatorInterface $basicAuthenticator,
    array $mink_parameters,
    array $parameters,
  ) {
    $this->setMink($mink);
    $this->setMinkParameters($mink_parameters);
    $this->setParameters($parameters);
  }

  /**
   * {@inheritdoc}
   */
  public function logIn(EntityStubInterface $user): void {
    $this->fastLogout();

    $session = $this->getSession();

    $login_url = $this->locatePath($this->getDrupalText('login_url'));
    $session->visit($login_url);

    $name = (string) $user->getValue('name');
    $pass = (string) $user->getValue('pass');

    $login_field = (string) ($this->getParameter('login_field') ?: 'name');
    $login_value = (string) $user->getValue($login_field);

    $page = $session->getPage();
    $page->fillField($this->getDrupalText('username_field'), $login_value);
    $page->fillField($this->getDrupalText('password_field'), $pass);

    $login_element = $this->getLoginElement($page);
    if (!$login_element instanceof NodeElement) {
      throw new ElementNotFoundException($session->getDriver(), 'submit button', 'css', 'login form');
    }
    $login_element->click();

    $login_wait = (int) $this->getParameter('login_wait');
    if ($login_wait > 0) {
      $timeout = microtime(TRUE) + $login_wait;
      while (microtime(TRUE) < $timeout && $session->getCurrentUrl() === $login_url) {
        usleep(100000);
      }

      $timeout = microtime(TRUE) + $login_wait;
      while (microtime(TRUE) < $timeout && !$session->getPage()->find('css', 'body')) {
        usleep(100000);
      }

      // The logged-in selector may be added by JS or AJAX after the render.
      $timeout = microtime(TRUE) + $login_wait;
      while (microtime(TRUE) < $timeout && !$session->getPage()->has('css', $this->getDrupalSelector('logged_in_selector'))) {
        usleep(100000);
      }
    }

    if (!$this->loggedIn()) {
      $role = $user->getValue('role');
      $message = $role !== NULL ? sprintf("Unable to determine if logged in because \"%s\" ('log_out') link cannot be found for user \"%s\" with role \"%s\"", $this->getDrupalText('log_out'), $name, $role) : sprintf("Unable to determine if logged in because \"%s\" ('log_out') link cannot be found for user \"%s\"", $this->getDrupalText('log_out'), $name);
      throw new ExpectationException($message, $session->getDriver());
    }

    $this->userRegistry->setCurrentUser($user);

    $this->backendLogin($user);
  }

  /**
   * {@inheritdoc}
   */
  public function logOut(): void {
    $session = $this->getSession();

    $logout_url = $this->locatePath($this->getDrupalText('logout_url'));
    $logout_confirm_url = $this->locatePath($this->getDrupalText('logout_confirm_url'));

    $session->visit($logout_url);

    if ($session->getCurrentUrl() === $logout_confirm_url) {
      $logout_element = $this->getLogoutConfirmElement($session->getPage());

      if (!$logout_element instanceof NodeElement) {
        throw new ElementNotFoundException($session->getDriver(), 'logout button', 'css', 'logout confirmation page');
      }

      $logout_element->click();
    }

    $this->userRegistry->setCurrentUser(FALSE);

    $this->backendLogout();
  }

  /**
   * {@inheritdoc}
   */
  public function loggedIn(): bool {
    $session = $this->getSession();

    if (!$session->isStarted()) {
      return FALSE;
    }

    $page = $session->getPage();
    if ($page === NULL) {
      return FALSE;
    }

    // The logged-in class on the body tag works with almost any theme.
    try {
      if ($page->has('css', $this->getDrupalSelector('logged_in_selector'))) {
        return TRUE;
      }
    }
    catch (DriverException) {
      // The browser driver has not loaded a page yet.
    }

    // Some themes do not add that class to the body, so fall back to the
    // presence of the login form.
    $login_url = $this->locatePath($this->getDrupalText('login_url'));
    $session->visit($login_url);
    if ($page->has('css', $this->getDrupalSelector('login_form_selector'))) {
      $this->fastLogout();

      return FALSE;
    }

    // As a last resort, a logout link means a user is logged in. A theme that
    // defers header navigation (Critical CSS or a late JS render) may add the
    // link late, so the poll reuses the 'login_wait' window of 'logIn()'.
    $session->visit($this->locatePath('/'));
    $login_wait = (int) $this->getParameter('login_wait');
    if ($login_wait > 0) {
      $timeout = microtime(TRUE) + $login_wait;
      while (microtime(TRUE) < $timeout && !$this->getLogoutElement() instanceof NodeElement) {
        usleep(100000);
      }
    }
    if ($this->getLogoutElement() instanceof NodeElement) {
      return TRUE;
    }

    // The user appears to be anonymous, so reset the session fully rather
    // than leave a partially logged-in state behind.
    $this->fastLogout();

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function fastLogout(): void {
    $session = $this->getSession();
    if ($session->isStarted()) {
      $session->reset();
      // Resetting clears request headers, including basic auth, so requests
      // after the reset would 401 on sites behind webserver-level basic auth.
      $this->basicAuthenticator->applyBasicAuth();
    }

    $this->userRegistry->setCurrentUser(FALSE);

    $this->backendLogout();
  }

  /**
   * Returns the logout element from the page.
   */
  public function getLogoutElement(): ?NodeElement {
    return $this->getSession()->getPage()->findLink($this->getDrupalText('log_out'));
  }

  /**
   * Returns the login element from the page.
   */
  protected function getLoginElement(DocumentElement $element): ?NodeElement {
    return $element->findButton($this->getDrupalText('log_in'));
  }

  /**
   * Returns the logout confirm element from the page.
   */
  protected function getLogoutConfirmElement(DocumentElement $element): ?NodeElement {
    return $element->findButton($this->getDrupalText('log_out'));
  }

  /**
   * Logs in on the backend if it supports authentication.
   */
  protected function backendLogin(EntityStubInterface $user): void {
    if ($this->backendRegistry->hasCapability(AuthenticationCapabilityInterface::class)) {
      $this->backendRegistry->getBackendFor(AuthenticationCapabilityInterface::class)->login($user);
    }
  }

  /**
   * Logs out on the backend if it supports authentication.
   */
  protected function backendLogout(): void {
    // Only a backend the scenario already reached can hold a backend session,
    // and resolving one here would bootstrap it. Teardown logs every scenario
    // out, so asking for the capability would boot Drupal for all of them.
    $this->backendRegistry->getResolvedBackendFor(AuthenticationCapabilityInterface::class)?->logout();
  }

}
