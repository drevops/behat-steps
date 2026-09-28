<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures;

/**
 * Trait whose prefix extends another switchable group's.
 *
 * 'sampleExtra' starts with 'sample', and both groups declare a switch. A hook
 * named after this one matches two groups, and the longer prefix takes
 * precedence.
 */
trait SampleExtraConfigTrait {

  /**
   * Declares the options this trait reads.
   *
   * @return array<string, array<string, mixed>>
   *   Option declarations keyed by option name.
   */
  protected function sampleExtraConfigSchema(): array {
    return [
      'enabled' => [
        'default' => TRUE,
        'description' => 'Whether the sample extra hook runs.',
      ],
    ];
  }

}
