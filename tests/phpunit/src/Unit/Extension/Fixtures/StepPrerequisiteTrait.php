<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Extension\Fixtures;

use DrevOps\BehatSteps\Backend\Drupal\Capability\ModuleCapabilityInterface;
use DrevOps\BehatSteps\Extension\Prerequisite\Prerequisite;

/**
 * Trait with no option to switch it off, needing a module.
 */
trait StepPrerequisiteTrait {

  /**
   * Declares the prerequisites this trait asserts.
   *
   * @return array<int, \DrevOps\BehatSteps\Extension\Prerequisite\Prerequisite>
   *   The prerequisites this trait declares.
   */
  protected function stepPrerequisitePrerequisites(): array {
    return [
      Prerequisite::check(static fn(ModuleCapabilityInterface $backend): bool => $backend->moduleIsEnabled('step'), 'the "step" module is enabled'),
    ];
  }

}
