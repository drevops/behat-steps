<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Drupal\Capability;

use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;

/**
 * Capability: set the path alias of an entity.
 */
interface PathAliasCapabilityInterface {

  /**
   * Sets the path alias of an entity, replacing the alias it has.
   *
   * The existing alias is changed rather than a second one added, and a
   * module that generates aliases leaves the new one in place.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   A stub carrying the entity type's id key, or one returned from a
   *   previous create.
   * @param string $alias
   *   The alias, with or without its leading slash.
   *
   * @throws \RuntimeException
   *   When the alias names no path, the stub carries no id, the id names no
   *   entity, or the entity has no path field.
   */
  public function setEntityPathAlias(EntityStubInterface $stub, string $alias): void;

}
