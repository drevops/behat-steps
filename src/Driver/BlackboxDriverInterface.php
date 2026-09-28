<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Driver;

/**
 * Contract for the blackbox driver.
 *
 * Performs no backend operations. Implementations satisfy only the base
 * driver contract and MUST NOT implement any interface in the
 * 'DrevOps\BehatSteps\Driver\Capability' namespace, so 'instanceof
 * BlackboxDriverInterface' is a reliable negative-capability guarantee.
 */
interface BlackboxDriverInterface extends DriverInterface {

}
