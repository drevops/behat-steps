<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Mink\Capability;

use Symfony\Component\BrowserKit\AbstractBrowser;

/**
 * Capability: lend out the browser the Mink session drives.
 *
 * A request sent through that browser becomes the page the session holds. A
 * driver speaking to a real browser has no such browser in PHP, so only a
 * driver that is itself an HTTP client provides this.
 *
 * @see \DrevOps\BehatSteps\Behat\Context\WebRawContext::httpPageClient()
 */
interface HttpClientCapabilityInterface {

  /**
   * Returns the browser the driver issues its requests through.
   *
   * @return \Symfony\Component\BrowserKit\AbstractBrowser<covariant object, covariant object>
   *   The browser the session drives.
   */
  public function httpClient(): AbstractBrowser;

}
