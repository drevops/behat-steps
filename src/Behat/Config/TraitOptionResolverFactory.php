<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Config;

use DrevOps\BehatSteps\Behat\Manager\ScenarioTagRegistry;
use DrevOps\BehatSteps\Behat\Manager\ScenarioTagRegistryInterface;

/**
 * Builds the option resolver of a context out of the shared collaborators.
 *
 * A context constructed outside Behat gets its own registry, which holds no
 * tags, so option resolution works there without the container.
 */
class TraitOptionResolverFactory implements TraitOptionResolverFactoryInterface {

  /**
   * Reads the option declarations of a context class.
   */
  protected ConfigSchemaReader $reader;

  /**
   * Holds the tags the running scenario carries.
   */
  protected ScenarioTagRegistryInterface $scenarioTags;

  /**
   * Applies the tag layers of one option.
   */
  protected TagOverrides $tagOverrides;

  /**
   * Constructs a TraitOptionResolverFactory.
   *
   * @param \DrevOps\BehatSteps\Behat\Config\ConfigSchemaReader|null $reader
   *   Reads the option declarations of a context class.
   * @param \DrevOps\BehatSteps\Behat\Manager\ScenarioTagRegistryInterface|null $scenario_tags
   *   Holds the tags the running scenario carries.
   * @param \DrevOps\BehatSteps\Behat\Config\TagOverrides|null $tag_overrides
   *   Applies the tag layers of one option.
   */
  public function __construct(?ConfigSchemaReader $reader = NULL, ?ScenarioTagRegistryInterface $scenario_tags = NULL, ?TagOverrides $tag_overrides = NULL) {
    $this->reader = $reader ?? new ConfigSchemaReader();
    $this->scenarioTags = $scenario_tags ?? new ScenarioTagRegistry();
    $this->tagOverrides = $tag_overrides ?? new TagOverrides();
  }

  /**
   * {@inheritdoc}
   */
  public function create(string $context_class, array $config, array $steps): TraitOptionResolverInterface {
    return new TraitOptionResolver($context_class, $this->reader->read($context_class), $config, $steps, $this->scenarioTags, $this->tagOverrides);
  }

}
