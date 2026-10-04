<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Capability;

use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;

/**
 * Capability: create and delete languages.
 */
interface LanguageCapabilityInterface {

  /**
   * Creates a language.
   *
   * A language that already exists is left in place and the stub is not
   * marked saved, so a caller can tell a language it created from one the
   * site already had.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   Language stub. Must carry a 'langcode' value.
   *
   * @return \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface
   *   The same stub, flagged as saved with the language attached when this
   *   call created it.
   */
  public function createLanguage(EntityStubInterface $stub): EntityStubInterface;

  /**
   * Deletes a language.
   *
   * Does nothing when the language does not exist.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   Language stub. Must carry a 'langcode' value.
   */
  public function deleteLanguage(EntityStubInterface $stub): void;

}
