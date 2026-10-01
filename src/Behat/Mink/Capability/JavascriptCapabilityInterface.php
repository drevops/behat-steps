<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Mink\Capability;

/**
 * Capability: the browser evaluates JavaScript.
 *
 * The interface declares no methods. A step executes its script through the
 * Mink session's own API, so the only question an adapter answers here is
 * whether the browser driver runs JavaScript at all; adding 'execute' and
 * 'evaluate' methods no caller would use would repeat the unused-capability
 * problem this layer exists to remove.
 */
interface JavascriptCapabilityInterface {

}
