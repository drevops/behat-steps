<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Config;

/**
 * Applies the tag layers of one option.
 *
 * A declaration names the tags that set it and the value each one sets. The
 * tags arrive in the order the parser produced them, feature tags before
 * scenario tags, so the tag on the narrower node settles the value.
 */
class TagOverrides {

  /**
   * Prefix of the tag that turns a hook or a whole trait off.
   */
  public const SKIP_TAG_PREFIX = 'behat-steps-skip:';

  /**
   * Name of the option the skip tag switches off.
   */
  public const ENABLED_OPTION = 'enabled';

  /**
   * Replaces a resolved value with whatever the last matching tag sets.
   *
   * @param string $group
   *   The group the option belongs to.
   * @param \DrevOps\BehatSteps\Behat\Config\Option $option
   *   The option being read.
   * @param mixed $value
   *   The value resolved from the configuration.
   * @param array<int, string> $tags
   *   The tags the scenario and its feature carry, each without a leading '@'.
   *
   * @return mixed
   *   The value, replaced by whatever the last matching tag sets.
   */
  public function apply(string $group, Option $option, mixed $value, array $tags): mixed {
    if ($tags === []) {
      return $value;
    }

    $bindings = $this->bindings($group, $option);

    if ($bindings === []) {
      return $value;
    }

    foreach ($tags as $tag) {
      if (array_key_exists($tag, $bindings)) {
        $value = $bindings[$tag];
      }
    }

    return $value;
  }

  /**
   * Collects the tags that set an option and the value each one sets.
   *
   * @param string $group
   *   The group the option belongs to.
   * @param \DrevOps\BehatSteps\Behat\Config\Option $option
   *   The option being read.
   *
   * @return array<string, mixed>
   *   Map of tag name to the value it sets.
   */
  protected function bindings(string $group, Option $option): array {
    $bindings = $option->tags;

    // An 'enabled' option is also switched off by the library's one skip tag,
    // named after the trait the group belongs to.
    if ($option->name === self::ENABLED_OPTION) {
      $bindings[self::SKIP_TAG_PREFIX . GroupName::toTraitName($group)] = FALSE;
    }

    return $bindings;
  }

}
