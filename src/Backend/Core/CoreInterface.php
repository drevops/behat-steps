<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Core;

use DrevOps\BehatSteps\Backend\Capability\BatchCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\BlockCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\CacheCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\ConfigCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\ContentCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\CronCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\LanguageCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\MailCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\ModuleCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\RoleCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\StateCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\UserCapabilityInterface;
use DrevOps\BehatSteps\Backend\Core\Field\FieldClassifierInterface;
use DrevOps\BehatSteps\Backend\Core\Field\FieldHandlerInterface;
use DrevOps\BehatSteps\Backend\Core\Field\FieldShapeClassifierInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;
use Drupal\Component\Utility\Random;

/**
 * Contract for a Drupal-backed core implementation.
 *
 * Combines the Drupal-bootstrap internals (validate, module list, field
 * handler, and so on) with the operational capabilities every Core provides.
 *
 * Authentication is deliberately absent: a Core declares
 * 'AuthenticationCapabilityInterface' separately, so an 'instanceof' check
 * determines whether it can log a user in.
 */
interface CoreInterface extends
  BatchCapabilityInterface,
  BlockCapabilityInterface,
  CacheCapabilityInterface,
  ConfigCapabilityInterface,
  ContentCapabilityInterface,
  CronCapabilityInterface,
  LanguageCapabilityInterface,
  MailCapabilityInterface,
  ModuleCapabilityInterface,
  RoleCapabilityInterface,
  StateCapabilityInterface,
  UserCapabilityInterface {

  /**
   * Returns a random-value generator.
   */
  public function getRandom(): Random;

  /**
   * Boots Drupal in-process.
   */
  public function bootstrap(): void;

  /**
   * Validates the Drupal site and prepares the environment for bootstrap.
   *
   * @throws \DrevOps\BehatSteps\Backend\Exception\BootstrapException
   *   Thrown when the Drupal site cannot be bootstrapped.
   *
   * @see _drush_bootstrap_drupal_site_validate()
   */
  public function validateDrupalSite(): void;

  /**
   * Returns a list of installed module machine names.
   *
   * @return array<int, string>
   *   Installed module names.
   */
  public function getModuleList(): array;

  /**
   * Returns absolute paths for enabled extensions.
   *
   * @return array<string>
   *   Absolute paths to enabled extensions.
   */
  public function getExtensionPathList(): array;

  /**
   * Returns a field handler for the given stub/field.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The entity stub providing the bundle context.
   * @param string $entity_type
   *   The entity type ID.
   * @param string $field_name
   *   The field machine name.
   *
   * @return \DrevOps\BehatSteps\Backend\Core\Field\FieldHandlerInterface
   *   The matching field handler.
   */
  public function getFieldHandler(EntityStubInterface $stub, string $entity_type, string $field_name): FieldHandlerInterface;

  /**
   * Registers a field handler class for a field type.
   *
   * Overrides one of the backend's built-in handlers or adds a handler for a
   * field type the backend does not ship one for. The registration replaces
   * the default registered by 'Core::registerDefaultFieldHandlers()' in the
   * constructor. A class that does not implement 'FieldHandlerInterface', or
   * that is abstract, triggers a 'RuntimeException' at registration time
   * rather than at field resolution time.
   *
   * @param string $field_type
   *   The Drupal field type id, e.g. 'boolean', 'entity_reference', or a
   *   project-specific id registered by a contrib module.
   * @param class-string $class
   *   The handler class to instantiate when a field of this type is
   *   expanded. The class must implement 'FieldHandlerInterface'.
   *
   * @throws \RuntimeException
   *   When '$class' does not implement 'FieldHandlerInterface' or is abstract.
   */
  public function registerFieldHandler(string $field_type, string $class): void;

  /**
   * Returns the field types for the given entity type.
   *
   * Returns the map of every F1, F5, and F9 field that should be routed
   * through the handler pipeline for this entity type. See
   * 'src/Backend/Core/Field/README.md' for the classification rules.
   *
   * @param string $entity_type
   *   The entity type ID.
   * @param string|null $bundle
   *   Optional. Bundle to consult for F9 (storage-backed bundle-attached)
   *   fields. Without a bundle only F1 and F5 fields surface.
   *
   * @return array<string, string>
   *   Map of field name to field type.
   */
  public function getEntityFieldTypes(string $entity_type, ?string $bundle = NULL): array;

  /**
   * Returns the field classifier, lazily instantiating on first access.
   *
   * The field classifier reports which F-row a field belongs to (F1, F2, ...,
   * F9). See 'src/Backend/Core/Field/README.md'.
   *
   * @return \DrevOps\BehatSteps\Backend\Core\Field\FieldClassifierInterface
   *   The field classifier instance.
   */
  public function getFieldClassifier(): FieldClassifierInterface;

  /**
   * Returns the field shape classifier, lazily instantiating on first access.
   *
   * The field shape classifier reports a field's stored value shape - whether
   * it is an entity reference or a complex/nested value - during handler
   * selection. See 'src/Backend/Core/Field/README.md'.
   *
   * @return \DrevOps\BehatSteps\Backend\Core\Field\FieldShapeClassifierInterface
   *   The field shape classifier instance.
   */
  public function getFieldShapeClassifier(): FieldShapeClassifierInterface;

}
