<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Http;

/**
 * The scenario state a detached browser carries to the site.
 *
 * Holds what a request needs to act as the scenario's visitor: the session's
 * cookies, the headers the steps set and the site's basic-auth credentials.
 * Connection options are not part of it; the shared transport carries those.
 *
 * @see \DrevOps\BehatSteps\Behat\Http\HttpClientFactoryInterface::createDetached()
 */
class HttpIdentity {

  /**
   * Constructs an HttpIdentity object.
   *
   * @param array<string, string> $cookies
   *   Cookie values keyed by name, in the form they travel on the wire.
   * @param string $cookieUrl
   *   The URL the cookies were read for. They are sent to its host and that
   *   host's subdomains only.
   * @param array<string, string> $headers
   *   Header values keyed by name.
   * @param array{username: string, password: string}|null $credentials
   *   Basic-auth credentials for the site, or NULL when it needs none.
   */
  public function __construct(
    public readonly array $cookies = [],
    public readonly string $cookieUrl = '',
    public readonly array $headers = [],
    public readonly ?array $credentials = NULL,
  ) {
  }

}
