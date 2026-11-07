<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Extension\Browser\Capability;

/**
 * Capability: override the time and the timezone a page's JavaScript reads.
 */
interface ClockCapabilityInterface {

  /**
   * Sets the time the clock of each document loaded from now on reads.
   *
   * The clock keeps ticking from that time, and stays continuous across page
   * loads. The document already loaded keeps the real time.
   *
   * @param int $timestamp
   *   The Unix timestamp, in seconds.
   */
  public function clockSetTime(int $timestamp): void;

  /**
   * Sets the timezone the browser formats and reports dates in.
   *
   * @param string $timezone
   *   An IANA timezone name, such as 'Australia/Melbourne'.
   */
  public function clockSetTimezone(string $timezone): void;

  /**
   * Restores the real time and the browser's own timezone.
   */
  public function clockReset(): void;

}
