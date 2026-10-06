<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Capability;

/**
 * Capability: read and write configuration.
 */
interface ConfigCapabilityInterface {

  /**
   * Returns a configuration value.
   *
   * @param string $name
   *   The configuration object name.
   * @param string|null $key
   *   The key within the configuration object, or NULL for the whole object.
   *
   * @return mixed
   *   The configuration value, or NULL if not set.
   */
  public function configGet(string $name, ?string $key = NULL): mixed;

  /**
   * Returns a configuration value with overrides not applied.
   *
   * Module and 'settings.php' overrides are left out, so the result is the
   * stored value a write replaces.
   *
   * @param string $name
   *   The configuration object name.
   * @param string|null $key
   *   The key within the configuration object, or NULL for the whole object.
   *
   * @return mixed
   *   The stored configuration value, or NULL if not set.
   */
  public function configGetOriginal(string $name, ?string $key = NULL): mixed;

  /**
   * Sets a configuration value.
   *
   * @param string $name
   *   The configuration object name.
   * @param string $key
   *   The key within the configuration object.
   * @param mixed $value
   *   The value to store.
   */
  public function configSet(string $name, string $key, mixed $value): void;

  /**
   * Whether a configuration object exists.
   *
   * @param string $name
   *   The configuration object name.
   */
  public function configExists(string $name): bool;

  /**
   * Returns every stored value of a configuration object.
   *
   * Overrides are not applied, so the result is the data a write would
   * replace. Pairs with 'configSetData()' to snapshot and restore an object.
   *
   * @param string $name
   *   The configuration object name.
   *
   * @return array<int|string, mixed>
   *   The object's data, or an empty array when it does not exist.
   */
  public function configGetData(string $name): array;

  /**
   * Replaces every stored value of a configuration object.
   *
   * Keys absent from the given data are dropped, so restoring a snapshot
   * removes the keys added since it was taken.
   *
   * @param string $name
   *   The configuration object name.
   * @param array<int|string, mixed> $data
   *   The data to store in place of the object's current data.
   */
  public function configSetData(string $name, array $data): void;

  /**
   * Deletes a configuration object.
   *
   * Does nothing when the object does not exist.
   *
   * @param string $name
   *   The configuration object name.
   */
  public function configDelete(string $name): void;

}
