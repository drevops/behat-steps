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
  protected ConfigSchemaReader $configSchemaReader;

  /**
   * Holds the tags the running scenario carries.
   */
  protected ScenarioTagRegistryInterface $scenarioTagRegistry;

  /**
   * Applies the tag layers of one option.
   */
  protected TagOverrides $tagOverrides;

  /**
   * Constructs a TraitOptionResolverFactory.
   *
   * @param \DrevOps\BehatSteps\Behat\Config\ConfigSchemaReader|null $config_schema_reader
   *   Reads the option declarations of a context class.
   * @param \DrevOps\BehatSteps\Behat\Manager\ScenarioTagRegistryInterface|null $scenario_tag_registry
   *   Holds the tags the running scenario carries.
   * @param \DrevOps\BehatSteps\Behat\Config\TagOverrides|null $tag_overrides
   *   Applies the tag layers of one option.
   */
  public function __construct(?ConfigSchemaReader $config_schema_reader = NULL, ?ScenarioTagRegistryInterface $scenario_tag_registry = NULL, ?TagOverrides $tag_overrides = NULL) {
    $this->configSchemaReader = $config_schema_reader ?? new ConfigSchemaReader();
    $this->scenarioTagRegistry = $scenario_tag_registry ?? new ScenarioTagRegistry();
    $this->tagOverrides = $tag_overrides ?? new TagOverrides();
  }

  /**
   * {@inheritdoc}
   */
  public function create(string $context_class, array $config, array $steps): TraitOptionResolverInterface {
    return new TraitOptionResolver($context_class, $this->configSchemaReader->read($context_class), $config, $steps, $this->scenarioTagRegistry, $this->tagOverrides);
  }

}
