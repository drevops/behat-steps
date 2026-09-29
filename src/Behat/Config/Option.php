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
   * Constructs an Option.
   *
   * @param string $name
   *   The option name within its group, in snake case.
   * @param mixed $default
   *   The value the option holds when nothing configures it.
   * @param string $description
   *   What the option does, as the configuration reference renders it.
   * @param array<string, mixed> $tags
   *   Map of tag name to the value that tag sets, without a leading '@'.
   *
   * @throws \InvalidArgumentException
   *   When the name or the description is empty, or a tag is not named.
   */
  public function __construct(
    public string $name,
    public mixed $default,
    public string $description,
    public array $tags = [],
  ) {
    if (trim($name) === '') {
      throw new \InvalidArgumentException('An option declares a name.');
    }

    if (trim($description) === '') {
      throw new \InvalidArgumentException(sprintf('The "%s" option declares a description.', $name));
    }

    foreach (array_keys($tags) as $tag) {
      if (!is_string($tag) || trim($tag) === '') {
        throw new \InvalidArgumentException(sprintf('The "%s" option lists its tags as a map of tag name to the value it sets.', $name));
      }
    }
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
