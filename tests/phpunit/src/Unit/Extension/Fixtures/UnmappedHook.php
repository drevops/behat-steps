<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Extension\Fixtures;

use DrevOps\BehatSteps\Extension\Hook\Attribute\HookAttributeInterface;

/**
 * Hook attribute the reader has no call class for.
 *
 * A project can declare its own attribute against the marker interface.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class UnmappedHook implements HookAttributeInterface {}
