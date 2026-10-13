<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Extension\Drupal\Hook\Attribute;

use DrevOps\BehatSteps\Extension\Drupal\Hook\Call\BeforeTermCreate as BeforeTermCreateCall;
use DrevOps\BehatSteps\Extension\Hook\Attribute\HookAttributeInterface;

/**
 * Attribute for methods to run before a taxonomy term is created.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class BeforeTermCreate implements HookAttributeInterface {

  /**
   * {@inheritdoc}
   */
  public function getCallClass(): string {
    return BeforeTermCreateCall::class;
  }

}
