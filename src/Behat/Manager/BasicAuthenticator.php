<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Manager;

use Behat\Mink\Exception\UnsupportedDriverActionException;
use Behat\Mink\Mink;
use DrevOps\BehatSteps\Behat\MinkAwareTrait;

/**
 * Applies webserver-level HTTP Basic authentication to the Mink session.
 *
 * This is not user authentication. It carries no user, reads no site
 * configuration and needs only the Mink session and the configured base URL,
 * so a suite for a site behind basic auth uses it without any Drupal site.
 */
class BasicAuthenticator implements BasicAuthenticatorInterface {

  use MinkAwareTrait;

  /**
   * Constructs a BasicAuthenticator object.
   *
   * @param \Behat\Mink\Mink $mink
   *   The Mink instance.
   * @param array<string, mixed> $mink_parameters
   *   Mink configuration parameters.
   */
  public function __construct(Mink $mink, array $mink_parameters) {
    $this->setMink($mink);
    $this->setMinkParameters($mink_parameters);
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
    $name = parse_url($base_url, PHP_URL_USER);

    if (!is_string($name) || $name === '') {
      return NULL;
    }

    $pass = parse_url($base_url, PHP_URL_PASS);

    return [
      // Userinfo is RFC 3986 encoded, where '+' is a literal plus and spaces
      // are '%20', so decode with rawurldecode() rather than urldecode()
      // (which would turn a literal '+' into a space).
      'username' => rawurldecode($name),
      'password' => is_string($pass) ? rawurldecode($pass) : '',
    ];
  }

}
