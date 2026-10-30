<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Capability;

use DrevOps\BehatSteps\Backend\Alias\CreationAliasInterface;

/**
 * Capability: the backend's entity creation aliases.
 *
 * Creation aliases are convenience property names on entity stubs that are
 * not fields of the entity. An example is 'author' on a node stub, which the
 * backend translates to a 'uid' value during creation.
 *
 * This capability lets consumers discover which aliases are accepted, for
 * what entity type, and how to document them. It also lets them register
 * aliases of their own.
 *
 * This interface is intentionally NOT extended by the composite backend
 * interfaces. Consumers should check '$backend instanceof
 * CreationAliasCapabilityInterface' before calling its methods so that
 * hand-rolled backends and test doubles that target the composite interfaces
 * remain valid.
 */
interface CreationAliasCapabilityInterface {

  /**
   * Returns creation aliases that apply to the given entity type.
   *
   * @param string $entity_type
   *   The entity type id (e.g. 'node', 'user', 'taxonomy_term').
   *
   * @return array<string, \DrevOps\BehatSteps\Backend\Alias\CreationAliasInterface>
   *   Map of alias name to alias instance. Empty array when no aliases apply.
   */
  public function getCreationAliases(string $entity_type): array;

  /**
   * Registers a creation alias.
   *
   * The backend runs the alias each time it creates an entity of the alias's
   * entity type from field values, when the stub carries a value under the
   * alias's name. A registration replaces the alias registered under the same
   * name for the same entity type, shipped or not.
   *
   * @param \DrevOps\BehatSteps\Backend\Alias\CreationAliasInterface $alias
   *   The alias. It implements 'PreCreateAliasInterface' or
   *   'PostCreateAliasInterface'.
   *
   * @throws \RuntimeException
   *   When the alias implements neither 'PreCreateAliasInterface' nor
   *   'PostCreateAliasInterface', so it would never run.
   */
  public function registerCreationAlias(CreationAliasInterface $alias): void;

}
