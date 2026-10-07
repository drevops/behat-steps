<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Mink\Capability;

/**
 * Capability: the browser evaluates JavaScript.
 *
 * A step executes its script through the Mink session's own API, so an
 * adapter only states whether the browser driver runs JavaScript at all.
 */
interface JavascriptCapabilityInterface {}
