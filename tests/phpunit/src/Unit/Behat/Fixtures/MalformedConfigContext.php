<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures;

use DrevOps\BehatSteps\Behat\Context\WebRawContext;

/**
 * Context whose declarations omit what every declaration needs.
 */
class MalformedConfigContext extends WebRawContext {

  /**
   * Declares an option without a default or a description.
   *
   * @return array<string, array<string, mixed>>
   *   Option declarations keyed by option name.
   */
  protected function brokenConfigSchema(): array {
    return ['label' => ['description' => 'Declared without a default.']];
  }

}
