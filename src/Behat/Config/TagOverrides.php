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
final class TagOverrides {

  /**
   * Prefix of the tag that switches off every hook of a trait.
   */
  public const string SKIP_TAG_PREFIX = 'behat-steps-skip:';

  /**
   * Matches the value a skip tag carries: the short name of a trait.
   */
  public const string SKIP_TAG_VALUE_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*' . GroupName::TRAIT_SUFFIX . '$/';

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
    $is_switchable = $option->name === Option::ENABLED;

    if ($tags === [] || ($option->tags === [] && !$is_switchable)) {
      return $value;
    }

    foreach ($tags as $tag) {
      if ($is_switchable && $this->skipsGroup($tag, $group)) {
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
   * converted back into. A run of capitals reads as 1 word, so 'APIClient'
   * and 'ApiClient' both give 'api_client'.
   *
   * The tag is read forwards instead, and any spelling of the trait that
   * derives the group matches it.
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
