<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Mink;

use Behat\Mink\Driver\DriverInterface;

/**
 * Declares which capabilities a Mink driver provides.
 *
 * Mink drivers ship from other packages, so a driver cannot implement the
 * capability interfaces itself. An adapter implements them on the driver's
 * behalf and states which driver it speaks for.
 */
interface BrowserAdapterInterface {

  /**
   * Whether this adapter speaks for the given driver.
   *
   * @param \Behat\Mink\Driver\DriverInterface $driver
   *   The driver a session is running.
   */
  public static function supports(DriverInterface $driver): bool;

}
