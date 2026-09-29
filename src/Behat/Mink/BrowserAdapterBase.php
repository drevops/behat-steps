<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Mink;

use Behat\Mink\Driver\DriverInterface;

/**
 * Holds the driver an adapter speaks for.
 */
abstract class BrowserAdapterBase implements BrowserAdapterInterface {

  /**
   * Constructs an adapter around a Mink driver.
   *
   * @param \Behat\Mink\Driver\DriverInterface $driver
   *   The driver the session is running.
   */
  public function __construct(protected readonly DriverInterface $driver) {
  }

}
