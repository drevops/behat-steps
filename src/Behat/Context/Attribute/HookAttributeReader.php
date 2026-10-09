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
final class HookAttributeReader implements AttributeReader {

  /**
   * Map of attribute classes to their hook call classes.
   *
   * @var array<class-string, class-string<\DrevOps\BehatSteps\Behat\Hook\Call\EntityHook>>
   */
  protected const array ATTRIBUTE_MAP = [
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
   *
   * @throws \RuntimeException
   *   When an entity creation hook attribute is declared with an argument.
   */
  public function readCallees(string $contextClass, \ReflectionMethod $method): array {
    $attributes = $method->getAttributes(DrupalHookInterface::class, \ReflectionAttribute::IS_INSTANCEOF);

    $callees = [];
    foreach ($attributes as $attribute) {
      $hook_call_class = self::ATTRIBUTE_MAP[$attribute->getName()] ?? NULL;
      if ($hook_call_class === NULL) {
        continue;
      }

      $hook = new $hook_call_class([$contextClass, $method->getName()]);

      if ($attribute->getArguments() !== []) {
        throw new \RuntimeException(sprintf('The "#[%s]" attribute on "%s::%s()" takes no argument. The hook runs for every entity created in its scope, so read the entity from "$scope->getStub()" and return early for one it does not handle.', $hook->getName(), $contextClass, $method->getName()));
      }

      $callees[] = $hook;
    }

    return $callees;
  }

}
