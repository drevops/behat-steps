<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Mink\Capability;

/**
 * Capability: set a header the browser sends with each request.
 *
 * A WebDriver session cannot add request headers, so a driver that speaks
 * WebDriver does not provide this.
 */
interface RequestHeaderCapabilityInterface {

  /**
   * Sets a request header for subsequent requests.
   *
   * @param string $name
   *   The header name.
   * @param string $value
   *   The header value. An empty string clears the header.
   */
  public function requestHeaderSet(string $name, string $value): void;

}
