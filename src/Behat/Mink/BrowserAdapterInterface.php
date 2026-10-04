<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Mink;

use Behat\Mink\Driver\DriverInterface;

/**
 * Declares which capabilities a browser driver provides.
 *
 * Browser drivers are defined in other packages, so one cannot implement the
 * capability interfaces itself. An adapter implements them on its behalf and
 * declares which browser driver it supports.
 */
interface BrowserAdapterInterface {

  /**
   * Whether this adapter supports the given browser driver.
   *
   * @param \Behat\Mink\Driver\DriverInterface $driver
   *   The browser driver a session is running.
   */
  public static function supports(DriverInterface $driver): bool;

}
