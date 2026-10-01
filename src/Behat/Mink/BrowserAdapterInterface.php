<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Mink;

use Behat\Mink\Driver\DriverInterface;

/**
 * Declares which capabilities a browser driver provides.
 *
 * Browser drivers ship from other packages, so one cannot implement the
 * capability interfaces itself. An adapter implements them on its behalf and
 * states which browser driver it speaks for.
 */
interface BrowserAdapterInterface {

  /**
   * Whether this adapter speaks for the given browser driver.
   *
   * @param \Behat\Mink\Driver\DriverInterface $driver
   *   The browser driver a session is running.
   */
  public static function supports(DriverInterface $driver): bool;

}
