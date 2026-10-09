<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Listener;

use Behat\Behat\Context\Environment\ContextEnvironment;
use Behat\Behat\EventDispatcher\Event\BeforeScenarioTested;
use Behat\Behat\EventDispatcher\Event\ExampleTested;
use Behat\Behat\EventDispatcher\Event\ScenarioTested;
use DrevOps\BehatSteps\Behat\Config\TagOverrides;
use DrevOps\BehatSteps\Behat\Tag;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Rejects a skip tag that does not name a trait the suite's contexts compose.
 *
 * A hook reads the skip tag by the name of its trait alone, so a tag carrying
 * any other value switches nothing off. Behat dispatches this event before
 * the first 'BeforeScenario' hook, so the run fails before any hook acts.
 */
final class SkipTagListener implements EventSubscriberInterface {

  /**
   * Short names of the composed traits, keyed by the list of context classes.
   *
   * A suite's contexts cannot change within a run, so each list is walked once
   * rather than for every scenario.
   *
   * @var array<string, array<string, true>>
   */
  protected array $composedTraits = [];

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
   *   When a skip tag carries no value, a value that is not a trait name, or
   *   the name of a trait no context of the suite composes.
   */
  public function validateSkipTags(BeforeScenarioTested $event): void {
    $bare = rtrim(TagOverrides::SKIP_TAG_PREFIX, ':');

    foreach (Tag::all($event) as $tag) {
      if ($tag !== $bare && !str_starts_with($tag, TagOverrides::SKIP_TAG_PREFIX)) {
        continue;
      }

      $trait = substr($tag, strlen(TagOverrides::SKIP_TAG_PREFIX));

      if (preg_match(TagOverrides::SKIP_TAG_VALUE_PATTERN, $trait) !== 1) {
        throw new \RuntimeException(sprintf('The "@%s" tag does not name a trait. A skip tag takes the name of the trait whose hooks it switches off, as in "@%sJavascriptTrait".', $tag, TagOverrides::SKIP_TAG_PREFIX));
      }

      $environment = $event->getEnvironment();

      if (!$environment instanceof ContextEnvironment || isset($this->collectComposedTraits($environment)[$trait])) {
        continue;
      }

      throw new \RuntimeException(sprintf('The "@%s" tag names no trait a context of the "%s" suite composes, so it would switch nothing off. Check the trait name for a typo, or remove the tag.', $tag, $environment->getSuite()->getName()));
    }
  }

  /**
   * Collects the short names of every trait the suite's contexts compose.
   *
   * A trait composed by a parent class or by another trait counts as well.
   *
   * @param \Behat\Behat\Context\Environment\ContextEnvironment $environment
   *   The environment holding the suite's context classes.
   *
   * @return array<string, true>
   *   The short trait names, as keys.
   */
  protected function collectComposedTraits(ContextEnvironment $environment): array {
    $classes = $environment->getContextClasses();
    $key = implode(' ', $classes);

    if (isset($this->composedTraits[$key])) {
      return $this->composedTraits[$key];
    }

    $pending = [];

    foreach ($classes as $class) {
      $pending = [...$pending, $class, ...array_values(class_parents($class) ?: [])];
    }

    $traits = [];

    while ($pending !== []) {
      foreach (class_uses(array_pop($pending)) ?: [] as $trait) {
        if (!isset($traits[$trait])) {
          $traits[$trait] = TRUE;
          $pending[] = $trait;
        }
      }
    }

    $names = [];

    foreach (array_keys($traits) as $trait) {
      $separator = strrpos($trait, '\\');
      $names[$separator === FALSE ? $trait : substr($trait, $separator + 1)] = TRUE;
    }

    $this->composedTraits[$key] = $names;

    return $names;
  }

}
