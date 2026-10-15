<?php

declare(strict_types=1);

namespace ConsumerProject\Backend\Alias;

use DrevOps\BehatSteps\Backend\Alias\PreCreateAliasInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;

/**
 * Fixture: a consumer project's alias for a value no shipped alias resolves.
 *
 * Copies 'headline' on a node stub into 'title'.
 */
final class HeadlineAlias implements PreCreateAliasInterface {

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return 'headline';
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityType(): string {
    return 'node';
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return "Sets the node's 'title' to the value supplied via 'headline'.";
  }

  /**
   * {@inheritdoc}
   */
  public function applyToStub(EntityStubInterface $stub): void {
    $stub->setValue('title', $stub->getValue('headline'));
    $stub->removeValue('headline');
  }

}
