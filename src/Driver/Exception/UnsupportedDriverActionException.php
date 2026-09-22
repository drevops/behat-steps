<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Driver\Exception;

use DrevOps\BehatSteps\Driver\DriverInterface;

/**
 * Unsupported driver action.
 */
class UnsupportedDriverActionException extends Exception {

  /**
   * Initializes exception.
   *
   * @param string $template
   *   A message template describing what is unsupported. A '%s' placeholder
   *   is filled with the driver's class name when a driver is given.
   * @param \DrevOps\BehatSteps\Driver\DriverInterface|null $driver
   *   The driver that cannot perform the action, or NULL when no driver could
   *   be resolved to attempt it.
   * @param int $code
   *   The exception code.
   * @param \Exception $previous
   *   Previous exception.
   */
  public function __construct(string $template, ?DriverInterface $driver = NULL, int $code = 0, ?\Exception $previous = NULL) {
    $message = $driver instanceof DriverInterface ? sprintf($template, $driver::class) : $template;

    parent::__construct($message, $driver, $code, $previous);
  }

}
