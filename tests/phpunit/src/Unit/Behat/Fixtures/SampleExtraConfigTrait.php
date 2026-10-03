<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures;

use DrevOps\BehatSteps\Behat\Config\Option;

/**
 * Trait whose group name extends another switchable group's.
 */
trait SampleExtraConfigTrait {

  /**
   * Declares the options this trait reads.
   *
   * @return array<int, \DrevOps\BehatSteps\Behat\Config\Option>
   *   The options this trait declares.
   */
  protected function sampleExtraConfigSchema(): array {
    return [
      new Option('enabled', default: TRUE, description: 'Whether the sample extra hook runs.'),
    ];
  }

}
