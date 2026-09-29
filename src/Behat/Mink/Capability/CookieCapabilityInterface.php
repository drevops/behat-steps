<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Mink\Capability;

/**
 * Capability: read the cookies the browser is holding.
 */
interface CookieCapabilityInterface {

  /**
   * Returns every cookie the browser currently holds.
   *
   * Values come back in wire form, exactly as the browser stores them, so a
   * caller building a 'Cookie' header passes them straight through and a
   * caller asserting on a value decodes first.
   *
   * @return array<int, array{name: string, value: string, secure: bool}>
   *   One entry per cookie.
   */
  public function cookieGetAll(): array;

}
