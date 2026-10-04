<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend;

/**
 * Contract for the blackbox backend.
 *
 * Performs no backend operations. Implementations satisfy only the base
 * backend contract and MUST NOT implement any interface in the
 * 'DrevOps\BehatSteps\Backend\Capability' namespace. An 'instanceof
 * BlackboxBackendInterface' check is therefore a reliable
 * negative-capability guarantee.
 */
interface BlackboxBackendInterface extends BackendInterface {

}
