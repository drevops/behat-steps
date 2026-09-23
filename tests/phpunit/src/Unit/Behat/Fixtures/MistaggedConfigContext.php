<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures;

use DrevOps\BehatSteps\Behat\Context\RawContext;

/**
 * Context whose declaration lists its tags as a scalar.
 */
class MistaggedConfigContext extends RawContext {

  /**
   * Declares an option whose tags are not a map.
   *
   * @return array<string, array<string, mixed>>
   *   Option declarations keyed by option name.
   */
  protected function mistaggedConfigSchema(): array {
    return [
      'enabled' => [
        'default' => TRUE,
        'description' => 'Declared with a tag name rather than a map.',
        'tags' => 'mistagged-off',
      ],
    ];
  }

}
