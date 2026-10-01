<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Mink;

use Behat\Mink\Driver\DriverInterface;

/**
 * Holds the browser driver an adapter speaks for.
 */
abstract class BrowserAdapterBase implements BrowserAdapterInterface {

  /**
   * Constructs an adapter around a browser driver.
   *
   * @param \Behat\Mink\Driver\DriverInterface $driver
   *   The browser driver the session is running.
   */
  public function __construct(protected readonly DriverInterface $driver) {
  }

}
