<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Helper\Drupal;

use DrevOps\BehatSteps\Backend\Capability\CoreCapabilityInterface;

/**
 * Reads Drupal state a step asserts on without going through a backend.
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
    $this->backendFor(CoreCapabilityInterface::class);

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
   * Find the id of the newest entity of a type matching the conditions.
   *
   * @param string $entity_type
   *   The entity type id. Its ids must be serial, as content entity ids are.
   * @param array<string, mixed> $conditions
   *   Conditions keyed by field names.
   * @param string|null $bundle
   *   Bundle to restrict the query to, or NULL for every bundle.
   *
   * @return string|null
   *   The id of the entity created last, or NULL when no entity matches.
   *
   * @throws \RuntimeException
   *   When a bundle is given for an entity type that declares no bundle key.
   */
  public function queryFindNewestEntityId(string $entity_type, array $conditions = [], ?string $bundle = NULL): ?string {
    $ids = $this->queryEntityIds($entity_type, $conditions, $bundle);

    if ($ids === []) {
      return NULL;
    }

    // A serial id grows with each new entity and stays fixed on a re-save, so
    // the highest id is the newest entity. Natural order compares the ids as
    // numbers.
    usort($ids, strnatcmp(...));

    return end($ids);
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

}
