<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Hook\Call;

use DrevOps\BehatSteps\Behat\Hook\Scope\EntityScopeInterface;

/**
 * Hook call dispatched after a generic entity is created.
 */
final class AfterEntityCreate extends EntityHook {

  /**
   * Initializes the hook.
   *
   * @param array{class-string<\Behat\Behat\Context\Context>, string}|callable $callable
   *   The context method to call.
   * @param string|null $description
   *   A human readable description of the hook.
   */
  public function __construct(array|callable $callable, ?string $description = NULL) {
    parent::__construct(EntityScopeInterface::AFTER, $callable, $description);
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return 'AfterEntityCreate';
  }

}
