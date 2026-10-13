<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Extension\Hook\Attribute;

use DrevOps\BehatSteps\Extension\Hook\Call\BeforeUserCreate as BeforeUserCreateCall;

/**
 * Attribute for methods to run before a user is created.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class BeforeUserCreate implements HookAttributeInterface {

  /**
   * {@inheritdoc}
   */
  public function getCallClass(): string {
    return BeforeUserCreateCall::class;
  }

}
