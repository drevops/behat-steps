<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Config;

/**
 * Interface for classes that resolve the options a context's traits declare.
 *
 * A read names the type it expects, so a caller neither casts nor guards what
 * it gets back.
 *
 * @see \DrevOps\BehatSteps\Behat\Config\TraitOptionResolverFactoryInterface
 */
interface TraitOptionResolverInterface {

  /**
   * Whether any trait of the context declares an option.
   *
   * @param string $group
   *   The trait group the option belongs to, such as 'javascript'.
   * @param string $key
   *   The option name within the group, such as 'fail_on_errors'.
   */
  public function has(string $group, string $key): bool;

  /**
   * Returns an option at whatever type it resolved to.
   *
   * Reserved for an option whose declaration defaults to NULL and so names no
   * type. Every other read names its type.
   *
   * @param string $group
   *   The trait group the option belongs to.
   * @param string $key
   *   The option name within the group.
   *
   * @return mixed
   *   The resolved value.
   *
   * @throws \RuntimeException
   *   When no trait of the context declares the option.
   */
  public function raw(string $group, string $key): mixed;

  /**
   * Returns an option declared as a boolean.
   *
   * @param string $group
   *   The trait group the option belongs to.
   * @param string $key
   *   The option name within the group.
   *
   * @throws \RuntimeException
   *   When no trait declares the option, or it resolved to another type.
   */
  public function bool(string $group, string $key): bool;

  /**
   * Returns an option declared as an integer.
   *
   * @param string $group
   *   The trait group the option belongs to.
   * @param string $key
   *   The option name within the group.
   *
   * @throws \RuntimeException
   *   When no trait declares the option, or it resolved to another type.
   */
  public function int(string $group, string $key): int;

  /**
   * Returns an option declared as a float.
   *
   * @param string $group
   *   The trait group the option belongs to.
   * @param string $key
   *   The option name within the group.
   *
   * @throws \RuntimeException
   *   When no trait declares the option, or it resolved to another type.
   */
  public function float(string $group, string $key): float;

  /**
   * Returns an option declared as a string.
   *
   * @param string $group
   *   The trait group the option belongs to.
   * @param string $key
   *   The option name within the group.
   *
   * @throws \RuntimeException
   *   When no trait declares the option, or it resolved to another type.
   */
  public function string(string $group, string $key): string;

  /**
   * Returns an option declared as a map.
   *
   * @param string $group
   *   The trait group the option belongs to.
   * @param string $key
   *   The option name within the group.
   *
   * @return array<array-key, mixed>
   *   The resolved value.
   *
   * @throws \RuntimeException
   *   When no trait declares the option, or it resolved to another type.
   */
  public function array(string $group, string $key): array;

  /**
   * Resolves the group a skip name belongs to.
   *
   * A name is either a trait name, which maps to its group directly, or a hook
   * method name, which carries its trait's prefix. The longest matching prefix
   * wins, so 'configOverrideBeforeStep' resolves to 'config_override' rather
   * than to 'config'.
   *
   * @param string $name
   *   The hook method name or trait name a skip tag would carry.
   *
   * @return string|null
   *   The group name, or NULL when no group with an 'enabled' option matches.
   */
  public function groupFor(string $name): ?string;

}
