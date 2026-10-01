<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures;

use DrevOps\BehatSteps\Behat\Prerequisite\Prerequisite;
use DrevOps\BehatSteps\Driver\Capability\ModuleCapabilityInterface;

/**
 * Trait with no option to switch it off, needing a module.
 */
trait StepPrerequisiteTrait {

  /**
   * Declares the prerequisites this trait asserts.
   *
   * @return array<int, \DrevOps\BehatSteps\Behat\Prerequisite\Prerequisite>
   *   The prerequisites this trait declares.
   */
  protected function stepPrerequisitePrerequisites(): array {
    return [
      Prerequisite::check(static fn(ModuleCapabilityInterface $driver): bool => $driver->moduleIsEnabled('step'), 'the "step" module is enabled'),
    ];
  }

}
