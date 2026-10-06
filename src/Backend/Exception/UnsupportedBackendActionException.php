<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Exception;

use DrevOps\BehatSteps\Backend\BackendInterface;

/**
 * Unsupported backend action.
 */
final class UnsupportedBackendActionException extends Exception {

  /**
   * Initializes exception.
   *
   * @param string $template
   *   A message template describing what is unsupported. A '%s' placeholder
   *   is filled with the backend's class name when a backend is given.
   * @param \DrevOps\BehatSteps\Backend\BackendInterface|null $backend
   *   The backend that cannot perform the action, or NULL when no backend
   *   could be resolved to attempt it.
   * @param int $code
   *   The exception code.
   * @param \Exception $previous
   *   Previous exception.
   */
  public function __construct(string $template, ?BackendInterface $backend = NULL, int $code = 0, ?\Exception $previous = NULL) {
    $message = $backend instanceof BackendInterface ? sprintf($template, $backend::class) : $template;

    parent::__construct($message, $backend, $code, $previous);
  }

}
