<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Driver\Unit\Fixtures;

use DrevOps\BehatSteps\Driver\Capability\AuthenticationCapabilityInterface;
use DrevOps\BehatSteps\Driver\Core\CoreInterface;

/**
 * Composite test interface used to exercise the auth-capable Core path.
 *
 * Extends both 'CoreInterface' and 'AuthenticationCapabilityInterface' so
 * a single PHPUnit mock can satisfy 'setCore()' and the
 * 'instanceof AuthenticationCapabilityInterface' guard in the same instance.
 */
interface AuthCapableCoreInterface extends CoreInterface, AuthenticationCapabilityInterface {
}
