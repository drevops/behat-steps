<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Alias;

use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;

/**
 * A creation alias that mutates the stub before the entity is created.
 *
 * Implementations resolve the alias value, write any derived real-field
 * values back onto the stub, and remove the alias's own key from the
 * stub. The values bag passed to Drupal's entity factory then contains
 * only real fields.
 */
interface PreCreateAliasInterface extends CreationAliasInterface {

  /**
   * Resolves the alias value and mutates the stub in place.
   *
   * Implementations MUST remove the alias's own value from the stub via
   * 'EntityStubInterface::removeValue()' once resolution succeeds. The
   * exception is an alias that deliberately overwrites the same key with
   * the resolved representation, such as a term name swapped for a tid.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The stub being prepared for creation. Must already carry a value
   *   under 'getName()'.
   *
   * @throws \DrevOps\BehatSteps\Backend\Exception\CreationAliasResolutionException
   *   When the value cannot be resolved into a Drupal storage value.
   */
  public function applyToStub(EntityStubInterface $stub): void;

}
