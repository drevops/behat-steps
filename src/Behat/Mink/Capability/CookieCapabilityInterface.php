<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Mink\Capability;

/**
 * Capability: read the cookies the browser is holding.
 */
interface CookieCapabilityInterface {

  /**
   * Returns every cookie the browser currently holds, 1 per name.
   *
   * Values are in wire form, as the browser stores them, so they fit
   * a 'Cookie' header as is and need decoding before an assertion.
   *
   * The name and value are the whole contract, because any further
   * attribute would be reliable on some browser drivers and a guess on
   * others. A BrowserKit cookie jar holds several objects per name across
   * domains and paths and exposes the value resolved for a URL, not the
   * object.
   *
   * @return array<int, array{name: string, value: string}>
   *   1 entry per cookie name.
   */
  public function cookieGetAll(): array;

}
