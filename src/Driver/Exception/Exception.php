<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Driver\Exception;

use DrevOps\BehatSteps\Driver\DriverInterface;

/**
 * Drupal driver manager base exception class.
 */
abstract class Exception extends \Exception {

  /**
   * Initializes Drupal driver manager exception.
   *
   * @param string $message
   *   The exception message.
   * @param \DrevOps\BehatSteps\Driver\DriverInterface $driver
   *   The driver where the exception occurred.
   * @param int $code
   *   Optional exception code. Defaults to 0.
   * @param \Exception $previous
   *   Optional previous exception that was thrown.
   */
  public function __construct(string $message, protected readonly ?DriverInterface $driver = NULL, int $code = 0, ?\Exception $previous = NULL) {
    parent::__construct($message, $code, $previous);
  }

  /**
   * Returns exception driver.
   *
   * @return \DrevOps\BehatSteps\Driver\DriverInterface|null
   *   The driver where the exception occurred, or NULL if not set.
   */
  public function getDriver(): ?DriverInterface {
    return $this->driver;
  }

}
