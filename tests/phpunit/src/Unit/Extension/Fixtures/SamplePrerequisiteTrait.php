<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Extension\Fixtures;

use DrevOps\BehatSteps\Backend\Drupal\Capability\CoreCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\ModuleCapabilityInterface;
use DrevOps\BehatSteps\Extension\Config\Option;
use DrevOps\BehatSteps\Extension\Prerequisite\Prerequisite;

/**
 * Trait that can be switched off and needs an in-process backend and a module.
 */
trait SamplePrerequisiteTrait {

  /**
   * Declares the options this trait reads.
   *
   * @return array<int, \DrevOps\BehatSteps\Extension\Config\Option>
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
   * @return array<int, \DrevOps\BehatSteps\Extension\Prerequisite\Prerequisite>
   *   The prerequisites this trait declares.
   */
  protected function samplePrerequisitePrerequisites(): array {
    return [
      Prerequisite::capability(CoreCapabilityInterface::class),
      Prerequisite::check(static fn(ModuleCapabilityInterface $backend): bool => $backend->moduleIsEnabled('sample'), 'the "sample" module is enabled'),
    ];
  }

}
