<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures;

use Behat\Behat\Hook\Scope\ScenarioScope;
use DrevOps\BehatSteps\Behat\Context\WebRawContext;

/**
 * Context composing 3 traits that declare options.
 */
class ConfigurableContext extends WebRawContext {

  use OtherSampleConfigTrait;
  use SampleConfigTrait;
  use SampleExtraConfigTrait;

  /**
   * Public bridge to the protected skip resolution.
   */
  public function callSkipTag(string $trait, ScenarioScope $scope): bool {
    return $this->skipTag($trait, $scope);
  }

}
