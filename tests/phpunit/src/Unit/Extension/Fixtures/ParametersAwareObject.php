<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Extension\Fixtures;

use DrevOps\BehatSteps\Extension\Config\ParametersTrait;
use DrevOps\BehatSteps\Extension\Context\ParametersAwareInterface;

/**
 * Minimal host for 'ParametersTrait'.
 */
class ParametersAwareObject implements ParametersAwareInterface {

  use ParametersTrait;

}
