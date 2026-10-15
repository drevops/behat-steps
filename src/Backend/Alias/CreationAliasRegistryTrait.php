<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Alias;

use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;

/**
 * Implements the 'CreationAliasCapabilityInterface' registry on a backend.
 */
trait CreationAliasRegistryTrait {

  /**
   * Registered aliases indexed by entity type and alias name.
   *
   * @var array<string, array<string, \DrevOps\BehatSteps\Backend\Alias\CreationAliasInterface>>
   */
  protected array $creationAliases = [];

  /**
   * Adds an alias to the registry.
   *
   * Re-registering the same name on the same entity type replaces the
   * previous entry.
   *
   * @param \DrevOps\BehatSteps\Backend\Alias\CreationAliasInterface $alias
   *   The alias to register.
   *
   * @throws \RuntimeException
   *   When the alias implements neither 'PreCreateAliasInterface' nor
   *   'PostCreateAliasInterface'.
   */
  public function registerCreationAlias(CreationAliasInterface $alias): void {
    if (!$alias instanceof PreCreateAliasInterface && !$alias instanceof PostCreateAliasInterface) {
      throw new \RuntimeException(sprintf('Creation alias class "%s" must implement "%s" or "%s".', $alias::class, PreCreateAliasInterface::class, PostCreateAliasInterface::class));
    }

    $this->creationAliases[$alias->getEntityType()][$alias->getName()] = $alias;
  }

  /**
   * Returns creation aliases that apply to the given entity type.
   *
   * @param string $entity_type
   *   The entity type id.
   *
   * @return array<string, \DrevOps\BehatSteps\Backend\Alias\CreationAliasInterface>
   *   Map of alias name to alias instance. Empty array when no aliases apply.
   */
  public function getCreationAliases(string $entity_type): array {
    return $this->creationAliases[$entity_type] ?? [];
  }

  /**
   * Runs every pre-create alias whose key is present on the stub.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The stub being prepared for creation.
   * @param string $entity_type
   *   The entity type id whose aliases should run.
   */
  protected function applyPreCreateAliases(EntityStubInterface $stub, string $entity_type): void {
    foreach ($this->getCreationAliases($entity_type) as $alias) {
      if (!$alias instanceof PreCreateAliasInterface) {
        continue;
      }

      if (!$stub->hasValue($alias->getName())) {
        continue;
      }

      $alias->applyToStub($stub);
    }
  }

  /**
   * Runs every post-create alias whose key is present on the stub.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The stub used to create the entity.
   * @param object $entity
   *   The entity that was just saved, or a placeholder object carrying only
   *   its id when the entity was saved in another process.
   * @param string $entity_type
   *   The entity type id whose aliases should run.
   */
  protected function applyPostCreateAliases(EntityStubInterface $stub, object $entity, string $entity_type): void {
    foreach ($this->getCreationAliases($entity_type) as $alias) {
      if (!$alias instanceof PostCreateAliasInterface) {
        continue;
      }

      if (!$stub->hasValue($alias->getName())) {
        continue;
      }

      $alias->applyAfterCreate($stub, $entity);
    }
  }

}
