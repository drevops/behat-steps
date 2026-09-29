<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Mink\Capability;

/**
 * Capability: issue a request outside the page the session is on.
 *
 * A driver speaking to a real browser cannot lend out its HTTP client, so only
 * a driver that is itself an HTTP client provides this.
 */
interface HttpClientCapabilityInterface {

  /**
   * Returns the client the driver issues its requests through.
   *
   * @return object
   *   The BrowserKit client.
   */
  public function httpClient(): object;

}
