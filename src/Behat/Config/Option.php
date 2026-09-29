<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Config;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * One option a step trait declares.
 *
 * The declared default carries the option's type: a configured value is read
 * as that type, and a value that cannot be is an error naming both. A default
 * of NULL names no type, so anything configured against it passes through.
 *
 * @see \DrevOps\BehatSteps\Behat\Config\ConfigSchemaReader
 */
final readonly class Option {

  /**
   * Name of the option that switches its trait on and off.
   */
  public const string ENABLED = 'enabled';

  /**
   * How each type reads in a failure message.
   */
  public const array TYPE_NAMES = [
    'bool' => 'a boolean',
    'int' => 'an integer',
    'float' => 'a float',
    'string' => 'a string',
    'array' => 'a map',
    'null' => 'null',
  ];

  /**
   * Map of tag name to the value that tag sets, read as the declared type.
   *
   * @var array<string, mixed>
   */
  public array $tags;

  /**
   * Constructs an Option.
   *
   * @param string $name
   *   The option name within its group, in snake case.
   * @param mixed $default
   *   The value the option holds when nothing configures it.
   * @param string $description
   *   What the option does, as the configuration reference renders it.
   * @param array<array-key, mixed> $tags
   *   Map of tag name to the value that tag sets, without a leading '@'.
   *
   * @throws \RuntimeException
   *   When the name or the description is empty, a tag is not named, or a tag
   *   sets a value that does not match the type the default carries.
   */
  public function __construct(
    public string $name,
    public mixed $default,
    public string $description,
    array $tags = [],
  ) {
    if (trim($name) === '') {
      throw new \RuntimeException('An option declares a name.');
    }

    if (trim($description) === '') {
      throw new \RuntimeException(sprintf('The "%s" option declares a description.', $name));
    }

    // Every hook of the trait is switched on this one option, and the skip tag
    // binds it to FALSE, so a declaration naming another type would fail at the
    // hook that read it rather than here.
    if ($name === self::ENABLED && !is_bool($default)) {
      throw new \RuntimeException(sprintf('The "%s" option switches its trait on and off, so it defaults to a boolean.', $name));
    }

    $bindings = [];

    foreach ($tags as $tag => $value) {
      if (!is_string($tag) || trim($tag) === '') {
        throw new \RuntimeException(sprintf('The "%s" option lists its tags as a map of tag name to the value it sets.', $name));
      }

      // Read here rather than where a tag matches, so a declaration a scenario
      // has yet to reach still fails while the context is built.
      try {
        $bindings[$tag] = $this->cast($value, $name);
      }
      catch (InvalidConfigurationException $exception) {
        throw new \RuntimeException(sprintf('The "%s" tag of the "%s" option sets a value of the wrong type. %s', $tag, $name, $exception->getMessage()), 0, $exception);
      }
    }

    $this->tags = $bindings;
  }

  /**
   * Reads a configured value as the type this option's default carries.
   *
   * @param mixed $value
   *   The configured value.
   * @param string $path
   *   The dotted path of the option, for the failure message.
   *
   * @return mixed
   *   The value, cast where a numeric form is unambiguous.
   *
   * @throws \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
   *   When the value cannot be read as the declared type.
   */
  public function cast(mixed $value, string $path): mixed {
    $expected = get_debug_type($this->default);

    if ($expected === 'bool' && is_bool($value)) {
      return $value;
    }

    if ($expected === 'int' && (is_int($value) || (is_string($value) && preg_match('/^-?\d+$/', $value) === 1))) {
      return (int) $value;
    }

    if ($expected === 'float' && (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value)))) {
      return (float) $value;
    }

    if ($expected === 'string' && (is_string($value) || is_int($value) || is_float($value))) {
      return (string) $value;
    }

    if ($expected === 'array' && is_array($value)) {
      return $value;
    }

    if (!in_array($expected, ['bool', 'int', 'float', 'string', 'array'], TRUE)) {
      return $value;
    }

    $given = get_debug_type($value);

    throw new InvalidConfigurationException(sprintf('The "%s" option expects %s, but %s was given.', $path, self::TYPE_NAMES[$expected], self::TYPE_NAMES[$given] ?? 'a ' . $given));
  }

}
