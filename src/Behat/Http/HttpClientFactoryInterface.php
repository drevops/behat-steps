<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Http;

use Symfony\Component\BrowserKit\AbstractBrowser;

/**
 * Builds the browsers a trait sends its own requests through.
 *
 * Each browser is a fresh instance on the shared transport, so a request sent
 * through it leaves the page the Mink session holds untouched.
 *
 * @see \DrevOps\BehatSteps\Behat\Context\WebRawContext::httpDetachedClient()
 * @see \DrevOps\BehatSteps\Behat\Context\WebRawContext::httpBareClient()
 */
interface HttpClientFactoryInterface {

  /**
   * Creates a browser carrying the site's connection options and nothing else.
   *
   * @param array<string, mixed> $options
   *   Symfony HttpClient options for this browser, merged over the site's.
   *
   * @return \Symfony\Component\BrowserKit\AbstractBrowser<covariant object, covariant object>
   *   A fresh browser with an empty cookie jar.
   */
  public function createBare(array $options = []): AbstractBrowser;

  /**
   * Creates a browser carrying the site's connection options and an identity.
   *
   * The identity's cookies go to the host they were read for and its
   * subdomains. Its headers and credentials go to the site's own host only.
   *
   * @param \DrevOps\BehatSteps\Behat\Http\HttpIdentity $identity
   *   The scenario state to send.
   * @param array<string, mixed> $options
   *   Symfony HttpClient options for this browser, merged over the site's.
   *
   * @return \Symfony\Component\BrowserKit\AbstractBrowser<covariant object, covariant object>
   *   A fresh browser holding the identity.
   */
  public function createDetached(HttpIdentity $identity, array $options = []): AbstractBrowser;

}
