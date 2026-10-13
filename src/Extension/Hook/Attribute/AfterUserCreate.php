<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Extension\Hook\Attribute;

use DrevOps\BehatSteps\Extension\Hook\Call\AfterUserCreate as AfterUserCreateCall;

/**
 * Attribute for methods to run after a user is created.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class AfterUserCreate implements HookAttributeInterface {

  /**
   * {@inheritdoc}
   */
  public function getCallClass(): string {
    return AfterUserCreateCall::class;
  }

}
