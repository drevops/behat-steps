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
   * Resolves the group of a trait that declares an 'enabled' option.
   *
   * @param string $trait
   *   The short trait name a skip tag carries, such as 'BigPipeTrait'.
   *
   * @return string|null
   *   The group name, or NULL when the trait declares no 'enabled' option.
   */
  public function groupFor(string $trait): ?string;

}
