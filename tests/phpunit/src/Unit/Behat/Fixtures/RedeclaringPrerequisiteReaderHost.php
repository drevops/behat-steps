<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures;

use DrevOps\BehatSteps\Behat\Prerequisite\Prerequisite;
use DrevOps\BehatSteps\Driver\Capability\ModuleCapabilityInterface;

/**
 * Host redeclaring the prerequisites of a trait it inherits.
 */
class RedeclaringPrerequisiteReaderHost extends PrerequisiteReaderHost {

  /**
   * Declares the prerequisites this host asserts in place of the trait's.
   *
   * @return array<int, \DrevOps\BehatSteps\Behat\Prerequisite\Prerequisite>
   *   The prerequisites this host declares.
   */
  protected function countedPrerequisitePrerequisites(): array {
    return [Prerequisite::capability(ModuleCapabilityInterface::class)];
  }

}
