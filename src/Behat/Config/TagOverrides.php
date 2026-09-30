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
    // An 'enabled' option is also switched off by the library's one skip tag,
    // named after the trait the group belongs to.
    $switchable = $option->name === Option::ENABLED;

    if ($tags === [] || ($option->tags === [] && !$switchable)) {
      return $value;
    }

    foreach ($tags as $tag) {
      if ($switchable && $this->skipsGroup($tag, $group)) {
        $value = FALSE;

        continue;
      }

      if (array_key_exists($tag, $option->tags)) {
        $value = $option->tags[$tag];
      }
    }

    return $value;
  }

  /**
   * Whether a skip tag names the trait that owns a group.
   *
   * The tag carries the trait's own name, which a group name cannot be
   * converted back into: a run of capitals reads as one word, so 'APIClient'
   * and 'ApiClient' both give 'api_client'. The tag is read forwards instead,
   * and any spelling of the trait that derives the group matches it.
   *
   * @param string $tag
   *   A tag the scenario or its feature carries, without a leading '@'.
   * @param string $group
   *   The group the option belongs to.
   */
  protected function skipsGroup(string $tag, string $group): bool {
    if (!str_starts_with($tag, self::SKIP_TAG_PREFIX)) {
      return FALSE;
    }

    $name = substr($tag, strlen(self::SKIP_TAG_PREFIX));

    return str_ends_with($name, GroupName::TRAIT_SUFFIX) && GroupName::fromTraitName($name) === $group;
  }

}
