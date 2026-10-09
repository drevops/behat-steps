<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Hook\Call;

use Behat\Behat\Context\ContextMethodCallableFactory;
use Behat\Testwork\Hook\Call\RuntimeHook;

/**
 * Base class for the entity creation hook calls.
 *
 * A hook runs for every entity created in its scope.
 */
abstract class EntityHook extends RuntimeHook {

  /**
   * Initializes the hook.
   *
   * @param string $scope_name
   *   The name of the scope the hook is dispatched in.
   * @param array{class-string<\Behat\Behat\Context\Context>, string}|callable $callable
   *   The context method to call.
   * @param string|null $description
   *   A human readable description of the hook.
   */
  public function __construct(string $scope_name, array|callable $callable, ?string $description = NULL) {
    parent::__construct($scope_name, $this->resolveCallable($callable), $description);
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
