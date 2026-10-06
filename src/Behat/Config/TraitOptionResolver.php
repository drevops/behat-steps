<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Config;

use DrevOps\BehatSteps\Behat\Registry\ScenarioTagRegistryInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * Resolves the options a context's traits declare.
 *
 * A value is taken from the first of these that sets it: the scenario's tags,
 * the feature's tags, the context's 'config' argument, the extension's 'steps'
 * section, the declaration's own default.
 *
 * The configuration layers settle when this object is built. The tag layers
 * are read per option, because the tags belong to whichever scenario is
 * running.
 *
 * It holds no Behat class, reflects over nothing and references no context
 * beyond the class name it names in a failure message.
 */
class TraitOptionResolver implements TraitOptionResolverInterface {

  /**
   * Values resolved from the configuration layers, keyed by group and name.
   *
   * @var array<string, array<string, mixed>>
   */
  protected array $resolved;

  /**
   * Constructs a TraitOptionResolver.
   *
   * @param string $contextClass
   *   The context whose traits declared the options, for failure messages.
   * @param array<string, array<string, \DrevOps\BehatSteps\Behat\Config\Option>> $declarations
   *   Declared options, keyed by group name and then by option name.
   * @param array<array-key, mixed> $config
   *   The context's 'config' argument, read strictly.
   * @param array<array-key, mixed> $steps
   *   The extension's 'steps' section, read permissively.
   * @param \DrevOps\BehatSteps\Behat\Registry\ScenarioTagRegistryInterface $scenarioTagRegistry
   *   The tags the running scenario carries.
   * @param \DrevOps\BehatSteps\Behat\Config\TagOverrides $tagOverrides
   *   Applies the tag layers of one option.
   *
   * @throws \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
   *   When the 'config' argument names a group or an option no trait declares,
   *   or gives a value that does not match the type its declaration defaults
   *   to.
   */
  public function __construct(
    protected readonly string $contextClass,
    protected readonly array $declarations,
    array $config,
    array $steps,
    protected readonly ScenarioTagRegistryInterface $scenarioTagRegistry,
    protected readonly TagOverrides $tagOverrides,
  ) {
    $resolved = $this->defaults();
    $resolved = $this->merge($resolved, $steps, FALSE);

    $this->resolved = $this->merge($resolved, $config, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function has(string $group, string $key): bool {
    return isset($this->declarations[$group][$key]);
  }

  /**
   * {@inheritdoc}
   */
  public function raw(string $group, string $key): mixed {
    if (!$this->has($group, $key)) {
      throw new \RuntimeException(sprintf('No trait in %s declares the option "%s.%s". Declared options: %s.', $this->contextClass, $group, $key, $this->optionList()));
    }

    return $this->tagOverrides->apply($group, $this->declarations[$group][$key], $this->resolved[$group][$key], $this->scenarioTagRegistry->getTags());
  }

  /**
   * {@inheritdoc}
   */
  public function bool(string $group, string $key): bool {
    $value = $this->raw($group, $key);

    return is_bool($value) ? $value : throw $this->mistyped($group, $key, Option::TYPE_NAMES['bool'], $value);
  }

  /**
   * {@inheritdoc}
   */
  public function int(string $group, string $key): int {
    $value = $this->raw($group, $key);

    return is_int($value) ? $value : throw $this->mistyped($group, $key, Option::TYPE_NAMES['int'], $value);
  }

  /**
   * {@inheritdoc}
   */
  public function float(string $group, string $key): float {
    $value = $this->raw($group, $key);

    return is_float($value) ? $value : throw $this->mistyped($group, $key, Option::TYPE_NAMES['float'], $value);
  }

  /**
   * {@inheritdoc}
   */
  public function string(string $group, string $key): string {
    $value = $this->raw($group, $key);

    return is_string($value) ? $value : throw $this->mistyped($group, $key, Option::TYPE_NAMES['string'], $value);
  }

  /**
   * {@inheritdoc}
   */
  public function array(string $group, string $key): array {
    $value = $this->raw($group, $key);

    return is_array($value) ? $value : throw $this->mistyped($group, $key, Option::TYPE_NAMES['array'], $value);
  }

  /**
   * {@inheritdoc}
   */
  public function groupFor(string $trait): ?string {
    if (!str_ends_with($trait, GroupName::TRAIT_SUFFIX)) {
      return NULL;
    }

    $group = GroupName::fromTraitName($trait);

    return isset($this->declarations[$group][Option::ENABLED]) ? $group : NULL;
  }

  /**
   * Returns every declared option at its default value.
   *
   * @return array<string, array<string, mixed>>
   *   Default values keyed by group name and then by option name.
   */
  protected function defaults(): array {
    $defaults = [];

    foreach ($this->declarations as $group => $options) {
      foreach ($options as $key => $option) {
        $defaults[$group][$key] = $option->default;
      }
    }

    return $defaults;
  }

  /**
   * Layers one set of overrides over the values resolved so far.
   *
   * @param array<string, array<string, mixed>> $resolved
   *   The values resolved so far.
   * @param array<array-key, mixed> $overrides
   *   The overrides to apply.
   * @param bool $is_strict
   *   Reject a group or an option no trait declares, rather than skipping it.
   *
   * @return array<string, array<string, mixed>>
   *   The values with the overrides applied.
   *
   * @throws \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
   *   When a group or an option is undeclared under a strict merge, a group
   *   does not hold a map of options, or a value does not match the type its
   *   declaration defaults to.
   */
  protected function merge(array $resolved, array $overrides, bool $is_strict): array {
    foreach ($overrides as $group => $options) {
      $group = (string) $group;

      if (!isset($this->declarations[$group])) {
        if ($is_strict) {
          throw new InvalidConfigurationException(sprintf('Unknown option group "%s" for context "%s". This context accepts: %s.', $group, $this->contextClass, implode(', ', array_keys($this->declarations)) ?: 'nothing'));
        }

        continue;
      }

      if (!is_array($options)) {
        throw new InvalidConfigurationException(sprintf('The "%s" option group holds a map of options, but a %s was given.', $group, get_debug_type($options)));
      }

      foreach ($options as $key => $value) {
        $key = (string) $key;

        if (!isset($this->declarations[$group][$key])) {
          if ($is_strict) {
            throw new InvalidConfigurationException(sprintf('Unknown option "%s.%s" for context "%s". The "%s" group accepts: %s.', $group, $key, $this->contextClass, $group, implode(', ', array_keys($this->declarations[$group]))));
          }

          continue;
        }

        $resolved[$group][$key] = $this->declarations[$group][$key]->cast($value, $group . '.' . $key);
      }
    }

    return $resolved;
  }

  /**
   * Lists every declared option as a dotted path.
   */
  protected function optionList(): string {
    $paths = [];

    foreach ($this->declarations as $group => $options) {
      foreach (array_keys($options) as $key) {
        $paths[] = $group . '.' . $key;
      }
    }

    return implode(', ', $paths) ?: 'none';
  }

  /**
   * Builds the failure of a read that named a type the option does not hold.
   *
   * @param string $group
   *   The group the option belongs to.
   * @param string $key
   *   The option name within the group.
   * @param string $expected
   *   The type the read named, with its article.
   * @param mixed $value
   *   The value the option resolved to.
   */
  protected function mistyped(string $group, string $key, string $expected, mixed $value): \RuntimeException {
    return new \RuntimeException(sprintf('The "%s.%s" option resolved to %s, but it was read as %s.', $group, $key, get_debug_type($value), $expected));
  }

}
