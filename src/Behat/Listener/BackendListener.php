<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Listener;

use Behat\Behat\EventDispatcher\Event\BeforeScenarioTested;
use Behat\Behat\EventDispatcher\Event\ExampleTested;
use Behat\Behat\EventDispatcher\Event\ScenarioTested;
use Behat\Gherkin\Node\TaggedNodeInterface;
use DrevOps\BehatSteps\Behat\Registry\BackendRegistryInterface;
use DrevOps\BehatSteps\Behat\Registry\ScenarioTagRegistryInterface;
use DrevOps\BehatSteps\Behat\Tag;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Publishes the state each scenario or example resolves against.
 *
 * Behat dispatches this event before the first 'BeforeScenario' hook, so
 * everything set here is in place for the whole scenario.
 */
final readonly class BackendListener implements EventSubscriberInterface {

  /**
   * Prefix of the tag that promotes a backend for one scenario or feature.
   */
  public const string BACKEND_TAG_PREFIX = 'backend:';

  /**
   * Prefix of a tag that fails the scenario, naming '@backend:' instead.
   */
  protected const string REPLACED_TAG_PREFIX = 'driver:';

  /**
   * Constructs a BackendListener.
   *
   * @param \DrevOps\BehatSteps\Behat\Registry\BackendRegistryInterface $backendRegistry
   *   The backend registry.
   * @param \DrevOps\BehatSteps\Behat\Registry\ScenarioTagRegistryInterface $scenarioTagRegistry
   *   The registry option resolution reads the scenario's tags from.
   * @param array<array-key, string> $backends
   *   The configured backend list, as ordered pairs of tag name to registered
   *   backend name. A bare entry carries an integer key and names both.
   */
  public function __construct(
    protected BackendRegistryInterface $backendRegistry,
    protected ScenarioTagRegistryInterface $scenarioTagRegistry,
    protected array $backends = [],
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ScenarioTested::BEFORE => ['prepareScenarioBackends', 11],
      ExampleTested::BEFORE => ['prepareScenarioBackends', 11],
    ];
  }

  /**
   * Passes the registries the state for the scenario about to run.
   *
   * The configured list is both the allow-list and the precedence order. A
   * '@backend:NAME' tag moves NAME ahead of the backends no tag names; it never
   * adds a backend the configuration does not list.
   *
   * The backends the scenario's tags name come first, then those its feature's
   * tags name. Each group keeps the configured order, so the order the tags
   * are written in never changes the result.
   *
   * The scenario's tags are published here rather than read from a hook
   * scope. A tag that sets a trait option thus applies to a step as well as
   * to a hook.
   *
   * Both subscribed events carry a 'BeforeScenarioTested', an example's
   * scenario being the outline row itself.
   *
   * @throws \RuntimeException
   *   When a '@backend:' tag names a backend the configuration does not list,
   *   or the scenario or its feature carries a '@driver:' tag.
   */
  public function prepareScenarioBackends(BeforeScenarioTested $event): void {
    $this->scenarioTagRegistry->setTags(Tag::all($event));

    $configured = $this->configuredBackends();
    $scenario = $event->getScenario();
    $scenario_backends = $scenario instanceof TaggedNodeInterface ? $this->promotedBackends($scenario, $configured) : [];
    $feature_backends = $this->promotedBackends($event->getFeature(), $configured);

    $this->backendRegistry->setScenarioBackends($scenario_backends + $feature_backends + $configured);
    $this->backendRegistry->setEnvironment($event->getEnvironment());
  }

  /**
   * Normalizes the configured list into a tag name to backend name map.
   *
   * A bare entry names a backend whose tag name is the backend name. A keyed
   * entry gives the tag a name of its own, so the same feature file can run
   * against a different backend in another profile.
   *
   * @return array<string, string>
   *   Ordered map of tag name to registered backend name. A configuration that
   *   declares no list gets every registered backend, in registration order.
   */
  protected function configuredBackends(): array {
    if ($this->backends === []) {
      $names = array_keys($this->backendRegistry->getBackends());

      return array_combine($names, $names);
    }

    $backends = [];

    foreach ($this->backends as $tag => $name) {
      $backends[strtolower(is_int($tag) ? $name : $tag)] = $name;
    }

    return $backends;
  }

  /**
   * Selects the configured entries the '@backend:' tags of a node name.
   *
   * An example of a scenario outline counts as 1 node: Gherkin merges the
   * outline's tags and its 'Examples:' table's tags into the example's list.
   *
   * @param \Behat\Gherkin\Node\TaggedNodeInterface $node
   *   The scenario or the feature to read.
   * @param array<string, string> $configured
   *   The configured map of tag name to registered backend name.
   *
   * @return array<string, string>
   *   The named entries of the configured map, in the configured order.
   *
   * @throws \RuntimeException
   *   When a '@backend:' tag names a backend the configuration does not list,
   *   or the node carries a '@driver:' tag.
   */
  protected function promotedBackends(TaggedNodeInterface $node, array $configured): array {
    $names = [];

    foreach (Tag::on($node) as $tag) {
      if (str_starts_with($tag, self::REPLACED_TAG_PREFIX)) {
        throw new \RuntimeException(sprintf('The "@%s" tag moved to "@%s%s". Rename the tag; the name it carries is unchanged.', $tag, self::BACKEND_TAG_PREFIX, substr($tag, strlen(self::REPLACED_TAG_PREFIX))));
      }

      if (!str_starts_with($tag, self::BACKEND_TAG_PREFIX)) {
        continue;
      }

      $name = strtolower(substr($tag, strlen(self::BACKEND_TAG_PREFIX)));

      if (!isset($configured[$name])) {
        throw new \RuntimeException(sprintf('The "@%s%s" tag names a backend that the configured backend list does not hold. Configured backends: %s. The tag reorders that list; it never adds to it.', self::BACKEND_TAG_PREFIX, $name, implode(', ', array_keys($configured))));
      }

      $names[$name] = TRUE;
    }

    // 'array_intersect_key()' keeps the order of its first argument, so the
    // order the tags are written in is discarded.
    return array_intersect_key($configured, $names);
  }

}
