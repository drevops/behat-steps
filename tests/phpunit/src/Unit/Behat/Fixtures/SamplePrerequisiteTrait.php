<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures;

use DrevOps\BehatSteps\Behat\Config\Option;
use DrevOps\BehatSteps\Behat\Prerequisite\Prerequisite;
use DrevOps\BehatSteps\Driver\Capability\CoreCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\ModuleCapabilityInterface;

/**
 * Trait that can be switched off and needs an in-process driver and a module.
 */
trait SamplePrerequisiteTrait {

  /**
   * Declares the options this trait reads.
   *
   * @return array<int, \DrevOps\BehatSteps\Behat\Config\Option>
   *   The options this trait declares.
   */
  protected function samplePrerequisiteConfigSchema(): array {
    return [
      new Option('enabled', default: TRUE, description: 'Whether the sample hooks run.'),
    ];
  }

  /**
   * Declares the prerequisites this trait asserts.
   *
   * @return array<int, \DrevOps\BehatSteps\Behat\Prerequisite\Prerequisite>
   *   The prerequisites this trait declares.
   */
  protected function samplePrerequisitePrerequisites(): array {
    return [
      Prerequisite::capability(CoreCapabilityInterface::class),
      Prerequisite::check(static fn(ModuleCapabilityInterface $driver): bool => $driver->moduleIsEnabled('sample'), 'the "sample" module is enabled'),
    ];
  }

}
