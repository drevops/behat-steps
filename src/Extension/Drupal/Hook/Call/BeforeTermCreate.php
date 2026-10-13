<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Extension\Drupal\Hook\Call;

use DrevOps\BehatSteps\Extension\Drupal\Hook\Scope\TermScope;
use DrevOps\BehatSteps\Extension\Hook\Call\EntityHook;

/**
 * Hook call dispatched before a taxonomy term is created.
 */
final class BeforeTermCreate extends EntityHook {

  /**
   * Initializes the hook.
   *
   * @param array{class-string<\Behat\Behat\Context\Context>, string}|callable $callable
   *   The context method to call.
   * @param string|null $description
   *   A human readable description of the hook.
   */
  public function __construct(array|callable $callable, ?string $description = NULL) {
    parent::__construct(TermScope::BEFORE, $callable, $description);
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return 'BeforeTermCreate';
  }

}
