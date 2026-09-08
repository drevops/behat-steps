<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Hook\Call;

use DrevOps\BehatSteps\Behat\Hook\Scope\TermScope;

/**
 * Hook call dispatched after a taxonomy term is created.
 */
class AfterTermCreate extends EntityHook {

  /**
   * Initializes the hook.
   *
   * @param string|null $filterString
   *   The filter string the hook was declared with.
   * @param array{class-string<\Behat\Behat\Context\Context>, string}|callable $callable
   *   The context method to call.
   * @param string|null $description
   *   A human readable description of the hook.
   */
  public function __construct(?string $filterString, array|callable $callable, ?string $description = NULL) {
    parent::__construct(TermScope::AFTER, $filterString, $callable, $description);
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return 'AfterTermCreate';
  }

}
