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
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   Language stub. Must carry a 'langcode' value.
   *
   * @return \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface|false
   *   The saved stub, or FALSE if the language already exists.
   */
  public function languageCreate(EntityStubInterface $stub): EntityStubInterface|false;

  /**
   * Deletes a language.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   Language stub. Must carry a 'langcode' value.
   */
  public function languageDelete(EntityStubInterface $stub): void;

}
