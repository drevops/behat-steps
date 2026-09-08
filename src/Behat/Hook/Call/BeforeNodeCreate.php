<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Hook\Call;

use DrevOps\BehatSteps\Behat\Hook\Scope\NodeScope;

/**
 * Hook call dispatched before a node is created.
 */
class BeforeNodeCreate extends EntityHook {

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
    parent::__construct(NodeScope::BEFORE, $filterString, $callable, $description);
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return 'BeforeNodeCreate';
  }

}
