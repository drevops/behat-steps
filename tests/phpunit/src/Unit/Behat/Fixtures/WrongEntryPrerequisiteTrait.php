<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures;

/**
 * Trait whose prerequisite declaration lists something else.
 */
trait WrongEntryPrerequisiteTrait {

  /**
   * Declares a list holding an object that is not a prerequisite.
   *
   * @return array<int, object>
   *   The list.
   */
  protected function wrongEntryPrerequisitePrerequisites(): array {
    return [new \stdClass()];
  }

}
