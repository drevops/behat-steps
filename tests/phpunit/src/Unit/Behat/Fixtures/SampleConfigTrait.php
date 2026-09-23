<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures;

/**
 * Trait declaring one option of each shape the resolution has to read.
 */
trait SampleConfigTrait {

  /**
   * Declares the options this trait reads.
   *
   * @return array<string, array<string, mixed>>
   *   Option declarations keyed by option name.
   */
  protected function sampleConfigSchema(): array {
    return [
      'enabled' => [
        'default' => TRUE,
        'description' => 'Whether the sample hook runs.',
        'tags' => ['sample-off' => FALSE, 'sample-on' => TRUE],
      ],
      'label' => [
        'default' => 'a default',
        'description' => 'A string option.',
      ],
      'limit' => [
        'default' => 7,
        'description' => 'An integer option.',
      ],
      'ratio' => [
        'default' => 0.5,
        'description' => 'A float option.',
      ],
      'anything' => [
        'default' => NULL,
        'description' => 'An option whose declaration names no type.',
      ],
    ];
  }

}
