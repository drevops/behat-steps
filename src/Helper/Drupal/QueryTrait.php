<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Helper\Drupal;

use DrevOps\BehatSteps\Driver\Capability\CoreCapabilityInterface;

/**
 * Reads Drupal state a step asserts on without going through a driver.
 *
 * Every member runs in the site's own process, so each resolves
 * 'CoreCapabilityInterface' first.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
 */
trait QueryTrait {

  /**
   * Load the ids of the entities of a type matching the conditions.
   *
   * @param string $entity_type
   *   The entity type id.
   * @param array<string, mixed> $conditions
   *   Conditions keyed by field names.
   * @param string|null $bundle
   *   Bundle to restrict the query to, or NULL for every bundle. The key the
   *   bundle is matched on comes from the entity type definition, so a caller
   *   passes the bundle name and never the key.
   *
   * @return array<int, string>
   *   Array of entity ids.
   *
   * @throws \RuntimeException
   *   When a bundle is given for an entity type that declares no bundle key.
   */
  public function queryEntityIds(string $entity_type, array $conditions = [], ?string $bundle = NULL): array {
    $this->driverFor(CoreCapabilityInterface::class);

    $query = \Drupal::entityQuery($entity_type)->accessCheck(FALSE);

    if ($bundle !== NULL) {
      $query->condition($this->queryBundleKey($entity_type), $bundle);
    }

    foreach ($conditions as $field => $value) {
      $and = $query->andConditionGroup();
      $and->condition($field, $value);
      $query->condition($and);
    }

    return $query->execute();
  }

  /**
   * Load the ids of the nodes of a content type matching the conditions.
   *
   * @param string $content_type
   *   The content type machine name.
   * @param array<string, mixed> $conditions
   *   Conditions keyed by field names.
   *
   * @return array<int, string>
   *   Array of node ids.
   */
  public function queryNodeIds(string $content_type, array $conditions = []): array {
    return $this->queryEntityIds('node', $conditions, $content_type);
  }

  /**
   * Returns the key an entity type stores its bundle under.
   *
   * @param string $entity_type
   *   The entity type id.
   *
   * @return string
   *   The bundle key, such as 'type' for a node or 'vid' for a term.
   *
   * @throws \RuntimeException
   *   When the entity type declares no bundle key.
   */
  protected function queryBundleKey(string $entity_type): string {
    $bundle_key = \Drupal::entityTypeManager()->getDefinition($entity_type)->getKey('bundle');

    if (!is_string($bundle_key) || $bundle_key === '') {
      throw new \RuntimeException(sprintf('Entity type "%s" declares no bundle key, so it cannot be queried by bundle.', $entity_type));
    }

    return $bundle_key;
  }

  /**
   * Assert that a module backing a set of steps is enabled.
   *
   * Without the check, a step against a missing module fails with a fatal on
   * an unresolvable class or a raw database error, not a message naming the
   * module.
   *
   * @param string $module
   *   The module machine name.
   * @param string $package
   *   Optional Composer package to name in the message. Pass an empty string
   *   for a module that ships with Drupal core.
   *
   * @throws \RuntimeException
   *   When the module is not enabled.
   */
  public function queryAssertModuleEnabled(string $module, string $package = ''): void {
    $this->driverFor(CoreCapabilityInterface::class);

    // @codeCoverageIgnoreStart
    if (\Drupal::moduleHandler()->moduleExists($module)) {
      return;
    }

    $remedy = $package === ''
      ? 'Enable it as part of the site setup; it ships with Drupal core.'
      : sprintf('Add "%s" to the consumer project\'s composer.json and enable the module as part of the site setup.', $package);

    throw new \RuntimeException(sprintf('The "%s" module is not enabled. %s', $module, $remedy));
    // @codeCoverageIgnoreEnd
  }

}
