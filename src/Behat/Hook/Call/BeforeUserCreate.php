<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Hook\Call;

use DrevOps\BehatSteps\Behat\Hook\Scope\UserScope;

/**
 * Hook call dispatched before a user is created.
 */
final class BeforeUserCreate extends EntityHook {

  /**
   * Initializes the hook.
   *
   * @param array{class-string<\Behat\Behat\Context\Context>, string}|callable $callable
   *   The context method to call.
   * @param string|null $description
   *   A human readable description of the hook.
   */
  public function __construct(array|callable $callable, ?string $description = NULL) {
    parent::__construct(UserScope::BEFORE, $callable, $description);
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return 'BeforeUserCreate';
  }

}
