<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures;

use Behat\Behat\Hook\Scope\ScenarioScope;
use DrevOps\BehatSteps\Behat\Context\RawContext;

/**
 * Context composing two traits that declare options.
 */
class ConfigurableContext extends RawContext {

  use OtherSampleConfigTrait;
  use SampleConfigTrait;
  use SampleExtraConfigTrait;

  /**
   * Public bridge to the protected skip resolution.
   */
  public function callSkipTag(string $name, ScenarioScope $scope): bool {
    return $this->skipTag($name, $scope);
  }

}
