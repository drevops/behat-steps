<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Config;

/**
 * Converts between an option group name and the names it derives from.
 *
 * A group is named after the trait that declares it, in snake case. That
 * trait declares its options in a method carrying the same name in camel
 * case.
 *
 * No conversion runs back to the trait name. A run of capitals reads as 1
 * word, so 'APIClientTrait' and 'ApiClientTrait' both give 'api_client', and
 * the group alone does not identify the spelling.
 */
final class GroupName {

  /**
   * Suffix every step trait's name carries.
   */
  public const string TRAIT_SUFFIX = 'Trait';

  /**
   * Converts a camel case method prefix to its group name.
   *
   * A run of capitals is 1 word, so the group a trait name derives to is the
   * group its method prefix derives to. 'APIClient' and 'apiClient' both give
   * 'api_client'.
   *
   * @param string $prefix
   *   The prefix the declaring method carries, such as 'bigPipe'.
   *
   * @return string
   *   The group name, such as 'big_pipe'.
   */
  public static function fromMethodPrefix(string $prefix): string {
    return strtolower((string) preg_replace('/(?<=[a-z0-9])[A-Z]|(?<!^)[A-Z](?=[a-z])/', '_$0', $prefix));
  }

  /**
   * Converts a group name to the camel case prefix its methods carry.
   *
   * @param string $group
   *   The group name, such as 'big_pipe'.
   *
   * @return string
   *   The method prefix, such as 'bigPipe'.
   */
  public static function toMethodPrefix(string $group): string {
    return lcfirst(str_replace('_', '', ucwords($group, '_')));
  }

  /**
   * Converts a trait name to the group its options are configured under.
   *
   * @param string $trait_name
   *   The trait name, fully qualified or short, such as 'BigPipeTrait'.
   *
   * @return string
   *   The group name, such as 'big_pipe'.
   */
  public static function fromTraitName(string $trait_name): string {
    $separator = strrpos($trait_name, '\\');

    if ($separator !== FALSE) {
      $trait_name = substr($trait_name, $separator + 1);
    }

    if (str_ends_with($trait_name, self::TRAIT_SUFFIX)) {
      $trait_name = substr($trait_name, 0, -strlen(self::TRAIT_SUFFIX));
    }

    return self::fromMethodPrefix($trait_name);
  }

}
