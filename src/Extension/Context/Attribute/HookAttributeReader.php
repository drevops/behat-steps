<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Extension\Context\Attribute;

use Behat\Behat\Context\Attribute\AttributeReader;
use DrevOps\BehatSteps\Extension\Hook\Attribute\HookAttributeInterface;
use DrevOps\BehatSteps\Extension\Hook\Call\EntityHook;

/**
 * Reads the entity creation hook attributes off a context method.
 */
final class HookAttributeReader implements AttributeReader {

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
    $attributes = $method->getAttributes(HookAttributeInterface::class, \ReflectionAttribute::IS_INSTANCEOF);

    $callees = [];

    foreach ($attributes as $attribute) {
      if ($attribute->getArguments() !== []) {
        $name = substr((string) strrchr($attribute->getName(), '\\'), 1);

        throw new \RuntimeException(sprintf('The "#[%s]" attribute on "%s::%s()" takes no argument. The hook runs for every entity created in its scope, so read the entity from "$scope->getStub()" and return early for one it does not handle.', $name, $contextClass, $method->getName()));
      }

      $hook_call_class = $attribute->newInstance()->getCallClass();

      if (!is_subclass_of($hook_call_class, EntityHook::class)) {
        continue;
      }

      $callees[] = new $hook_call_class([$contextClass, $method->getName()]);
    }

    return $callees;
  }

}
