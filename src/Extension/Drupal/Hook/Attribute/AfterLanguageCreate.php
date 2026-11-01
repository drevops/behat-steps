<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Extension\Drupal\Hook\Attribute;

use DrevOps\BehatSteps\Extension\Drupal\Hook\Call\AfterLanguageCreate as AfterLanguageCreateCall;
use DrevOps\BehatSteps\Extension\Hook\Attribute\HookAttributeInterface;

/**
 * Attribute for methods to run after a language is created.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class AfterLanguageCreate implements HookAttributeInterface {

  /**
   * {@inheritdoc}
   */
  public function getCallClass(): string {
    return AfterLanguageCreateCall::class;
  }

}
