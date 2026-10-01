<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Manager;

use Behat\Mink\Driver\DriverInterface;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use Behat\Mink\Mink;
use Behat\Mink\Session;
use DrevOps\BehatSteps\Behat\Manager\BasicAuthenticator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests applying webserver-level basic auth to the session.
 */
#[CoversClass(BasicAuthenticator::class)]
class BasicAuthenticatorTest extends TestCase {

  /**
   * Tests that credentials are parsed from the configured base URL.
   *
   * @param string $base_url
   *   The configured Mink 'base_url'.
   * @param array{0: string, 1: string}|null $expected
   *   The [username, password] expected to be applied, or NULL when basic
   *   auth should not be applied at all.
   */
  #[DataProvider('dataProviderApplyBasicAuth')]
  public function testApplyBasicAuth(string $base_url, ?array $expected): void {
    $session = $this->createMock(Session::class);

    if ($expected === NULL) {
      $session->expects($this->never())->method('setBasicAuth');
    }
    else {
      $session->expects($this->once())->method('setBasicAuth')->with($expected[0], $expected[1]);
    }

    $this->createManager($session, $base_url)->applyBasicAuth();
  }

  public static function dataProviderApplyBasicAuth(): \Iterator {
    yield 'base_url userinfo is used' => [
      'http://bob:s3cret@localhost',
      ['bob', 's3cret'],
    ];
    yield 'base_url user without password uses empty password' => [
      'http://bob@localhost',
      ['bob', ''],
    ];
    yield 'url-encoded userinfo is decoded' => [
      'http://bob%40corp:p%40ss@localhost',
      ['bob@corp', 'p@ss'],
    ];
    yield 'literal plus in userinfo is preserved' => [
      'http://bob+corp:p+ss@localhost',
      ['bob+corp', 'p+ss'],
    ];
    yield 'no credentials is a no-op' => [
      'http://localhost',
      NULL,
    ];
  }

  /**
   * Tests the credentials read from the configured base URL.
   *
   * @param string $base_url
   *   The configured Mink 'base_url'.
   * @param array{username: string, password: string}|null $expected
   *   The credentials expected, or NULL when the base URL carries none.
   */
  #[DataProvider('dataProviderFindCredentials')]
  public function testFindCredentials(string $base_url, ?array $expected): void {
    $this->assertSame($expected, $this->createManager($this->createMock(Session::class), $base_url)->findCredentials());
  }

  public static function dataProviderFindCredentials(): \Iterator {
    yield 'username and password' => ['http://bob:s3cret@localhost', ['username' => 'bob', 'password' => 's3cret']];
    yield 'username only' => ['http://bob@localhost', ['username' => 'bob', 'password' => '']];
    yield 'no userinfo' => ['http://localhost', NULL];
  }

  /**
   * Tests that an unsupported-driver exception is swallowed.
   *
   * JavaScript drivers cannot set basic auth headers and throw; the call must
   * be a no-op for them rather than aborting the scenario.
   */
  public function testApplyBasicAuthIgnoresUnsupportedDriver(): void {
    $session = $this->createMock(Session::class);
    $session->expects($this->once())->method('setBasicAuth')->willThrowException(new UnsupportedDriverActionException('Basic auth setup is not supported by %s', $this->createMock(DriverInterface::class)));

    $this->createManager($session, 'http://alice:secret@localhost')->applyBasicAuth();
  }

  /**
   * Builds an authenticator over a session and a configured base URL.
   */
  protected function createManager(Session $session, string $base_url): BasicAuthenticator {
    $mink = new Mink(['default' => $session]);
    $mink->setDefaultSessionName('default');

    return new BasicAuthenticator($mink, ['base_url' => $base_url]);
  }

}
