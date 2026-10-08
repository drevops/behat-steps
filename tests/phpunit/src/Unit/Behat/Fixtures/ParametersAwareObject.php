<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures;

use DrevOps\BehatSteps\Behat\Config\ParametersTrait;
use DrevOps\BehatSteps\Behat\Context\ParametersAwareInterface;

/**
 * Minimal host for 'ParametersTrait'.
 */
class ParametersAwareObject implements ParametersAwareInterface {

  use ParametersTrait;

}
