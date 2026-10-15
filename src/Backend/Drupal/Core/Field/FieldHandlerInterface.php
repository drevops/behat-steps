<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Drupal\Core\Field;

use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;

/**
 * Field handler contract.
 *
 * A handler is constructed for 1 field of the entity a stub describes, so
 * every implementation takes the constructor arguments declared here.
 */
interface FieldHandlerInterface {

  /**
   * Constructs a handler for 1 field of an entity.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The stub of the entity the field belongs to.
   * @param string $entity_type
   *   The entity type.
   * @param string $field_name
   *   The field name.
   */
  public function __construct(EntityStubInterface $stub, string $entity_type, string $field_name);

  /**
   * Transforms loose field input into the storage shape.
   *
   * @param mixed $values
   *   Whatever shape the caller produced.
   *
   * @return array<int|string, mixed>
   *   Field values in the format expected by Drupal's entity storage.
   */
  public function expand(mixed $values): array;

}
