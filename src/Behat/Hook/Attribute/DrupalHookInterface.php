<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Hook\Attribute;

/**
 * Marks the entity creation hook attributes.
 *
 * An attribute takes no argument, because a hook runs for every entity
 * created in its scope.
 */
interface DrupalHookInterface {}
