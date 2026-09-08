<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Hook\Attribute;

/**
 * Marker interface for the entity creation hook attributes.
 */
interface DrupalHookInterface {

  /**
   * Returns the filter string for this hook.
   */
  public function getFilterString(): ?string;

}
