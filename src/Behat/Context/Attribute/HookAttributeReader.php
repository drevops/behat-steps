<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Context\Attribute;

use Behat\Behat\Context\Attribute\AttributeReader;
use DrevOps\BehatSteps\Behat\Hook\Attribute\AfterEntityCreate as AfterEntityCreateAttribute;
use DrevOps\BehatSteps\Behat\Hook\Attribute\AfterNodeCreate as AfterNodeCreateAttribute;
use DrevOps\BehatSteps\Behat\Hook\Attribute\AfterTermCreate as AfterTermCreateAttribute;
use DrevOps\BehatSteps\Behat\Hook\Attribute\AfterUserCreate as AfterUserCreateAttribute;
use DrevOps\BehatSteps\Behat\Hook\Attribute\BeforeEntityCreate as BeforeEntityCreateAttribute;
use DrevOps\BehatSteps\Behat\Hook\Attribute\BeforeNodeCreate as BeforeNodeCreateAttribute;
use DrevOps\BehatSteps\Behat\Hook\Attribute\BeforeTermCreate as BeforeTermCreateAttribute;
use DrevOps\BehatSteps\Behat\Hook\Attribute\BeforeUserCreate as BeforeUserCreateAttribute;
use DrevOps\BehatSteps\Behat\Hook\Attribute\DrupalHookInterface;
use DrevOps\BehatSteps\Behat\Hook\Call\AfterEntityCreate;
use DrevOps\BehatSteps\Behat\Hook\Call\AfterNodeCreate;
use DrevOps\BehatSteps\Behat\Hook\Call\AfterTermCreate;
use DrevOps\BehatSteps\Behat\Hook\Call\AfterUserCreate;
use DrevOps\BehatSteps\Behat\Hook\Call\BeforeEntityCreate;
use DrevOps\BehatSteps\Behat\Hook\Call\BeforeNodeCreate;
use DrevOps\BehatSteps\Behat\Hook\Call\BeforeTermCreate;
use DrevOps\BehatSteps\Behat\Hook\Call\BeforeUserCreate;

/**
 * Reads the entity creation hook attributes off a context method.
 */
class HookAttributeReader implements AttributeReader {

  /**
   * Behat's factory for a callable that survives late instance binding.
   *
   * Behat 4 types the callee constructor as 'callable', and a
   * '[class-string, method]' pair is not callable for an instance method, so
   * it wraps such methods instead. The class is absent on Behat 3, which
   * accepts the pair directly.
   */
  protected const CALLABLE_FACTORY = 'Behat\\Behat\\Context\\ContextMethodCallableFactory';

  /**
   * Map of attribute classes to their hook call classes.
   *
   * @var array<class-string, class-string<\DrevOps\BehatSteps\Behat\Hook\Call\EntityHook>>
   */
  protected const ATTRIBUTE_MAP = [
    AfterEntityCreateAttribute::class => AfterEntityCreate::class,
    AfterNodeCreateAttribute::class => AfterNodeCreate::class,
    AfterTermCreateAttribute::class => AfterTermCreate::class,
    AfterUserCreateAttribute::class => AfterUserCreate::class,
    BeforeEntityCreateAttribute::class => BeforeEntityCreate::class,
    BeforeNodeCreateAttribute::class => BeforeNodeCreate::class,
    BeforeTermCreateAttribute::class => BeforeTermCreate::class,
    BeforeUserCreateAttribute::class => BeforeUserCreate::class,
  ];

  /**
   * {@inheritdoc}
   *
   * @param class-string<\Behat\Behat\Context\Context> $contextClass
   *   The context class name.
   * @param \ReflectionMethod $method
   *   The reflected method.
   */
  public function readCallees(string $contextClass, \ReflectionMethod $method): array {
    $attributes = $method->getAttributes(DrupalHookInterface::class, \ReflectionAttribute::IS_INSTANCEOF);

    $callees = [];
    foreach ($attributes as $attribute) {
      $hook_call_class = self::ATTRIBUTE_MAP[$attribute->getName()] ?? NULL;
      if ($hook_call_class === NULL) {
        continue;
      }

      $hook = $attribute->newInstance();
      $callees[] = new $hook_call_class($hook->getFilterString(), $this->makeCallable($contextClass, $method));
    }

    return $callees;
  }

  /**
   * Builds the callable a hook call is constructed with.
   *
   * @param class-string<\Behat\Behat\Context\Context> $context_class
   *   The context class declaring the method.
   * @param \ReflectionMethod $method
   *   The reflected method carrying the attribute.
   *
   * @return array{class-string<\Behat\Behat\Context\Context>, string}|callable
   *   The pair Behat 3 accepts, or the wrapper Behat 4 requires.
   */
  protected function makeCallable(string $context_class, \ReflectionMethod $method): array|callable {
    if ($method->isStatic() || !class_exists(self::CALLABLE_FACTORY)) {
      return [$context_class, $method->getName()];
    }

    // @codeCoverageIgnoreStart
    /** @var callable $callable */
    // @phpstan-ignore argument.type
    $callable = call_user_func([self::CALLABLE_FACTORY, 'makeCallable'], $context_class, $method);

    return $callable;
    // @codeCoverageIgnoreEnd
  }

}
