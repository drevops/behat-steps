<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Driver\Fixtures;

use DrevOps\BehatSteps\Driver\Capability\CreationAliasCapabilityInterface;
use DrevOps\BehatSteps\Driver\Core\CoreInterface;

/**
 * Composite test interface used to exercise the alias-capable Core path.
 *
 * Extends both 'CoreInterface' and 'CreationAliasCapabilityInterface' so
 * a single PHPUnit mock can satisfy 'setCore()' and the
 * 'instanceof CreationAliasCapabilityInterface' guard in the same instance.
 */
interface AliasCapableCoreInterface extends CoreInterface, CreationAliasCapabilityInterface {
}
