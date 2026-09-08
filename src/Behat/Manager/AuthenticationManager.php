<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Manager;

use Behat\Mink\Element\DocumentElement;
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\DriverException;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Exception\ExpectationException;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use Behat\Mink\Mink;
use DrevOps\BehatSteps\Behat\MinkAwareTrait;
use DrevOps\BehatSteps\Behat\ParametersTrait;
use DrevOps\BehatSteps\Driver\Capability\AuthenticationCapabilityInterface;
use DrevOps\BehatSteps\Driver\Entity\EntityStubInterface;

/**
 * Default implementation of the authentication manager service.
 */
class AuthenticationManager implements AuthenticationManagerInterface, FastLogoutInterface, BasicAuthInterface {

  use MinkAwareTrait;
  use ParametersTrait;

  /**
   * Constructs an AuthenticationManager object.
   *
   * @param \Behat\Mink\Mink $mink
   *   The Mink instance.
   * @param \DrevOps\BehatSteps\Behat\Manager\UserManagerInterface $userManager
   *   The user manager.
   * @param \DrevOps\BehatSteps\Behat\Manager\DriverManagerInterface $driverManager
   *   The driver manager.
   * @param array<string, mixed> $minkParameters
   *   Mink configuration parameters.
   * @param array<string, mixed> $parameters
   *   Extension parameters.
   */
  public function __construct(
    Mink $mink,
    protected UserManagerInterface $userManager,
    protected DriverManagerInterface $driverManager,
    array $minkParameters,
    array $parameters,
  ) {
    $this->setMink($mink);
    $this->setMinkParameters($minkParameters);
    $this->setParameters($parameters);
  }

  /**
   * {@inheritdoc}
   */
  public function logIn(EntityStubInterface $user): void {
    // Log out any existing user before logging in a new user.
    $this->fastLogout();

    $session = $this->getSession();

    $login_url = $this->locatePath($this->getDrupalText('login_url'));
    $session->visit($login_url);

    $name = (string) $user->getValue('name');
    $pass = (string) $user->getValue('pass');

    // Which user property is submitted as the login value. Defaults to 'name'
    // but may be set to 'mail' for sites that authenticate by email or to any
    // other user entity property.
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
      // Wait for the redirect away from the login form.
      $timeout = microtime(TRUE) + $login_wait;
      while (microtime(TRUE) < $timeout && $session->getCurrentUrl() === $login_url) {
        usleep(100000);
      }

      // Wait for the page body to render.
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
      $message = $role !== NULL ? sprintf("Unable to determine if logged in because '%s' ('log_out') link cannot be found for user '%s' with role '%s'", $this->getDrupalText('log_out'), $name, $role) : sprintf("Unable to determine if logged in because '%s' ('log_out') link cannot be found for user '%s'", $this->getDrupalText('log_out'), $name);
      throw new ExpectationException($message, $session->getDriver());
    }

    $this->userManager->setCurrentUser($user);

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

    $this->userManager->setCurrentUser(FALSE);

    $this->backendLogout();
  }

  /**
   * {@inheritdoc}
   */
  public function loggedIn(): bool {
    $session = $this->getSession();

    // A session that has not started has no user logged in.
    if (!$session->isStarted()) {
      return FALSE;
    }

    // A nullsafe check here keeps PHPStan from flagging the non-nullable
    // 'getPage()' return type while still letting test doubles that return
    // 'NULL' short-circuit safely.
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
      // The driver has not loaded a page yet.
    }

    // Some themes do not add that class to the body, so fall back to the
    // presence of the login form.
    $login_url = $this->locatePath($this->getDrupalText('login_url'));
    $session->visit($login_url);
    if ($page->has('css', $this->getDrupalSelector('login_form_selector'))) {
      $this->fastLogout();

      return FALSE;
    }

    // As a last resort, a logout link means a user is logged in. On themes
    // that defer header navigation (through Critical CSS or a late JS render)
    // the link may be absent at the moment of this lookup, so poll for it
    // within the same window 'login_wait' configures for the post-submit
    // waits in 'logIn()'.
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
      $this->applyBasicAuth();
    }

    $this->userManager->setCurrentUser(FALSE);

    $this->backendLogout();
  }

  /**
   * {@inheritdoc}
   */
  public function applyBasicAuth(): void {
    $credentials = $this->resolveBasicAuth();
    if ($credentials === NULL) {
      return;
    }

    try {
      $this->getSession()->setBasicAuth($credentials['username'], $credentials['password']);
    }
    catch (UnsupportedDriverActionException) {
      // The active driver cannot set basic auth headers (a JavaScript driver,
      // for example); those receive credentials via the 'base_url' userinfo.
    }
  }

  /**
   * Returns the logout element from the page.
   */
  public function getLogoutElement(): ?NodeElement {
    return $this->getSession()->getPage()->findLink($this->getDrupalText('log_out'));
  }

  /**
   * Resolves the HTTP Basic authentication credentials to apply.
   *
   * Credentials are derived from the 'base_url' userinfo
   * ('http://user:pass@host').
   *
   * @return array{username: string, password: string}|null
   *   The resolved credentials, or NULL when the 'base_url' carries no
   *   username.
   */
  protected function resolveBasicAuth(): ?array {
    $base_url = (string) $this->getMinkParameter('base_url');
    $user = parse_url($base_url, PHP_URL_USER);
    if (is_string($user) && $user !== '') {
      $pass = parse_url($base_url, PHP_URL_PASS);

      return [
        // Userinfo is RFC 3986 encoded, where '+' is a literal plus and
        // spaces are '%20', so decode with rawurldecode() rather than
        // urldecode() (which would turn a literal '+' into a space).
        'username' => rawurldecode($user),
        'password' => is_string($pass) ? rawurldecode($pass) : '',
      ];
    }

    return NULL;
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
   * Logs in on the backend driver if it supports authentication.
   */
  protected function backendLogin(EntityStubInterface $user): void {
    $driver = $this->driverManager->getDriver();
    if ($driver instanceof AuthenticationCapabilityInterface) {
      $driver->login($user);
    }
  }

  /**
   * Logs out on the backend driver if it supports authentication.
   */
  protected function backendLogout(): void {
    $driver = $this->driverManager->getDriver();
    if ($driver instanceof AuthenticationCapabilityInterface) {
      $driver->logout();
    }
  }

}
