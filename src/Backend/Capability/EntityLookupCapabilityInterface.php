<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Capability;

/**
 * Capability: find the entities that match conditions.
 */
interface EntityLookupCapabilityInterface {

  /**
   * Finds the ids of the entities of a type that match conditions.
   *
   * @param string $entity_type
   *   The entity type id.
   * @param array<string, mixed> $conditions
   *   Conditions keyed by field name. A scalar value matches the field with
   *   '='. An [operator, value] pair matches with the operator it names: '=',
   *   'IN', 'CONTAINS' or 'LIKE', in any case. 'IN' takes a non-empty list of
   *   values and the other 3 take 1 value.
   * @param string|null $bundle
   *   The bundle to restrict the lookup to, or NULL for every bundle.
   *
   * @return array<int, string>
   *   The ids of the matching entities in natural order, so the newest entity
   *   with a serial id comes last. Empty when nothing matches.
   *
   * @throws \RuntimeException
   *   When the entity type is unknown, a bundle is given for an entity type
   *   without bundles, or a condition is malformed.
   */
  public function findEntityIds(string $entity_type, array $conditions = [], ?string $bundle = NULL): array;

}
