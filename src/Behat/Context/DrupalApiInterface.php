<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Behat\Context;

use Behat\Gherkin\Node\TableNode;
use DrevOps\BehatSteps\Behat\Manager\UserManagerInterface;
use DrevOps\BehatSteps\Driver\Entity\EntityStubInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Contract for a context carrying the Drupal scenario lifecycle.
 *
 * A trait cannot implement an interface, so a Drupal step trait cannot state
 * what it needs from its host directly. It names this interface in a
 * '@phpstan-require-implements' annotation instead, and the class composing
 * 'DrupalApiTrait' declares it.
 *
 * @see \DrevOps\BehatSteps\Helper\DrupalApiTrait
 * @see \DrevOps\BehatSteps\Behat\Context\DrupalContext
 */
interface DrupalApiInterface extends DriverAwareInterface {

  /**
   * Creates a node.
   */
  public function nodeCreate(EntityStubInterface $stub): EntityStubInterface;

  /**
   * Creates a user.
   */
  public function userCreate(EntityStubInterface $stub): EntityStubInterface;

  /**
   * Creates a taxonomy term.
   */
  public function termCreate(EntityStubInterface $stub): EntityStubInterface;

  /**
   * Creates an entity of a type that has no dedicated method.
   */
  public function entityCreate(EntityStubInterface $stub): EntityStubInterface;

  /**
   * Creates a language.
   */
  public function languageCreate(EntityStubInterface $stub): EntityStubInterface|false;

  /**
   * Registers an entity saved outside the create pipeline for cleanup.
   */
  public function entityRegister(EntityInterface $entity): void;

  /**
   * Expands a stub's raw Gherkin values into the storage field shape.
   *
   * @param \DrevOps\BehatSteps\Driver\Entity\EntityStubInterface $stub
   *   The stub, mutated in place.
   * @param array<int, string> $ignored_properties
   *   Value names to leave untouched.
   */
  public function parseEntityFields(EntityStubInterface $stub, array $ignored_properties = []): void;

  /**
   * Logs the given user in.
   */
  public function login(EntityStubInterface $user): void;

  /**
   * Logs the current user out.
   */
  public function logout(bool $fast = FALSE): void;

  /**
   * Determines whether a user is logged in for this session.
   */
  public function loggedIn(): bool;

  /**
   * Sets the user manager.
   *
   * @internal
   *   Injection point called by the context initializer.
   */
  public function setUserManager(UserManagerInterface $user_manager): void;

  /**
   * Returns the user manager.
   */
  public function getUserManager(): UserManagerInterface;

  /**
   * Loads the ids of the nodes of a content type matching the conditions.
   *
   * @param string $content_type
   *   The content type machine name.
   * @param array<string, mixed> $conditions
   *   Conditions keyed by field names.
   *
   * @return array<int, string>
   *   Array of node ids.
   */
  public function loadNodeIds(string $content_type, array $conditions = []): array;

  /**
   * Asserts that a module backing a set of steps is enabled.
   */
  public function assertModuleEnabled(string $module, string $package = ''): void;

  /**
   * Expands fixture file paths for file and image fields on an entity stub.
   */
  public function expandEntityFieldsFixtures(string $entity_type, EntityStubInterface $stub): void;

  /**
   * Transposes a vertical table format (field/value columns) to entity arrays.
   *
   * @param \Behat\Gherkin\Node\TableNode $table
   *   The vertical format table.
   *
   * @return array<int, array<string, string>>
   *   Array of entity data arrays. Each entity is an associative array.
   */
  public function transposeVerticalTable(TableNode $table): array;

  /**
   * Converts vertical format entities to a horizontal TableNode.
   *
   * @param array<int, array<string, string>> $entities
   *   Array of entity data arrays from transposeVerticalTable().
   */
  public function buildHorizontalTable(array $entities): TableNode;

}
