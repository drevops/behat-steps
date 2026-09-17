<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat;

/**
 * Declares extension parameter availability.
 */
interface ParametersAwareInterface {

  /**
   * Sets parameters provided by the extension.
   *
   * @param array<string, mixed> $parameters
   *   The extension parameters.
   *
   * @internal
   *   Injection point called by the context initializer.
   */
  public function setParameters(array $parameters): void;

  /**
   * Returns a specific extension parameter.
   *
   * @param string $name
   *   Parameter name.
   *
   * @return mixed
   *   The value, or NULL if the parameter is not set.
   */
  public function getParameter(string $name): mixed;

}
