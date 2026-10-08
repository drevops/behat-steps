<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Auth;

use Behat\Mink\Element\DocumentElement;
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\DriverException;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Exception\ExpectationException;
use Behat\Mink\Mink;
use DrevOps\BehatSteps\Backend\Capability\AuthenticationCapabilityInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;
use DrevOps\BehatSteps\Behat\Config\ParametersTrait;
use DrevOps\BehatSteps\Behat\Mink\BrowserCapabilityResolver;
use DrevOps\BehatSteps\Behat\Mink\Capability\JavascriptCapabilityInterface;
use DrevOps\BehatSteps\Behat\Mink\MinkAwareTrait;
use DrevOps\BehatSteps\Behat\Registry\BackendRegistryInterface;
use DrevOps\BehatSteps\Behat\Registry\UserRegistryInterface;

/**
 * Logs a user in and out of the site under test.
 */
final class Authenticator implements AuthenticatorInterface, FastLogoutInterface {

  use MinkAwareTrait;
  use ParametersTrait;

  /**
   * Seconds a JavaScript session waits, at least, for a login signal.
   */
  public const int JAVASCRIPT_LOGIN_WAIT = 10;

  /**
   * Resolves what the session's browser driver can do.
   */
  protected BrowserCapabilityResolver $browserCapabilityResolver;

  /**
   * Constructs an Authenticator object.
   *
   * @param \Behat\Mink\Mink $mink
   *   The Mink instance.
   * @param \DrevOps\BehatSteps\Behat\Registry\UserRegistryInterface $userRegistry
   *   The user registry.
   * @param \DrevOps\BehatSteps\Behat\Registry\BackendRegistryInterface $backendRegistry
   *   The backend registry.
   * @param \DrevOps\BehatSteps\Behat\Auth\BasicAuthenticatorInterface $basicAuthenticator
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
    $this->browserCapabilityResolver = new BrowserCapabilityResolver();
  }

  /**
   * {@inheritdoc}
   */
  public function login(EntityStubInterface $user): void {
    $this->fastLogout();

    $session = $this->getSession();

    $session->visit($this->locatePath($this->getDrupalText('login_url')));

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

    // The login URL can redirect, so the wait compares against the URL the
    // form is submitted from.
    $form_url = $session->getCurrentUrl();
    $login_element->click();

    $navigation_wait = $this->getLoginSignalWait();

    if ($navigation_wait > 0) {
      // An AJAX login shows the logged-in selector without leaving the page.
      $this->waitUntil($navigation_wait, fn(): bool => $session->getCurrentUrl() !== $form_url || $this->hasLoggedInSelector());
    }

    $login_wait = (int) $this->getParameter('login_wait');

    // A theme without the logged-in selector would hold every login for the
    // whole wait, so these polls run only when 'login_wait' asks for them.
    if ($login_wait > 0) {
      $this->waitUntil($login_wait, static fn(): bool => $session->getPage()->find('css', 'body') !== NULL);

      // The logged-in selector may be added by JS or AJAX after the render.
      $this->waitUntil($login_wait, $this->hasLoggedInSelector(...));
    }

    if (!$this->isLoggedIn()) {
      $role = $user->getValue('role');
      $message = $role !== NULL ? sprintf("Unable to determine if logged in because \"%s\" ('logout') link cannot be found for user \"%s\" with role \"%s\".", $this->getDrupalText('logout'), $name, $role) : sprintf("Unable to determine if logged in because \"%s\" ('logout') link cannot be found for user \"%s\".", $this->getDrupalText('logout'), $name);
      throw new ExpectationException($message, $session->getDriver());
    }

    $this->userRegistry->setCurrentUser($user);

    $this->backendLogin($user);
  }

  /**
   * {@inheritdoc}
   */
  public function logout(): void {
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
  public function isLoggedIn(): bool {
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
      if ($this->hasLoggedInSelector()) {
        return TRUE;
      }
    }
    catch (DriverException) {
      // The browser driver has not loaded a page yet.
    }

    // The current page can predate the login, so the check repeats on the page
    // the login URL leads to.
    $session->visit($this->locatePath($this->getDrupalText('login_url')));
    if ($this->hasLoggedInSelector()) {
      return TRUE;
    }

    // Some themes do not add that class to the body, so fall back to the
    // presence of the login form.
    if ($page->has('css', $this->getDrupalSelector('login_form_selector'))) {
      $this->fastLogout();

      return FALSE;
    }

    // As a last resort, the logged-in selector or a logout link on the
    // homepage means a user is logged in. A theme deferring its header
    // (Critical CSS or a late JS render) can add either late, so the check
    // polls until one appears.
    $session->visit($this->locatePath('/'));
    if ($this->waitUntil($this->getLoginSignalWait(), fn(): bool => $this->hasLoggedInSelector() || $this->getLogoutElement() instanceof NodeElement)) {
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
  protected function getLogoutElement(): ?NodeElement {
    return $this->getSession()->getPage()->findLink($this->getDrupalText('logout'));
  }

  /**
   * Returns the login element from the page.
   */
  protected function getLoginElement(DocumentElement $element): ?NodeElement {
    return $element->findButton($this->getDrupalText('login'));
  }

  /**
   * Returns the logout confirm element from the page.
   */
  protected function getLogoutConfirmElement(DocumentElement $element): ?NodeElement {
    return $element->findButton($this->getDrupalText('logout'));
  }

  /**
   * Determines whether the current page matches the logged-in selector.
   *
   * @phpstan-impure
   */
  protected function hasLoggedInSelector(): bool {
    return $this->getSession()->getPage()->has('css', $this->getDrupalSelector('logged_in_selector'));
  }

  /**
   * Determines whether the session's browser driver runs JavaScript.
   */
  protected function isJavascriptSession(): bool {
    return $this->browserCapabilityResolver->has($this->getSession()->getDriver(), JavascriptCapabilityInterface::class);
  }

  /**
   * Returns the seconds to wait for a login signal.
   *
   * It is 'login_wait', raised to 'JAVASCRIPT_LOGIN_WAIT' in a JavaScript
   * session, where the browser can render a page after the call that loads it
   * returns.
   */
  protected function getLoginSignalWait(): int {
    $login_wait = (int) $this->getParameter('login_wait');

    return $this->isJavascriptSession() ? max($login_wait, self::JAVASCRIPT_LOGIN_WAIT) : $login_wait;
  }

  /**
   * Polls a condition until it holds or the given seconds elapse.
   *
   * The condition is checked at least once, so 0 seconds checks it without
   * waiting.
   *
   * @param int $seconds
   *   The longest time to poll for, in seconds.
   * @param \Closure(): bool $condition
   *   The condition to poll.
   *
   * @return bool
   *   Whether the condition held.
   */
  protected function waitUntil(int $seconds, \Closure $condition): bool {
    $timeout = microtime(TRUE) + $seconds;

    while (!$condition()) {
      if (microtime(TRUE) >= $timeout) {
        return FALSE;
      }

      usleep(100000);
    }

    return TRUE;
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
    // and resolving one here would bootstrap it.
    $this->backendRegistry->getResolvedBackendFor(AuthenticationCapabilityInterface::class)?->logout();
  }

}
