<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Helper\Drupal;

use DrevOps\BehatSteps\Backend\Capability\EntityLookupCapabilityInterface;

/**
 * Finds the entities a step reads, asserts on or deletes.
 *
 * Every member resolves 'EntityLookupCapabilityInterface', so a lookup runs on
 * any backend providing it.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\WebRawContext
 */
trait QueryTrait {

  /**
   * Load the ids of the entities of a type matching the conditions.
   *
   * @param string $entity_type
   *   The entity type id.
   * @param array<string, mixed> $conditions
   *   Conditions keyed by field names. A value matches the field with '=',
   *   and an [operator, value] pair matches with '=', 'IN', 'CONTAINS' or
   *   'LIKE'.
   * @param string|null $bundle
   *   Bundle to restrict the query to, or NULL for every bundle. The key the
   *   bundle is matched on comes from the entity type definition, so a caller
   *   passes the bundle name and never the key.
   *
   * @return array<int, string>
   *   Array of entity ids in natural order.
   *
   * @throws \RuntimeException
   *   When a bundle is given for an entity type that declares no bundle key,
   *   or a condition is malformed.
   */
  public function queryEntityIds(string $entity_type, array $conditions = [], ?string $bundle = NULL): array {
    return $this->backendFor(EntityLookupCapabilityInterface::class)->findEntityIds($entity_type, $conditions, $bundle);
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
    // the last id in natural order is the newest entity's.
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

}
