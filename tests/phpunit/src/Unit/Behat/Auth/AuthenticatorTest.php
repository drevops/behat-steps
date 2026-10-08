<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Auth;

use Behat\Mink\Driver\DriverInterface;
use Behat\Mink\Driver\Selenium2Driver;
use Behat\Mink\Element\DocumentElement;
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\DriverException;
use Behat\Mink\Mink;
use Behat\Mink\Session;
use DrevOps\BehatSteps\Backend\BackendInterface;
use DrevOps\BehatSteps\Backend\Capability\AuthenticationCapabilityInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;
use DrevOps\BehatSteps\Behat\Auth\Authenticator;
use DrevOps\BehatSteps\Behat\Auth\AuthenticatorInterface;
use DrevOps\BehatSteps\Behat\Auth\BasicAuthenticator;
use DrevOps\BehatSteps\Behat\Auth\FastLogoutInterface;
use DrevOps\BehatSteps\Behat\Registry\BackendRegistryInterface;
use DrevOps\BehatSteps\Behat\Registry\UserRegistry;
use DrevOps\BehatSteps\Behat\Registry\UserRegistryInterface;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests the login, logout and basic-auth flows against a stubbed session.
 */
#[CoversClass(Authenticator::class)]
class AuthenticatorTest extends UnitTestCase {

  protected const EXTENSION_PARAMS = [
    'text' => [
      'login' => 'Log in',
      'logout' => 'Log out',
      'login_url' => '/user/login',
      'logout_url' => '/user/logout',
      'logout_confirm_url' => '/user/logout/confirm',
      'username_field' => 'Username',
      'password_field' => 'Password',
    ],
    'selectors' => [
      'logged_in_selector' => 'body.logged-in',
      'login_form_selector' => 'form#user-login',
    ],
  ];

  protected const MINK_PARAMS = [
    'base_url' => 'http://localhost',
  ];

  public function testImplementsInterfaces(): void {
    $authenticator = $this->createAuthenticator();
    $this->assertInstanceOf(AuthenticatorInterface::class, $authenticator);
    $this->assertInstanceOf(FastLogoutInterface::class, $authenticator);
  }

  public function testLoginSuccess(): void {
    $submit = $this->createMock(NodeElement::class);
    $submit->expects($this->once())->method('click');

    $page = $this->createMock(DocumentElement::class);
    $page->method('findButton')->with('Log in')->willReturn($submit);
    $page->method('has')->willReturn(TRUE);

    $session = $this->createSessionMock($page);
    // @phpstan-ignore method.notFound
    $session->method('isStarted')->willReturn(TRUE);

    $user_registry = new UserRegistry();
    $backend_registry = $this->createBackendRegistryMock();
    $authenticator = $this->createAuthenticator($session, $user_registry, $backend_registry);

    $user = new EntityStub('user', NULL, ['name' => 'admin', 'pass' => 'password']);
    $authenticator->login($user);
    $this->assertSame($user, $user_registry->getCurrentUser());
  }

  #[DataProvider('dataProviderLoginFieldValue')]
  public function testLoginFieldValue(?string $login_field, string $expected_value): void {
    $submit = $this->createMock(NodeElement::class);

    $page = $this->createMock(DocumentElement::class);
    $page->method('findButton')->with('Log in')->willReturn($submit);
    $page->method('has')->willReturn(TRUE);
    $filled = [];
    $page->expects($this->exactly(2))->method('fillField')->willReturnCallback(static function (string $field, string $value) use (&$filled): void {
      $filled[$field] = $value;
    });

    $session = $this->createSessionMock($page);
    // @phpstan-ignore method.notFound
    $session->method('isStarted')->willReturn(TRUE);

    $params = static::EXTENSION_PARAMS;
    if ($login_field !== NULL) {
      $params['login_field'] = $login_field;
    }

    $authenticator = $this->createAuthenticator($session, NULL, NULL, $params);
    $user = new EntityStub('user', NULL, ['name' => 'admin', 'mail' => 'admin@example.com', 'pass' => 'password']);
    $authenticator->login($user);

    $this->assertSame($expected_value, $filled['Username'] ?? NULL);
  }

  public static function dataProviderLoginFieldValue(): \Iterator {
    yield 'defaults to name when not configured' => [NULL, 'admin'];
    yield 'explicit name uses name' => ['name', 'admin'];
    yield 'mail uses mail' => ['mail', 'admin@example.com'];
  }

  public function testLoginThrowsWhenNoSubmitButton(): void {
    $page = $this->createMock(DocumentElement::class);
    $page->method('findButton')->willReturn(NULL);

    $session = $this->createSessionMock($page);
    // @phpstan-ignore method.notFound
    $session->method('isStarted')->willReturn(TRUE);
    // @phpstan-ignore method.notFound
    $session->method('getCurrentUrl')->willReturn('http://localhost/user/login');

    $authenticator = $this->createAuthenticator($session);

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Submit button matching css "login form" not found.');
    $authenticator->login(new EntityStub('user', NULL, ['name' => 'admin', 'pass' => 'pass']));
  }

  #[DataProvider('dataProviderLoginThrowsWhenNotLoggedIn')]
  public function testLoginThrowsWhenNotLoggedIn(EntityStubInterface $user, string $expected_message): void {
    $submit = $this->createMock(NodeElement::class);

    $page = $this->createMock(DocumentElement::class);
    $page->method('findButton')->willReturn($submit);
    $page->method('has')->willReturn(FALSE);
    $page->method('findLink')->willReturn(NULL);

    $session = $this->createSessionMock($page);
    // @phpstan-ignore method.notFound
    $session->method('isStarted')->willReturn(TRUE);

    $authenticator = $this->createAuthenticator($session);

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage($expected_message);
    $authenticator->login($user);
  }

  public static function dataProviderLoginThrowsWhenNotLoggedIn(): \Iterator {
    yield 'user without role' => [
      new EntityStub('user', NULL, ['name' => 'admin', 'pass' => 'pass']),
      "Unable to determine if logged in because \"Log out\" ('logout') link cannot be found for user \"admin\".",
    ];
    yield 'user with role' => [
      new EntityStub('user', NULL, ['name' => 'admin', 'pass' => 'pass', 'role' => 'administrator']),
      "Unable to determine if logged in because \"Log out\" ('logout') link cannot be found for user \"admin\" with role \"administrator\".",
    ];
  }

  public function testLoginCallsBackend(): void {
    $submit = $this->createMock(NodeElement::class);

    $page = $this->createMock(DocumentElement::class);
    $page->method('findButton')->willReturn($submit);
    $page->method('has')->willReturn(TRUE);

    $session = $this->createSessionMock($page);
    // @phpstan-ignore method.notFound
    $session->method('isStarted')->willReturn(TRUE);

    $auth_backend = $this->createAuthBackendMock();
    $auth_backend->expects($this->once())->method('login');

    $backend_registry = $this->createMock(BackendRegistryInterface::class);
    $backend_registry->method('hasCapability')->willReturn(TRUE);
    $backend_registry->method('getBackendFor')->willReturn($auth_backend);
    $backend_registry->method('getResolvedBackendFor')->willReturn($auth_backend);

    $authenticator = $this->createAuthenticator($session, NULL, $backend_registry);
    $authenticator->login(new EntityStub('user', NULL, ['name' => 'admin', 'pass' => 'pass']));
  }

  public function testLogout(): void {
    $page = $this->createMock(DocumentElement::class);

    $session = $this->createSessionMock($page);
    // @phpstan-ignore method.notFound
    $session->expects($this->once())->method('visit');
    // @phpstan-ignore method.notFound
    $session->method('getCurrentUrl')->willReturn('http://localhost/user/logout');

    $user_registry = new UserRegistry();
    $user_registry->setCurrentUser(new EntityStub('user', NULL, ['name' => 'admin']));

    $backend_registry = $this->createBackendRegistryMock();
    $authenticator = $this->createAuthenticator($session, $user_registry, $backend_registry);
    $authenticator->logout();
    $this->assertFalse($user_registry->getCurrentUser());
  }

  public function testLogoutWithConfirmationPage(): void {
    $submit = $this->createMock(NodeElement::class);
    $submit->expects($this->once())->method('click');

    $page = $this->createMock(DocumentElement::class);
    $page->method('findButton')->with('Log out')->willReturn($submit);

    $session = $this->createSessionMock($page);
    // @phpstan-ignore method.notFound
    $session->method('getCurrentUrl')->willReturn('http://localhost/user/logout/confirm');

    $user_registry = new UserRegistry();
    $backend_registry = $this->createBackendRegistryMock();
    $authenticator = $this->createAuthenticator($session, $user_registry, $backend_registry);
    $authenticator->logout();
    $this->assertFalse($user_registry->getCurrentUser());
  }

  public function testLogoutWithConfirmationPageThrowsWhenNoButton(): void {
    $page = $this->createMock(DocumentElement::class);
    $page->method('findButton')->willReturn(NULL);

    $session = $this->createSessionMock($page);
    // @phpstan-ignore method.notFound
    $session->method('getCurrentUrl')->willReturn('http://localhost/user/logout/confirm');

    $authenticator = $this->createAuthenticator($session);

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Logout button matching css "logout confirmation page" not found.');
    $authenticator->logout();
  }

  public function testLogoutCallsBackend(): void {
    $page = $this->createMock(DocumentElement::class);
    $session = $this->createSessionMock($page);
    // @phpstan-ignore method.notFound
    $session->method('getCurrentUrl')->willReturn('http://localhost/user/logout');

    $auth_backend = $this->createAuthBackendMock();
    $auth_backend->expects($this->once())->method('logout');

    $backend_registry = $this->createMock(BackendRegistryInterface::class);
    $backend_registry->method('hasCapability')->willReturn(TRUE);
    $backend_registry->method('getBackendFor')->willReturn($auth_backend);
    $backend_registry->method('getResolvedBackendFor')->willReturn($auth_backend);

    $authenticator = $this->createAuthenticator($session, NULL, $backend_registry);
    $authenticator->logout();
  }

  #[DataProvider('dataProviderIsLoggedIn')]
  public function testIsLoggedIn(bool $session_started, bool $has_logged_in_selector, bool $has_login_form, bool $has_logout_link, bool $expected): void {
    $page = $this->createMock(DocumentElement::class);

    $has_map = [];
    if ($session_started) {
      $has_map[] = ['css', 'body.logged-in', $has_logged_in_selector];
      if (!$has_logged_in_selector) {
        $has_map[] = ['css', 'form#user-login', $has_login_form];
      }
    }
    $page->method('has')->willReturnMap($has_map);
    $page->method('findLink')->willReturn($has_logout_link ? $this->createMock(NodeElement::class) : NULL);

    $session = $this->createSessionMock($page);
    // @phpstan-ignore method.notFound
    $session->method('isStarted')->willReturn($session_started);

    $authenticator = $this->createAuthenticator($session);
    $this->assertSame($expected, $authenticator->isLoggedIn());
  }

  public static function dataProviderIsLoggedIn(): \Iterator {
    yield 'session not started' => [FALSE, FALSE, FALSE, FALSE, FALSE];
    yield 'logged in selector found' => [TRUE, TRUE, FALSE, FALSE, TRUE];
    yield 'login form found means not logged in' => [TRUE, FALSE, TRUE, FALSE, FALSE];
    yield 'logout link found means logged in' => [TRUE, FALSE, FALSE, TRUE, TRUE];
    yield 'nothing found means not logged in' => [TRUE, FALSE, FALSE, FALSE, FALSE];
  }

  /**
   * Tests which page of the fallback decides whether a user is logged in.
   *
   * The current page shows neither signal, as when the post-login page had
   * not loaded at the time of the first check.
   *
   * @param array<string, array<int, string>> $pages
   *   The selectors and link texts each page shows, keyed by URL.
   * @param bool $expected
   *   Whether the user is expected to be logged in.
   * @param array<int, string> $expected_visits
   *   The URLs expected to be visited, in order.
   */
  #[DataProvider('dataProviderIsLoggedInAfterNavigation')]
  public function testIsLoggedInAfterNavigation(array $pages, bool $expected, array $expected_visits): void {
    $visited = new \ArrayObject();
    $session = $this->createNavigatingSessionMock(static fn(string $url, string $locator): bool => in_array($locator, $pages[$url] ?? [], TRUE), $visited);

    $authenticator = $this->createAuthenticator($session);

    $this->assertSame($expected, $authenticator->isLoggedIn());
    $this->assertSame($expected_visits, $visited->getArrayCopy());
  }

  public static function dataProviderIsLoggedInAfterNavigation(): \Iterator {
    yield 'logged-in selector on the page the login URL leads to' => [
      ['http://localhost/user/login' => ['body.logged-in']],
      TRUE,
      ['http://localhost/user/login'],
    ];
    yield 'logged-in selector on a homepage without a logout link' => [
      ['http://localhost/' => ['body.logged-in']],
      TRUE,
      ['http://localhost/user/login', 'http://localhost/'],
    ];
    yield 'logout link on the homepage' => [
      ['http://localhost/' => ['Log out']],
      TRUE,
      ['http://localhost/user/login', 'http://localhost/'],
    ];
    yield 'login form on the page the login URL leads to' => [
      ['http://localhost/user/login' => ['form#user-login']],
      FALSE,
      ['http://localhost/user/login'],
    ];
    yield 'neither signal on any page' => [
      [],
      FALSE,
      ['http://localhost/user/login', 'http://localhost/'],
    ];
  }

  public function testIsLoggedInReturnsFalseWhenPageNotAvailable(): void {
    $session = $this->createMock(Session::class);
    $session->method('isStarted')->willReturn(TRUE);
    $session->method('getPage')->willReturn(NULL);

    $mink = new Mink(['default' => $session]);
    $mink->setDefaultSessionName('default');

    $authenticator = new Authenticator($mink, new UserRegistry(), $this->createBackendRegistryMock(), new BasicAuthenticator($mink, static::MINK_PARAMS), static::MINK_PARAMS, static::EXTENSION_PARAMS);
    $this->assertFalse($authenticator->isLoggedIn());
  }

  /**
   * Tests that isLoggedIn() polls for the logout link when login_wait > 0.
   *
   * Simulates the Critical CSS / late JS race: the logged-in selector never
   * appears, and the login form is absent because the user is logged in. The
   * logout link appears only after several polls, so with login_wait > 0 the
   * third-resort check keeps polling.
   */
  public function testIsLoggedInPollsForLogoutLinkWhenLoginWaitSet(): void {
    $link = $this->createMock(NodeElement::class);

    $call_count = 0;
    $page = $this->createMock(DocumentElement::class);
    $page->method('has')->willReturn(FALSE);
    $page->method('findLink')->willReturnCallback(static function () use (&$call_count, $link): ?NodeElement {
      $call_count++;
      return $call_count >= 3 ? $link : NULL;
    });

    $session = $this->createSessionMock($page);
    // @phpstan-ignore method.notFound
    $session->method('isStarted')->willReturn(TRUE);

    $params = static::EXTENSION_PARAMS;
    $params['login_wait'] = 2;

    $authenticator = $this->createAuthenticator($session, NULL, NULL, $params);
    $this->assertTrue($authenticator->isLoggedIn());
    $this->assertGreaterThanOrEqual(3, $call_count);
  }

  /**
   * Tests that isLoggedIn() polls the homepage for the logged-in selector.
   *
   * The homepage renders no logout link, and the selector appears on the
   * third check. A JavaScript session polls even when 'login_wait' is unset.
   *
   * @param int|null $login_wait
   *   The 'login_wait' parameter, or NULL to leave it unset.
   * @param bool $is_javascript
   *   Whether the session runs a browser driver that runs JavaScript.
   */
  #[DataProvider('dataProviderIsLoggedInPollsHomepageForLoggedInSelector')]
  public function testIsLoggedInPollsHomepageForLoggedInSelector(?int $login_wait, bool $is_javascript): void {
    $checks = 0;
    $session = $this->createNavigatingSessionMock(static function (string $url, string $locator) use (&$checks): bool {
      if ($url !== 'http://localhost/' || $locator !== 'body.logged-in') {
        return FALSE;
      }

      $checks++;

      return $checks >= 3;
    }, NULL, $is_javascript ? $this->createMock(Selenium2Driver::class) : NULL);

    $params = static::EXTENSION_PARAMS;
    if ($login_wait !== NULL) {
      $params['login_wait'] = $login_wait;
    }

    $authenticator = $this->createAuthenticator($session, NULL, NULL, $params);
    $this->assertTrue($authenticator->isLoggedIn());
    $this->assertSame(3, $checks);
  }

  public static function dataProviderIsLoggedInPollsHomepageForLoggedInSelector(): \Iterator {
    yield 'login_wait set' => [2, FALSE];
    yield 'JavaScript session without login_wait' => [NULL, TRUE];
  }

  public function testIsLoggedInDoesNotPollWhenLoginWaitIsZero(): void {
    $call_count = 0;
    $page = $this->createMock(DocumentElement::class);
    $page->method('has')->willReturn(FALSE);
    $page->method('findLink')->willReturnCallback(static function () use (&$call_count): ?NodeElement {
      $call_count++;
      return NULL;
    });

    $session = $this->createSessionMock($page);
    // @phpstan-ignore method.notFound
    $session->method('isStarted')->willReturn(TRUE);

    $params = static::EXTENSION_PARAMS;
    $params['login_wait'] = 0;

    $authenticator = $this->createAuthenticator($session, NULL, NULL, $params);
    $this->assertFalse($authenticator->isLoggedIn());
    $this->assertSame(1, $call_count);
  }

  /**
   * Tests that isLoggedIn() returns FALSE when the wait elapses.
   *
   * The logout link never appears, so the wait expires after login_wait
   * seconds and the method falls through to the anonymous-state cleanup.
   */
  public function testIsLoggedInReturnsFalseWhenLogoutLinkWaitTimesOut(): void {
    $page = $this->createMock(DocumentElement::class);
    $page->method('has')->willReturn(FALSE);
    $page->method('findLink')->willReturn(NULL);

    $session = $this->createSessionMock($page);
    // @phpstan-ignore method.notFound
    $session->method('isStarted')->willReturn(TRUE);

    $params = static::EXTENSION_PARAMS;
    $params['login_wait'] = 1;

    $authenticator = $this->createAuthenticator($session, NULL, NULL, $params);
    $start = microtime(TRUE);
    $this->assertFalse($authenticator->isLoggedIn());
    $elapsed = microtime(TRUE) - $start;
    $this->assertGreaterThanOrEqual(1.0, $elapsed);
  }

  public function testIsLoggedInHandlesDriverException(): void {
    $session = $this->createNavigatingSessionMock(static function (string $url, string $locator): bool {
      if ($url === '') {
        throw new DriverException('Not loaded');
      }

      return $locator === 'form#user-login';
    });

    $authenticator = $this->createAuthenticator($session);
    // The login form is found, so the call returns FALSE rather than throwing.
    $this->assertFalse($authenticator->isLoggedIn());
  }

  public function testFastLogoutResetsSession(): void {
    $session = $this->createMock(Session::class);
    $session->method('isStarted')->willReturn(TRUE);
    $session->expects($this->once())->method('reset');

    $mink = new Mink(['default' => $session]);
    $mink->setDefaultSessionName('default');

    $user_registry = new UserRegistry();
    $user_registry->setCurrentUser(new EntityStub('user', NULL, ['name' => 'admin']));

    $backend_registry = $this->createBackendRegistryMock();
    $authenticator = new Authenticator($mink, $user_registry, $backend_registry, new BasicAuthenticator($mink, static::MINK_PARAMS), static::MINK_PARAMS, static::EXTENSION_PARAMS);
    $authenticator->fastLogout();

    $this->assertFalse($user_registry->getCurrentUser());
  }

  public function testFastLogoutSkipsResetWhenNotStarted(): void {
    $session = $this->createMock(Session::class);
    $session->method('isStarted')->willReturn(FALSE);
    $session->expects($this->never())->method('reset');

    $mink = new Mink(['default' => $session]);
    $mink->setDefaultSessionName('default');

    $backend_registry = $this->createBackendRegistryMock();
    $authenticator = new Authenticator($mink, new UserRegistry(), $backend_registry, new BasicAuthenticator($mink, static::MINK_PARAMS), static::MINK_PARAMS, static::EXTENSION_PARAMS);
    $authenticator->fastLogout();
  }

  public function testFastLogoutCallsBackend(): void {
    $session = $this->createMock(Session::class);
    $session->method('isStarted')->willReturn(FALSE);

    $mink = new Mink(['default' => $session]);
    $mink->setDefaultSessionName('default');

    $auth_backend = $this->createAuthBackendMock();
    $auth_backend->expects($this->once())->method('logout');

    $backend_registry = $this->createMock(BackendRegistryInterface::class);
    $backend_registry->method('hasCapability')->willReturn(TRUE);
    $backend_registry->method('getBackendFor')->willReturn($auth_backend);
    $backend_registry->method('getResolvedBackendFor')->willReturn($auth_backend);

    $authenticator = new Authenticator($mink, new UserRegistry(), $backend_registry, new BasicAuthenticator($mink, static::MINK_PARAMS), static::MINK_PARAMS, static::EXTENSION_PARAMS);
    $authenticator->fastLogout();
  }

  public function testFastLogoutReappliesBasicAuth(): void {
    $session = $this->createMock(Session::class);
    $session->method('isStarted')->willReturn(TRUE);
    $session->expects($this->once())->method('reset');
    $session->expects($this->once())->method('setBasicAuth')->with('alice', 'secret');

    $mink = new Mink(['default' => $session]);
    $mink->setDefaultSessionName('default');

    $authenticator = new Authenticator($mink, new UserRegistry(), $this->createBackendRegistryMock(), new BasicAuthenticator($mink, ['base_url' => 'http://alice:secret@localhost']), ['base_url' => 'http://alice:secret@localhost'], static::EXTENSION_PARAMS);
    $authenticator->fastLogout();
  }

  /**
   * Tests that fastLogout() skips basic auth when the session is not started.
   *
   * Nothing was reset, so there are no cleared headers to restore.
   */
  public function testFastLogoutSkipsBasicAuthWhenSessionNotStarted(): void {
    $session = $this->createMock(Session::class);
    $session->method('isStarted')->willReturn(FALSE);
    $session->expects($this->never())->method('setBasicAuth');

    $mink = new Mink(['default' => $session]);
    $mink->setDefaultSessionName('default');

    $authenticator = new Authenticator($mink, new UserRegistry(), $this->createBackendRegistryMock(), new BasicAuthenticator($mink, ['base_url' => 'http://alice:secret@localhost']), ['base_url' => 'http://alice:secret@localhost'], static::EXTENSION_PARAMS);
    $authenticator->fastLogout();
  }

  public function testIsLoggedInFindsLogoutLinkByConfiguredText(): void {
    $page = $this->createMock(DocumentElement::class);
    $page->method('has')->willReturn(FALSE);
    $page->expects($this->atLeastOnce())->method('findLink')->with('Log out')->willReturn($this->createMock(NodeElement::class));

    $session = $this->createSessionMock($page);
    // @phpstan-ignore method.notFound
    $session->method('isStarted')->willReturn(TRUE);

    $authenticator = $this->createAuthenticator($session);
    $this->assertTrue($authenticator->isLoggedIn());
  }

  /**
   * Tests that a session without JavaScript never polls after the click.
   *
   * The URL is read once, before the click.
   */
  public function testLoginSkipsWaitWhenLoginWaitIsZero(): void {
    $submit = $this->createMock(NodeElement::class);

    $page = $this->createMock(DocumentElement::class);
    $page->method('findButton')->with('Log in')->willReturn($submit);
    $page->method('has')->willReturn(TRUE);

    $session = $this->createSessionMock($page);
    // @phpstan-ignore method.notFound
    $session->method('isStarted')->willReturn(TRUE);
    // @phpstan-ignore method.notFound
    $session->expects($this->once())->method('getCurrentUrl')->willReturn('http://localhost/user/login');

    $params = static::EXTENSION_PARAMS;
    $params['login_wait'] = 0;

    $authenticator = $this->createAuthenticator($session, NULL, NULL, $params);
    $authenticator->login(new EntityStub('user', NULL, ['name' => 'admin', 'pass' => 'password']));
  }

  public function testLoginWaitsForLoggedInSelector(): void {
    $submit = $this->createMock(NodeElement::class);

    $call_count = 0;
    $page = $this->createMock(DocumentElement::class);
    $page->method('findButton')->with('Log in')->willReturn($submit);
    $page->method('has')->willReturnCallback(static function (string $selector, string $locator) use (&$call_count): bool {
      if ($locator === 'body.logged-in') {
        $call_count++;
        // The first 2 calls return FALSE, so the wait polls before the
        // selector appears.
        return $call_count > 2;
      }
      return FALSE;
    });
    $page->method('find')->willReturnCallback(static function (string $selector, string $locator) use ($page): ?DocumentElement {
      if ($locator === 'body') {
        return $page;
      }
      return NULL;
    });

    $url_call_count = 0;
    $session = $this->createSessionMock($page);
    // @phpstan-ignore method.notFound
    $session->method('isStarted')->willReturn(TRUE);
    // The URL changes after login, as on a redirect.
    // @phpstan-ignore method.notFound
    $session->method('getCurrentUrl')->willReturnCallback(static function () use (&$url_call_count): string {
      $url_call_count++;
      return $url_call_count <= 1 ? 'http://localhost/user/login' : 'http://localhost/user/1';
    });

    $params = static::EXTENSION_PARAMS;
    $params['login_wait'] = 1;

    $user_registry = new UserRegistry();
    $authenticator = $this->createAuthenticator($session, $user_registry, NULL, $params);
    $authenticator->login(new EntityStub('user', NULL, ['name' => 'admin', 'pass' => 'password']));

    $this->assertNotFalse($user_registry->getCurrentUser());
  }

  /**
   * Tests that login() polls until the page body renders.
   *
   * A driver can return a page whose body has not been written yet, so the
   * wait loop polls for the body before proceeding to the logged-in check.
   */
  public function testLoginWaitsForTheBodyToRender(): void {
    $submit = $this->createMock(NodeElement::class);

    $find_count = 0;
    $page = $this->createMock(DocumentElement::class);
    $page->method('findButton')->with('Log in')->willReturn($submit);
    $page->method('has')->willReturn(TRUE);
    $page->method('find')->willReturnCallback(static function (string $selector, string $locator) use (&$find_count, $page): ?DocumentElement {
      if ($locator !== 'body') {
        return NULL;
      }

      $find_count++;

      return $find_count >= 3 ? $page : NULL;
    });

    $url_call_count = 0;
    $session = $this->createSessionMock($page);
    // @phpstan-ignore method.notFound
    $session->method('isStarted')->willReturn(TRUE);
    // @phpstan-ignore method.notFound
    $session->method('getCurrentUrl')->willReturnCallback(static function () use (&$url_call_count): string {
      $url_call_count++;
      return $url_call_count <= 1 ? 'http://localhost/user/login' : 'http://localhost/user/1';
    });

    $params = static::EXTENSION_PARAMS;
    $params['login_wait'] = 2;

    $authenticator = $this->createAuthenticator($session, NULL, NULL, $params);
    $authenticator->login(new EntityStub('user', NULL, ['name' => 'admin', 'pass' => 'password']));

    $this->assertGreaterThanOrEqual(3, $find_count);
  }

  /**
   * Tests that a JavaScript session waits for the post-login page.
   *
   * The click returns while the browser still shows the login form, and the
   * third URL read after it returns the post-login page.
   *
   * The login URL redirects to the form, so the wait compares against the URL
   * the form was submitted from. A single visit shows the first check
   * confirmed the login.
   */
  public function testLoginWaitsForNavigationInJavascriptSession(): void {
    $is_clicked = FALSE;
    $reads = 0;

    $submit = $this->createMock(NodeElement::class);
    $submit->method('click')->willReturnCallback(static function () use (&$is_clicked): void {
      $is_clicked = TRUE;
    });

    $page = $this->createMock(DocumentElement::class);
    $page->method('findButton')->with('Log in')->willReturn($submit);

    $session = $this->createSessionMock($page, $this->createMock(Selenium2Driver::class));
    // @phpstan-ignore method.notFound
    $session->method('isStarted')->willReturn(TRUE);
    // @phpstan-ignore method.notFound
    $session->expects($this->once())->method('visit')->with('http://localhost/user');
    // @phpstan-ignore method.notFound
    $session->method('getCurrentUrl')->willReturnCallback(static function () use (&$is_clicked, &$reads): string {
      if (!$is_clicked) {
        return 'http://localhost/user/login';
      }

      $reads++;

      return $reads >= 3 ? 'http://localhost/user/1' : 'http://localhost/user/login';
    });

    $page->method('has')->willReturnCallback(static fn(string $selector, string $locator): bool => $locator === 'body.logged-in' && $session->getCurrentUrl() === 'http://localhost/user/1');

    $params = static::EXTENSION_PARAMS;
    $params['text']['login_url'] = '/user';

    $user_registry = new UserRegistry();
    $authenticator = $this->createAuthenticator($session, $user_registry, NULL, $params);
    $authenticator->login(new EntityStub('user', NULL, ['name' => 'admin', 'pass' => 'password']));

    $this->assertNotFalse($user_registry->getCurrentUser());
  }

  /**
   * Tests that the logged-in selector ends a JavaScript login's wait.
   *
   * The URL never changes, as in an AJAX login, and the selector appears on
   * the third check. The login is confirmed with a single visit, well inside
   * the JavaScript wait.
   */
  public function testLoginEndsNavigationWaitOnLoggedInSelector(): void {
    $checks = 0;

    $page = $this->createMock(DocumentElement::class);
    $page->method('findButton')->with('Log in')->willReturn($this->createMock(NodeElement::class));
    $page->method('has')->willReturnCallback(static function (string $selector, string $locator) use (&$checks): bool {
      if ($locator !== 'body.logged-in') {
        return FALSE;
      }

      $checks++;

      return $checks >= 3;
    });

    $session = $this->createSessionMock($page, $this->createMock(Selenium2Driver::class));
    // @phpstan-ignore method.notFound
    $session->method('isStarted')->willReturn(TRUE);
    // @phpstan-ignore method.notFound
    $session->expects($this->once())->method('visit')->with('http://localhost/user/login');
    // @phpstan-ignore method.notFound
    $session->method('getCurrentUrl')->willReturn('http://localhost/user/login');

    $user_registry = new UserRegistry();
    $authenticator = $this->createAuthenticator($session, $user_registry);

    $start = microtime(TRUE);
    $authenticator->login(new EntityStub('user', NULL, ['name' => 'admin', 'pass' => 'password']));
    $elapsed = microtime(TRUE) - $start;

    $this->assertNotFalse($user_registry->getCurrentUser());
    $this->assertLessThan(Authenticator::JAVASCRIPT_LOGIN_WAIT, $elapsed);
  }

  /**
   * Tests that login() without login_wait throws when selector is delayed.
   *
   * Demonstrates the race condition: without login_wait, a delayed
   * logged_in_selector causes login to fail even though login succeeded.
   */
  public function testLoginFailsWithoutLoginWaitWhenSelectorDelayed(): void {
    $submit = $this->createMock(NodeElement::class);

    $page = $this->createMock(DocumentElement::class);
    $page->method('findButton')->willReturnCallback(static fn(string $text): ?NodeElement => $text === 'Log in' ? $submit : NULL);
    $page->method('has')->willReturn(FALSE);
    $page->method('findLink')->willReturn(NULL);

    $session = $this->createSessionMock($page);
    // @phpstan-ignore method.notFound
    $session->method('isStarted')->willReturn(TRUE);
    // @phpstan-ignore method.notFound
    $session->method('getCurrentUrl')->willReturn('http://localhost/user/1');

    $authenticator = $this->createAuthenticator($session);

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage("Unable to determine if logged in");
    $authenticator->login(new EntityStub('user', NULL, ['name' => 'admin', 'pass' => 'password']));
  }

  public function testLoginVisitsConfiguredLoginUrl(): void {
    $submit = $this->createMock(NodeElement::class);

    $page = $this->createMock(DocumentElement::class);
    $page->method('findButton')->with('Log in')->willReturn($submit);
    $page->method('has')->willReturn(TRUE);

    $session = $this->createSessionMock($page);
    // @phpstan-ignore method.notFound
    $session->method('isStarted')->willReturn(TRUE);
    // @phpstan-ignore method.notFound
    $session->expects($this->once())->method('visit')->with('http://localhost/custom-login');

    $params = static::EXTENSION_PARAMS;
    $params['text']['login_url'] = '/custom-login';

    $authenticator = $this->createAuthenticator($session, NULL, NULL, $params);
    $authenticator->login(new EntityStub('user', NULL, ['name' => 'admin', 'pass' => 'password']));
  }

  public function testLogoutVisitsConfiguredLogoutUrl(): void {
    $page = $this->createMock(DocumentElement::class);

    $session = $this->createSessionMock($page);
    // @phpstan-ignore method.notFound
    $session->expects($this->once())->method('visit')->with('http://localhost/custom-logout');
    // @phpstan-ignore method.notFound
    $session->method('getCurrentUrl')->willReturn('http://localhost/custom-logout');

    $params = static::EXTENSION_PARAMS;
    $params['text']['logout_url'] = '/custom-logout';

    $user_registry = new UserRegistry();
    $user_registry->setCurrentUser(new EntityStub('user', NULL, ['name' => 'admin']));

    $authenticator = $this->createAuthenticator($session, $user_registry, NULL, $params);
    $authenticator->logout();
    $this->assertFalse($user_registry->getCurrentUser());
  }

  public function testLogoutConfirmUsesConfiguredUrls(): void {
    $submit = $this->createMock(NodeElement::class);
    $submit->expects($this->once())->method('click');

    $page = $this->createMock(DocumentElement::class);
    $page->method('findButton')->with('Log out')->willReturn($submit);

    $session = $this->createSessionMock($page);
    // @phpstan-ignore method.notFound
    $session->expects($this->once())->method('visit')->with('http://localhost/custom-logout');
    // @phpstan-ignore method.notFound
    $session->method('getCurrentUrl')->willReturn('http://localhost/custom-logout/confirm');

    $params = static::EXTENSION_PARAMS;
    $params['text']['logout_url'] = '/custom-logout';
    $params['text']['logout_confirm_url'] = '/custom-logout/confirm';

    $user_registry = new UserRegistry();
    $authenticator = $this->createAuthenticator($session, $user_registry, NULL, $params);
    $authenticator->logout();
    $this->assertFalse($user_registry->getCurrentUser());
  }

  protected function createSessionMock(?DocumentElement $page = NULL, ?DriverInterface $driver = NULL): Session {
    $session = $this->createMock(Session::class);
    $session->method('getPage')->willReturn($page ?? $this->createMock(DocumentElement::class));
    $session->method('getDriver')->willReturn($driver ?? $this->createMock(DriverInterface::class));
    return $session;
  }

  /**
   * Creates a started session whose page depends on the URL last visited.
   *
   * The session starts with no page loaded, at an empty URL.
   *
   * @param \Closure(string, string): bool $shows
   *   Receives the current URL and a CSS selector or link text, and returns
   *   whether that page shows it.
   * @param \ArrayObject<int, string>|null $visited
   *   Collects every URL the session visits, in order.
   * @param \Behat\Mink\Driver\DriverInterface|null $driver
   *   The browser driver the session runs.
   */
  protected function createNavigatingSessionMock(\Closure $shows, ?\ArrayObject $visited = NULL, ?DriverInterface $driver = NULL): Session {
    $url = '';
    $link = $this->createMock(NodeElement::class);

    $page = $this->createMock(DocumentElement::class);
    $page->method('has')->willReturnCallback(static function (string $selector, string $locator) use (&$url, $shows): bool {
      return $shows($url, $locator);
    });
    $page->method('findLink')->willReturnCallback(static function (string $locator) use (&$url, $shows, $link): ?NodeElement {
      return $shows($url, $locator) ? $link : NULL;
    });

    $session = $this->createSessionMock($page, $driver);
    // @phpstan-ignore method.notFound
    $session->method('isStarted')->willReturn(TRUE);
    // @phpstan-ignore method.notFound
    $session->method('getCurrentUrl')->willReturnCallback(static function () use (&$url): string {
      return $url;
    });
    // @phpstan-ignore method.notFound
    $session->method('visit')->willReturnCallback(static function (string $target) use (&$url, $visited): void {
      $url = $target;
      $visited?->append($target);
    });

    return $session;
  }

  /**
   * Creates a mock for the AuthenticationCapability and BackendInterface.
   *
   * @return \DrevOps\BehatSteps\Backend\Capability\AuthenticationCapabilityInterface&\DrevOps\BehatSteps\Backend\BackendInterface&\PHPUnit\Framework\MockObject\MockObject
   *   The mocked backend.
   */
  protected function createAuthBackendMock(): AuthenticationCapabilityInterface&BackendInterface&MockObject {
    /** @var \DrevOps\BehatSteps\Backend\Capability\AuthenticationCapabilityInterface&\DrevOps\BehatSteps\Backend\BackendInterface&\PHPUnit\Framework\MockObject\MockObject $backend */
    $backend = $this->createMockForIntersectionOfInterfaces([
      AuthenticationCapabilityInterface::class,
      BackendInterface::class,
    ]);
    $backend->method('isBootstrapped')->willReturn(TRUE);
    return $backend;
  }

  protected function createBackendRegistryMock(): BackendRegistryInterface {
    $backend = $this->createMock(BackendInterface::class);
    $backend->method('isBootstrapped')->willReturn(TRUE);
    $backend_registry = $this->createMock(BackendRegistryInterface::class);
    $backend_registry->method('hasCapability')->willReturn(FALSE);
    $backend_registry->method('getBackend')->willReturn($backend);
    return $backend_registry;
  }

  /**
   * Creates an Authenticator with optional overrides.
   *
   * @param \Behat\Mink\Session|null $session
   *   Optional Mink session override.
   * @param \DrevOps\BehatSteps\Behat\Registry\UserRegistryInterface|null $user_registry
   *   Optional user registry override.
   * @param \DrevOps\BehatSteps\Behat\Registry\BackendRegistryInterface|null $backend_registry
   *   Optional backend registry override.
   * @param array<string, mixed>|null $parameters
   *   Optional extension parameters override.
   */
  protected function createAuthenticator(?Session $session = NULL, ?UserRegistryInterface $user_registry = NULL, ?BackendRegistryInterface $backend_registry = NULL, ?array $parameters = NULL): Authenticator {
    $session ??= $this->createSessionMock();
    $mink = new Mink(['default' => $session]);
    $mink->setDefaultSessionName('default');

    return new Authenticator(
          $mink,
          $user_registry ?? new UserRegistry(),
          $backend_registry ?? $this->createBackendRegistryMock(),
          new BasicAuthenticator($mink, static::MINK_PARAMS),
          static::MINK_PARAMS,
          $parameters ?? static::EXTENSION_PARAMS
      );
  }

}
