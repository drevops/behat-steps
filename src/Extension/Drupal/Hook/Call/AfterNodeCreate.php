<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Extension\Drupal\Hook\Call;

use DrevOps\BehatSteps\Extension\Drupal\Hook\Scope\NodeScope;
use DrevOps\BehatSteps\Extension\Hook\Call\EntityHook;

/**
 * Hook call dispatched after a node is created.
 */
final class AfterNodeCreate extends EntityHook {

  /**
   * Initializes the hook.
   *
   * @param array{class-string<\Behat\Behat\Context\Context>, string}|callable $callable
   *   The context method to call.
   * @param string|null $description
   *   A human readable description of the hook.
   */
  public function __construct(array|callable $callable, ?string $description = NULL) {
    parent::__construct(NodeScope::AFTER, $callable, $description);
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return 'AfterNodeCreate';
  }

}
