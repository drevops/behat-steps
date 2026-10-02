<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures;

use DrevOps\BehatSteps\Backend\Capability\CoreCapabilityInterface;
use DrevOps\BehatSteps\Behat\Prerequisite\Prerequisite;

/**
 * Trait counting how often its prerequisite declaration is built.
 */
trait CountedPrerequisiteTrait {

  /**
   * Number of times the declaration was built.
   */
  public static int $countedPrerequisiteReads = 0;

  /**
   * Declares the prerequisites this trait asserts.
   *
   * @return array<int, \DrevOps\BehatSteps\Behat\Prerequisite\Prerequisite>
   *   The prerequisites this trait declares.
   */
  protected function countedPrerequisitePrerequisites(): array {
    static::$countedPrerequisiteReads++;

    return [Prerequisite::capability(CoreCapabilityInterface::class)];
  }

}
