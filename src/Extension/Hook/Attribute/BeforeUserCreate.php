<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Extension\Hook\Attribute;

/**
 * Attribute for methods to run before a user is created.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class BeforeUserCreate implements HookAttributeInterface {}
