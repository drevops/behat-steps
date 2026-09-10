<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat;

use Behat\Behat\EventDispatcher\Event\BeforeScenarioTested;
use Behat\Behat\Hook\Scope\ScenarioScope;
use Behat\Gherkin\Node\TaggedNodeInterface;

/**
 * Reads scenario and feature tags in a form that does not vary by parser mode.
 *
 * Gherkin's 'legacy' mode strips the '@' from a tag and its 'gherkin-32' mode
 * keeps it, while 'TaggedNodeInterface::hasTag()' compares strictly. Every tag
 * this library reads goes through here, so a suite can pick either mode.
 *
 * @see \Behat\Gherkin\GherkinCompatibilityMode
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

    // An example's scenario is the outline row, which carries tags of its own
    // only on the outline. Behat 4 types the getter as returning a node that
    // need not be tagged.
    if ($scenario instanceof TaggedNodeInterface) {
      $tags = array_merge($tags, $scenario->getTags());
    }

    return self::normalize($tags);
  }

}
