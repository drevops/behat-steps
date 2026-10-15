<?php

declare(strict_types=1);

namespace ConsumerProject\Backend\Alias;

use DrevOps\BehatSteps\Backend\Alias\PreCreateAliasInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;

/**
 * Fixture: a consumer project's alias replacing the shipped 'author' alias.
 *
 * Reads 'author' on a node stub as a user ID, where the shipped alias reads a
 * username.
 */
final class AuthorIdAlias implements PreCreateAliasInterface {

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return 'author';
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
    return "Sets the node's 'uid' to the user ID supplied via 'author'.";
  }

  /**
   * {@inheritdoc}
   */
  public function applyToStub(EntityStubInterface $stub): void {
    $stub->setValue('uid', (int) $stub->getValue('author'));
    $stub->removeValue('author');
  }

}
