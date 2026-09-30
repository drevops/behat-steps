<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Listener;

use Behat\Behat\EventDispatcher\Event\BeforeScenarioTested;
use Behat\Behat\EventDispatcher\Event\ExampleTested;
use Behat\Behat\EventDispatcher\Event\ScenarioTested;
use DrevOps\BehatSteps\Behat\Config\TagOverrides;
use DrevOps\BehatSteps\Behat\Tag;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Rejects a skip tag that does not name a trait.
 *
 * A hook reads the skip tag by the name of its trait alone, so a tag carrying
 * any other value switches nothing off. Behat dispatches this event before
 * the first 'BeforeScenario' hook, so the run fails before any hook acts.
 */
class SkipTagListener implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ScenarioTested::BEFORE => ['validateSkipTags', 12],
      ExampleTested::BEFORE => ['validateSkipTags', 12],
    ];
  }

  /**
   * Checks every skip tag on the scenario and its feature.
   *
   * Both subscribed events carry a 'BeforeScenarioTested', an example's
   * scenario being the outline row itself.
   *
   * @throws \RuntimeException
   *   When a skip tag carries no value, or a value that is not a trait name.
   */
  public function validateSkipTags(BeforeScenarioTested $event): void {
    $bare = rtrim(TagOverrides::SKIP_TAG_PREFIX, ':');

    foreach (Tag::all($event) as $tag) {
      if ($tag !== $bare && !str_starts_with($tag, TagOverrides::SKIP_TAG_PREFIX)) {
        continue;
      }

      if (preg_match(TagOverrides::SKIP_TAG_VALUE_PATTERN, substr($tag, strlen(TagOverrides::SKIP_TAG_PREFIX))) === 1) {
        continue;
      }

      throw new \RuntimeException(sprintf('The "@%s" tag does not name a trait. A skip tag takes the name of the trait whose hooks it switches off, as in "@%sJavascriptTrait".', $tag, TagOverrides::SKIP_TAG_PREFIX));
    }
  }

}
