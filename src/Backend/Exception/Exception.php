<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Exception;

use DrevOps\BehatSteps\Backend\BackendInterface;

/**
 * Base exception of the backend layer.
 */
abstract class Exception extends \RuntimeException {

  /**
   * Initializes a backend layer exception.
   *
   * @param string $message
   *   The exception message.
   * @param \DrevOps\BehatSteps\Backend\BackendInterface $backend
   *   The backend where the exception occurred.
   * @param int $code
   *   Optional exception code. Defaults to 0.
   * @param \Exception $previous
   *   Optional previous exception that was thrown.
   */
  public function __construct(string $message, protected readonly ?BackendInterface $backend = NULL, int $code = 0, ?\Exception $previous = NULL) {
    parent::__construct($message, $code, $previous);
  }

  /**
   * Returns the backend where the exception occurred.
   *
   * @return \DrevOps\BehatSteps\Backend\BackendInterface|null
   *   The backend where the exception occurred, or NULL if not set.
   */
  public function getBackend(): ?BackendInterface {
    return $this->backend;
  }

}
