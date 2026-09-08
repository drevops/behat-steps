<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Hook\Attribute;

/**
 * Attribute for methods to run before a taxonomy term is created.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class BeforeTermCreate implements DrupalHookInterface {

  use FilterStringTrait;

}
