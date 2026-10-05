<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Auth;

use Behat\Mink\Exception\UnsupportedDriverActionException;
use Behat\Mink\Mink;
use DrevOps\BehatSteps\Behat\MinkAwareTrait;

/**
 * Applies webserver-level HTTP Basic authentication to the Mink session.
 *
 * This is not user authentication: it carries no user and reads no site
 * configuration. It needs only the Mink session and the configured base URL,
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
    $credentials = $this->findCredentials();

    if ($credentials === NULL) {
      return;
    }

    try {
      $this->getSession()->setBasicAuth($credentials['username'], $credentials['password']);
    }
    catch (UnsupportedDriverActionException) {
      // The active browser driver cannot set basic auth headers (a JavaScript
      // one, for example); those receive credentials via the 'base_url'
      // userinfo.
    }
  }

  /**
   * {@inheritdoc}
   *
   * Credentials are derived from the 'base_url' userinfo
   * ('http://user:pass@host').
   */
  public function findCredentials(): ?array {
    $base_url = (string) $this->getMinkParameter('base_url');
    $name = parse_url($base_url, PHP_URL_USER);

    if (!is_string($name) || $name === '') {
      return NULL;
    }

    $pass = parse_url($base_url, PHP_URL_PASS);

    return [
      // Userinfo is RFC 3986 encoded, where '+' is a literal plus and a space
      // is '%20'. 'urldecode()' would turn a literal '+' into a space, so
      // 'rawurldecode()' is used.
      'username' => rawurldecode($name),
      'password' => is_string($pass) ? rawurldecode($pass) : '',
    ];
  }

}
