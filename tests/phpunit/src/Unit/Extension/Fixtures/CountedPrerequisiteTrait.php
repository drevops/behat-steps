<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Extension\Fixtures;

use DrevOps\BehatSteps\Backend\Capability\ContentCapabilityInterface;
use DrevOps\BehatSteps\Extension\Prerequisite\Prerequisite;

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
   * @return array<int, \DrevOps\BehatSteps\Extension\Prerequisite\Prerequisite>
   *   The prerequisites this trait declares.
   */
  protected function countedPrerequisitePrerequisites(): array {
    static::$countedPrerequisiteReads++;

    return [Prerequisite::capability(ContentCapabilityInterface::class)];
  }

}
