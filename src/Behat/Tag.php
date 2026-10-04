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
 *
 * 'has()', 'values()' and 'valueStates()' take a subject. A scenario scope
 * or event reads the scenario together with its feature, and a node reads
 * that node alone.
 *
 * A parametrized tag reads '@<name>:<value>', and a '!' before the value
 * switches it off, as in '@module:!help'.
 */
final class Tag {

  /**
   * The tag that runs a scenario in the Mink JavaScript session.
   */
  public const string JAVASCRIPT = 'javascript';

  /**
   * Separates the name of a parametrized tag from its value.
   */
  public const string SEPARATOR = ':';

  /**
   * Switches off the value it precedes, as in '@module:!help'.
   */
  public const string NEGATION = '!';

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
   * Checks whether a subject carries a tag.
   *
   * @param \Behat\Gherkin\Node\TaggedNodeInterface|\Behat\Behat\Hook\Scope\ScenarioScope|\Behat\Behat\EventDispatcher\Event\BeforeScenarioTested $subject
   *   A scenario scope or event, to read the scenario and its feature, or a
   *   single node.
   * @param string $tag
   *   The tag name, without a leading '@'.
   *
   * @return bool
   *   TRUE when the subject carries the tag.
   */
  public static function has(TaggedNodeInterface|ScenarioScope|BeforeScenarioTested $subject, string $tag): bool {
    $tags = $subject instanceof TaggedNodeInterface ? self::on($subject) : self::all($subject);

    return in_array($tag, $tags, TRUE);
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

  /**
   * Collects the values of a parametrized tag.
   *
   * '@watchdog:php @watchdog:cron' gives 'php' and 'cron'. A value keeps any
   * separator of its own, and a tag with nothing after the separator names no
   * value.
   *
   * @param \Behat\Gherkin\Node\TaggedNodeInterface|\Behat\Behat\Hook\Scope\ScenarioScope|\Behat\Behat\EventDispatcher\Event\BeforeScenarioTested $subject
   *   A scenario scope or event, to read the scenario and its feature, or a
   *   single node.
   * @param string $name
   *   The tag name, without a leading '@' or the separator.
   *
   * @return array<int, string>
   *   One value per tag, feature tags first, each in the order written.
   */
  public static function values(TaggedNodeInterface|ScenarioScope|BeforeScenarioTested $subject, string $name): array {
    $tags = $subject instanceof TaggedNodeInterface ? self::on($subject) : self::all($subject);
    $prefix = $name . self::SEPARATOR;
    $values = [];

    foreach ($tags as $tag) {
      if (str_starts_with($tag, $prefix) && strlen($tag) > strlen($prefix)) {
        $values[] = substr($tag, strlen($prefix));
      }
    }

    return $values;
  }

  /**
   * Resolves each value of a parametrized tag into an on/off state.
   *
   * '@module:help' switches 'help' on and '@module:!help' switches it off. A
   * later tag for a value replaces an earlier one, so a scenario tag
   * overrides a feature tag.
   *
   * @param \Behat\Gherkin\Node\TaggedNodeInterface|\Behat\Behat\Hook\Scope\ScenarioScope|\Behat\Behat\EventDispatcher\Event\BeforeScenarioTested $subject
   *   A scenario scope or event, to read the scenario and its feature, or a
   *   single node.
   * @param string $name
   *   The tag name, without a leading '@' or the separator.
   *
   * @return array<string, bool>
   *   TRUE for a value switched on and FALSE for one switched off, keyed by
   *   value in the order each was first named.
   */
  public static function valueStates(TaggedNodeInterface|ScenarioScope|BeforeScenarioTested $subject, string $name): array {
    $states = [];

    foreach (self::values($subject, $name) as $value) {
      $is_on = !str_starts_with($value, self::NEGATION);
      $key = $is_on ? $value : substr($value, strlen(self::NEGATION));

      if ($key !== '') {
        $states[$key] = $is_on;
      }
    }

    return $states;
  }

}
