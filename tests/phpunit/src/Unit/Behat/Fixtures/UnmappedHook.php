<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures;

use DrevOps\BehatSteps\Behat\Hook\Attribute\DrupalHookInterface;

/**
 * Hook attribute the reader has no call class for.
 *
 * A project can declare its own attribute against the marker interface.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class UnmappedHook implements DrupalHookInterface {}
