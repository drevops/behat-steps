<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Core\Alias;

use DrevOps\BehatSteps\Backend\Alias\PreCreateAliasInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;

/**
 * Renames 'vocabulary_machine_name' on a term stub to 'vid'.
 */
final class VocabularyMachineNameAlias implements PreCreateAliasInterface {

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return 'vocabulary_machine_name';
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityType(): string {
    return 'taxonomy_term';
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return "Selects the vocabulary for a taxonomy term by machine name. Maps to 'vid' on the stub when no bundle or 'vid' is already set.";
  }

  /**
   * {@inheritdoc}
   */
  public function applyToStub(EntityStubInterface $stub): void {
    if (!$stub->hasValue('vocabulary_machine_name')) {
      return;
    }

    $vid = $stub->getValue('vocabulary_machine_name');

    if ($stub->getBundle() === NULL && !$stub->hasValue('vid')) {
      $stub->setValue('vid', (string) $vid);
    }

    $stub->removeValue('vocabulary_machine_name');
  }

}
