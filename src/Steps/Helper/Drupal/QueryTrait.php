<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Helper\Drupal;

use DrevOps\BehatSteps\Backend\Capability\EntityLookupCapabilityInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;

/**
 * Finds the entities a step reads, asserts on or deletes.
 *
 * Every lookup resolves 'EntityLookupCapabilityInterface', so it runs on any
 * backend providing it. An entity found comes back as an id or as a stub
 * carrying the id, never as a loaded entity.
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
   * Load a stub for each entity of a type matching the conditions.
   *
   * @param string $entity_type
   *   The entity type id.
   * @param string $id_key
   *   The key the entity type stores its id under, such as 'nid' for a node.
   * @param array<string, mixed> $conditions
   *   Conditions keyed by field names, as 'queryEntityIds()' takes them.
   * @param string|null $bundle
   *   Bundle to restrict the query to, or NULL for every bundle.
   *
   * @return array<int|string, \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface>
   *   A stub per matching entity carrying its id under the id key, keyed by
   *   id in natural order. Empty when nothing matches.
   */
  public function queryEntityStubs(string $entity_type, string $id_key, array $conditions = [], ?string $bundle = NULL): array {
    $stubs = [];

    foreach ($this->queryEntityIds($entity_type, $conditions, $bundle) as $id) {
      $stubs[$id] = new EntityStub($entity_type, $bundle, [$id_key => $id]);
    }

    return $stubs;
  }

  /**
   * Get the id a stub carries.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   A stub carrying the id under the id key, as a lookup returns it, or a
   *   stub a capability create returned.
   * @param string $id_key
   *   The key the entity type stores its id under, such as 'nid' for a node.
   *
   * @return string
   *   The id.
   *
   * @throws \RuntimeException
   *   When the stub carries no id.
   */
  public function queryGetEntityId(EntityStubInterface $stub, string $id_key): string {
    $id = $stub->getId() ?? $stub->getValue($id_key);

    if ((!is_int($id) && !is_string($id)) || $id === '') {
      throw new \RuntimeException(sprintf('The "%s" stub carries no id under the key "%s".', $stub->getEntityType(), $id_key));
    }

    return (string) $id;
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
