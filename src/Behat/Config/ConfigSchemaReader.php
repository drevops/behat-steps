<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Config;

/**
 * Collects the option declarations of every trait a context composes.
 *
 * A trait declares its options in a '<prefix>ConfigSchema()' method, named by
 * the prefix its other methods carry. The group name derives from the method
 * name, so a consuming project's own trait participates without being
 * registered anywhere.
 *
 * This is the only piece of the option machinery that reflects.
 */
class ConfigSchemaReader {

  /**
   * Suffix of the method a trait declares its options in.
   */
  public const string METHOD_SUFFIX = 'ConfigSchema';

  /**
   * Declared options, keyed by context class name.
   *
   * Reflection over every method of a context composing 40 traits is too
   * expensive to repeat per option read. A class's declarations cannot
   * change within a run.
   *
   * @var array<string, array<string, array<string, \DrevOps\BehatSteps\Behat\Config\Option>>>
   */
  protected static array $cache = [];

  /**
   * Names the method a trait declares its options in.
   *
   * @param string $trait
   *   The trait name, fully qualified or short.
   *
   * @return string
   *   The method name, such as 'bigPipeConfigSchema'.
   */
  public static function methodFor(string $trait): string {
    return GroupName::toMethodPrefix(GroupName::fromTraitName($trait)) . self::METHOD_SUFFIX;
  }

  /**
   * Reads the option declarations a context class composes.
   *
   * @param string $context_class
   *   The context class to read.
   *
   * @return array<string, array<string, \DrevOps\BehatSteps\Behat\Config\Option>>
   *   Options keyed by group name and then by option name, groups sorted.
   *
   * @throws \RuntimeException
   *   When a declaring method returns anything other than a list of options.
   */
  public function read(string $context_class): array {
    if (isset(self::$cache[$context_class])) {
      return self::$cache[$context_class];
    }

    /** @var class-string $context_class */
    $reflection = new \ReflectionClass($context_class);
    $instance = $reflection->newInstanceWithoutConstructor();
    $schema = [];

    foreach ($reflection->getMethods() as $method) {
      $group = $this->groupOf($method);

      if ($group === NULL) {
        continue;
      }

      $schema[$group] = $this->optionsOf($method, $instance, $group);
    }

    ksort($schema);
    self::$cache[$context_class] = $schema;

    return $schema;
  }

  /**
   * Names the group a method declares, or NULL when it declares none.
   */
  protected function groupOf(\ReflectionMethod $method): ?string {
    if ($method->getNumberOfParameters() > 0) {
      return NULL;
    }

    $pattern = '/^(.+)' . preg_quote(self::METHOD_SUFFIX, '/') . '$/';

    if (preg_match($pattern, $method->getName(), $matches) !== 1) {
      return NULL;
    }

    return GroupName::fromMethodPrefix($matches[1]);
  }

  /**
   * Invokes a declaring method and keys what it returned by option name.
   *
   * @param \ReflectionMethod $method
   *   The declaring method.
   * @param object $instance
   *   An instance of the context, built without running its constructor.
   * @param string $group
   *   The group the method declares.
   *
   * @return array<string, \DrevOps\BehatSteps\Behat\Config\Option>
   *   Options keyed by name.
   *
   * @throws \RuntimeException
   *   When the method returns anything other than a list of options, or an
   *   option it lists rejects its own declaration.
   */
  protected function optionsOf(\ReflectionMethod $method, object $instance, string $group): array {
    $where = sprintf('%s::%s()', $method->getDeclaringClass()->getName(), $method->getName());

    try {
      $declarations = $method->invoke($instance);
    }
    catch (\RuntimeException $exception) {
      throw new \RuntimeException(sprintf('%s declares a malformed option: %s', $where, $exception->getMessage()), 0, $exception);
    }

    if (!is_array($declarations)) {
      throw new \RuntimeException(sprintf('%s must return a list of %s objects.', $where, Option::class));
    }

    $options = [];

    foreach ($declarations as $declaration) {
      if (!$declaration instanceof Option) {
        throw new \RuntimeException(sprintf('%s must return a list of %s objects, but it lists a %s.', $where, Option::class, get_debug_type($declaration)));
      }

      if (isset($options[$declaration->name])) {
        throw new \RuntimeException(sprintf('%s declares the "%s.%s" option twice.', $where, $group, $declaration->name));
      }

      $options[$declaration->name] = $declaration;
    }

    return $options;
  }

}
