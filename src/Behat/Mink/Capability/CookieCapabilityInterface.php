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
   * Values come back in wire form, exactly as the browser stores them, so a
   * caller building a 'Cookie' header passes them straight through and a
   * caller asserting on a value decodes first.
   *
   * The name and the value are the whole contract. A BrowserKit cookie jar
   * holds several objects per name across domains and paths and exposes only
   * the resolved value for a URL, not the object it resolved, so any further
   * attribute would be reliable on some drivers and a guess on others.
   *
   * @return array<int, array{name: string, value: string}>
   *   One entry per cookie name.
   */
  public function cookieGetAll(): array;

}
