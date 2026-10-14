<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Drupal\Capability;

use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;

/**
 * Capability: read entity type and field definitions, and parse values by them.
 */
interface EntityDefinitionCapabilityInterface {

  /**
   * Returns the key an entity type stores its id under.
   *
   * @param string $entity_type
   *   The entity type id.
   *
   * @return string
   *   The id key, such as 'nid' for a node.
   *
   * @throws \RuntimeException
   *   When the entity type is unknown or declares no id key.
   */
  public function getEntityIdKey(string $entity_type): string;

  /**
   * Returns the types of the fields that take values through the handlers.
   *
   * Returns the map of every F1, F5, and F9 field that should be routed
   * through the handler pipeline for this entity type. See
   * 'src/Backend/Drupal/Core/Field/README.md' for the classification rules.
   *
   * @param string $entity_type
   *   The entity type ID.
   * @param string|null $bundle
   *   Optional. Bundle to consult for F9 (storage-backed bundle-attached)
   *   fields, and to limit the F5 fields to the ones the bundle carries.
   *   Without a bundle only F1 and F5 fields surface.
   *
   * @return array<string, string>
   *   Map of field name to field type.
   */
  public function getEntityFieldTypes(string $entity_type, ?string $bundle = NULL): array;

  /**
   * Parses the raw values of a stub into the shape of their fields.
   *
   * A table cell arrives as written: a bare scalar, a comma-separated list or
   * a compound 'key:"value"' cell. Each value is parsed against the definition
   * of its field, and the field handlers expand the result on create.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The stub, its values replaced in place.
   * @param array<int, string> $ignored_properties
   *   Value names to leave as written, such as base properties the caller
   *   handles itself.
   */
  public function parseEntityFields(EntityStubInterface $stub, array $ignored_properties = []): void;

}
