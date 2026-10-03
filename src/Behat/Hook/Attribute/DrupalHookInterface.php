<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Hook\Attribute;

/**
 * Contract for the entity creation hook attributes.
 *
 * Each attribute exposes the filter string it was declared with.
 */
interface DrupalHookInterface {

  /**
   * Returns the filter string for this hook.
   */
  public function getFilterString(): ?string;

}
