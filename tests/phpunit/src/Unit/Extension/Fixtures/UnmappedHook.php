<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Extension\Fixtures;

use DrevOps\BehatSteps\Extension\Hook\Attribute\HookAttributeInterface;

/**
 * Hook attribute naming a call class that is no hook call.
 *
 * A project can declare its own attribute against the marker interface.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class UnmappedHook implements HookAttributeInterface {

  /**
   * {@inheritdoc}
   */
  public function getCallClass(): string {
    return \stdClass::class;
  }

}
