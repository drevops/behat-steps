<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Capability;

/**
 * Capability: read and write the site's key-value state.
 */
interface StateCapabilityInterface {

  /**
   * Returns a state value.
   *
   * @param string $name
   *   The state key.
   *
   * @return mixed
   *   The stored value, or NULL when the key is not set.
   */
  public function stateGet(string $name): mixed;

  /**
   * Sets a state value.
   *
   * @param string $name
   *   The state key.
   * @param mixed $value
   *   The value to store.
   */
  public function stateSet(string $name, mixed $value): void;

  /**
   * Deletes a state value.
   *
   * @param string $name
   *   The state key.
   */
  public function stateDelete(string $name): void;

  /**
   * Whether a state key is set.
   *
   * A key holding NULL counts as set where the backend can tell the difference.
   * A backend reading state through 'stateGet()' alone cannot, and says so on
   * its own implementation.
   *
   * @param string $name
   *   The state key.
   */
  public function stateExists(string $name): bool;

}
