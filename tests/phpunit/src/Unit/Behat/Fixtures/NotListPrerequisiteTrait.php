<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures;

/**
 * Trait whose prerequisite declaration is not a list.
 */
trait NotListPrerequisiteTrait {

  /**
   * Declares something other than a list of prerequisites.
   */
  protected function notListPrerequisitePrerequisites(): mixed {
    return 'not a list';
  }

}
