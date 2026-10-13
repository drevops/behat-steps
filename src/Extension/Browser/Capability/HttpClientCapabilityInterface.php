<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Extension\Browser\Capability;

use Symfony\Component\BrowserKit\AbstractBrowser;

/**
 * Capability: expose the browser the Mink session drives.
 *
 * A request sent through that browser becomes the page the session holds. A
 * browser driver controlling a real browser has no such browser in PHP, so
 * only a browser driver wrapping an HTTP client provides this.
 *
 * @see \DrevOps\BehatSteps\WebRawContext::httpPageClient()
 */
interface HttpClientCapabilityInterface {

  /**
   * Returns the browser the browser driver issues its requests through.
   *
   * @return \Symfony\Component\BrowserKit\AbstractBrowser<covariant object, covariant object>
   *   The browser the session drives.
   */
  public function httpClient(): AbstractBrowser;

}
