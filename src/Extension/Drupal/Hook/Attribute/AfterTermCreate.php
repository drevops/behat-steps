<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Extension\Drupal\Hook\Attribute;

use DrevOps\BehatSteps\Extension\Drupal\Hook\Call\AfterTermCreate as AfterTermCreateCall;
use DrevOps\BehatSteps\Extension\Hook\Attribute\HookAttributeInterface;

/**
 * Attribute for methods to run after a taxonomy term is created.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class AfterTermCreate implements HookAttributeInterface {

  /**
   * {@inheritdoc}
   */
  public function getCallClass(): string {
    return AfterTermCreateCall::class;
  }

}
