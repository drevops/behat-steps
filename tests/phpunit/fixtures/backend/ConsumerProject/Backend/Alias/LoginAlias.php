<?php

declare(strict_types=1);

namespace ConsumerProject\Backend\Alias;

use DrevOps\BehatSteps\Backend\Alias\PreCreateAliasInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;

/**
 * Fixture: a consumer project's alias for a user value.
 *
 * Derives the name and the mail of a user stub from 'login'.
 */
final class LoginAlias implements PreCreateAliasInterface {

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return 'login';
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityType(): string {
    return 'user';
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return "Sets the user's 'name' and 'mail' from the value supplied via 'login'.";
  }

  /**
   * {@inheritdoc}
   */
  public function applyToStub(EntityStubInterface $stub): void {
    $login = (string) $stub->getValue('login');

    $stub->setValue('name', $login);
    $stub->setValue('mail', $login . '@example.com');
    $stub->removeValue('login');
  }

}
