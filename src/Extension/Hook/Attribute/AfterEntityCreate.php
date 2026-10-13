<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Extension\Hook\Attribute;

use DrevOps\BehatSteps\Extension\Hook\Call\AfterEntityCreate as AfterEntityCreateCall;

/**
 * Attribute for methods to run after a generic entity is created.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class AfterEntityCreate implements HookAttributeInterface {

  /**
   * {@inheritdoc}
   */
  public function getCallClass(): string {
    return AfterEntityCreateCall::class;
  }

}
