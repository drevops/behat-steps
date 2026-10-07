<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Hook\Call;

use Behat\Behat\Context\ContextMethodCallableFactory;
use Behat\Testwork\Hook\Call\RuntimeFilterableHook;
use Behat\Testwork\Hook\Scope\HookScope;

/**
 * Base class for the entity creation hook calls.
 */
abstract class EntityHook extends RuntimeFilterableHook {

  /**
   * Initializes the hook.
   *
   * @param string $scope_name
   *   The name of the scope the hook is dispatched in.
   * @param string|null $filter_string
   *   The filter string the hook was declared with.
   * @param array{class-string<\Behat\Behat\Context\Context>, string}|callable $callable
   *   The context method to call.
   * @param string|null $description
   *   A human readable description of the hook.
   */
  public function __construct(string $scope_name, ?string $filter_string, array|callable $callable, ?string $description = NULL) {
    parent::__construct($scope_name, $filter_string, $this->resolveCallable($callable), $description);
  }

  /**
   * {@inheritdoc}
   *
   * An entity creation scope carries no tags to filter on, so a hook declared
   * with a filter string matches nothing.
   */
  public function filterMatches(HookScope $scope): bool {
    return $this->getFilterString() === NULL;
  }

  /**
   * Resolves the context method to the form the installed Behat accepts.
   *
   * Behat 4 accepts only a callable, and a '[class-string, method]' pair is
   * not callable for an instance method, so 'ContextMethodCallableFactory'
   * converts it. Behat 3 has no factory and accepts the pair unchanged.
   *
   * The return type is 'mixed' because each major accepts a different type,
   * and PHPStan analyzes against the installed one only.
   *
   * @param array{class-string<\Behat\Behat\Context\Context>, string}|callable $callable
   *   The context method to call.
   *
   * @return mixed
   *   The callable, or the unchanged pair on Behat 3.
   */
  protected function resolveCallable(array|callable $callable): mixed {
    if (is_callable($callable) || !class_exists(ContextMethodCallableFactory::class)) {
      return $callable;
    }

    // @codeCoverageIgnoreStart
    return ContextMethodCallableFactory::makeCallable($callable[0], new \ReflectionMethod($callable[0], $callable[1]));
    // @codeCoverageIgnoreEnd
  }

}
