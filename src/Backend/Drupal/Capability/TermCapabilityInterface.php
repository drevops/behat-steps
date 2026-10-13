<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Drupal\Capability;

use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;

/**
 * Capability: create and delete taxonomy terms.
 */
interface TermCapabilityInterface {

  /**
   * Creates a taxonomy term.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The term stub. The bundle property selects the vocabulary.
   *
   * @return \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface
   *   The same stub, now flagged as saved with the created term attached.
   */
  public function createTerm(EntityStubInterface $stub): EntityStubInterface;

  /**
   * Deletes a taxonomy term.
   *
   * Does nothing when the term does not exist.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The stub returned from a previous 'createTerm()' call, or one that
   *   carries a 'tid' value resolving to an existing term.
   */
  public function deleteTerm(EntityStubInterface $stub): void;

}
