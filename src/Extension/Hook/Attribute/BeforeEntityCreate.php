<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Extension\Hook\Attribute;

use DrevOps\BehatSteps\Extension\Hook\Call\BeforeEntityCreate as BeforeEntityCreateCall;

/**
 * Attribute for methods to run before a generic entity is created.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class BeforeEntityCreate implements HookAttributeInterface {

  /**
   * {@inheritdoc}
   */
  public function getCallClass(): string {
    return BeforeEntityCreateCall::class;
  }

}
