<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Mink\Capability;

/**
 * Capability: the browser evaluates JavaScript.
 *
 * The interface declares no methods. A step executes its script through the
 * Mink session's own API, so an adapter only states whether the browser
 * driver runs JavaScript at all.
 *
 * 'execute' and 'evaluate' methods would have no caller, so they would add
 * the unused-capability problem this layer removes.
 */
interface JavascriptCapabilityInterface {

}
