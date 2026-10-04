<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Capability;

/**
 * Capability: expose the backend's entity creation aliases.
 *
 * Creation aliases are convenience property names on entity stubs that are
 * not real Drupal fields. An example is 'author' on a node stub, which the
 * backend translates to a 'uid' value during creation.
 *
 * This capability lets consumers discover which aliases are accepted, for
 * what entity type, and how to document them.
 *
 * This interface is intentionally NOT extended by the composite backend
 * interfaces. Consumers should check '$backend instanceof
 * CreationAliasCapabilityInterface' before calling
 * 'getCreationAliases()' so that hand-rolled backends and test doubles
 * that target the composite interfaces remain valid.
 */
interface CreationAliasCapabilityInterface {

  /**
   * Returns creation aliases that apply to the given entity type.
   *
   * @param string $entity_type
   *   The Drupal entity type id (e.g. 'node', 'user', 'taxonomy_term').
   *
   * @return array<string, \DrevOps\BehatSteps\Backend\Alias\CreationAliasInterface>
   *   Map of alias name to alias instance. Empty array when no aliases apply.
   */
  public function getCreationAliases(string $entity_type): array;

}
