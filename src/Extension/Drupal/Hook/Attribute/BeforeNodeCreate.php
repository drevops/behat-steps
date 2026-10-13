<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Extension\Drupal\Hook\Attribute;

use DrevOps\BehatSteps\Extension\Drupal\Hook\Call\BeforeNodeCreate as BeforeNodeCreateCall;
use DrevOps\BehatSteps\Extension\Hook\Attribute\HookAttributeInterface;

/**
 * Attribute for methods to run before a node is created.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class BeforeNodeCreate implements HookAttributeInterface {

  /**
   * {@inheritdoc}
   */
  public function getCallClass(): string {
    return BeforeNodeCreateCall::class;
  }

}
