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
final readonly class HttpIdentity {

  /**
   * Constructs an HttpIdentity object.
   *
   * @param array<string, string> $cookies
   *   Cookie values keyed by name, in wire form.
   * @param string|null $cookieUrl
   *   The URL the cookies were read for, or NULL before the session opens a
   *   page. They are sent to its host and that host's subdomains only.
   * @param array<string, string> $headers
   *   Header values keyed by name.
   * @param array{username: string, password: string}|null $credentials
   *   Basic-auth credentials for the site, or NULL when it needs none.
   */
  public function __construct(
    public array $cookies = [],
    public ?string $cookieUrl = NULL,
    public array $headers = [],
    public ?array $credentials = NULL,
  ) {}

}
