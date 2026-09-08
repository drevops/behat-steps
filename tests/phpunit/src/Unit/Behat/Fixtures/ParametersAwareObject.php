<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures;

use DrevOps\BehatSteps\Behat\ParametersAwareInterface;
use DrevOps\BehatSteps\Behat\ParametersTrait;

/**
 * Minimal host for 'ParametersTrait'.
 */
class ParametersAwareObject implements ParametersAwareInterface {

  use ParametersTrait;

}
