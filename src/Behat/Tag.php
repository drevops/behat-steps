<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat;

use Behat\Behat\EventDispatcher\Event\BeforeScenarioTested;
use Behat\Behat\Hook\Scope\ScenarioScope;
use Behat\Gherkin\Node\TaggedNodeInterface;

/**
 * Reads scenario and feature tags in a form that does not vary by Behat major.
 *
 * Behat 3 strips the '@' from a tag by default and Behat 4 keeps it, while
 * 'TaggedNodeInterface::hasTag()' compares strictly. Every tag this library
 * reads goes through here, so a tag matches on both majors.
 */
final class Tag {

  /**
   * Strips the leading '@' from each tag.
   *
   * @param array<array-key, string> $tags
   *   Tags as the parser produced them.
   *
   * @return array<int, string>
   *   Tags without a leading '@'.
   */
  public static function normalize(array $tags): array {
    return array_values(array_map(
      static fn(string $tag): string => str_starts_with($tag, '@') ? substr($tag, 1) : $tag,
      $tags
    ));
  }

  /**
   * Collects the tags of a single node.
   *
   * @param \Behat\Gherkin\Node\TaggedNodeInterface $node
   *   The feature or scenario to read.
   *
   * @return array<int, string>
   *   The node's tags, each without a leading '@'.
   */
  public static function on(TaggedNodeInterface $node): array {
    return self::normalize($node->getTags());
  }

  /**
   * Checks whether a node carries a tag.
   *
   * @param \Behat\Gherkin\Node\TaggedNodeInterface $node
   *   The feature or scenario to read.
   * @param string $tag
   *   The tag name, without a leading '@'.
   *
   * @return bool
   *   TRUE when the node carries the tag.
   */
  public static function has(TaggedNodeInterface $node, string $tag): bool {
    return in_array($tag, self::on($node), TRUE);
  }

  /**
   * Collects the tags of a scenario and the feature that holds it.
   *
   * @param \Behat\Behat\Hook\Scope\ScenarioScope|\Behat\Behat\EventDispatcher\Event\BeforeScenarioTested $subject
   *   The scenario scope a hook received, or the event a listener received.
   *
   * @return array<int, string>
   *   Feature tags followed by scenario tags, each without a leading '@'.
   */
  public static function all(ScenarioScope|BeforeScenarioTested $subject): array {
    $scenario = $subject->getScenario();
    $tags = $subject->getFeature()->getTags();

    if ($scenario instanceof TaggedNodeInterface) {
      $tags = array_merge($tags, $scenario->getTags());
    }

    return self::normalize($tags);
  }

}
