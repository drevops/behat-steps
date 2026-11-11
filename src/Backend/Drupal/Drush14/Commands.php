<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Drupal\Drush14;

use DrevOps\BehatSteps\Backend\Drupal\Drush\Commands as BaseCommands;
use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;

/**
 * The Drush commands of Drush 14.
 */
class Commands extends BaseCommands {

  /**
   * The ID of the experimental notice 'cancelUser()' records.
   */
  public const string USER_DELETE_NOTICE = 'drush14.user-delete';

  /**
   * {@inheritdoc}
   *
   * 'user:cancel' can fail on an account that exists, so a failure falls back
   * to 'entity:delete', which deletes the account and, through Drupal's own
   * predelete hooks, its nodes. The fallback is experimental, so in strict
   * mode the 'user:cancel' failure is thrown instead.
   */
  protected function cancelUser(EntityStubInterface $stub, array $arguments, array $lookup): void {
    try {
      parent::cancelUser($stub, $arguments, $lookup);
    }
    catch (\RuntimeException $exception) {
      $this->experimentalFallback(static::USER_DELETE_NOTICE, 'Drush 14 "user:cancel" failed on an existing account, so the account was deleted with "entity:delete" instead.', $exception);

      $this->drush('entity:delete', ['user', $this->resolveUserId($stub)], ['yes' => NULL]);
    }
  }

  /**
   * Resolves the id of a user stub, reading it from Drush for a name.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The user stub.
   *
   * @return string
   *   The user id.
   *
   * @throws \RuntimeException
   *   When Drush prints no id for the account.
   */
  protected function resolveUserId(EntityStubInterface $stub): string {
    [$key, $value] = $this->resolveUserIdentifier($stub);

    if ($key === 'uid') {
      return $value;
    }

    $uid = $this->readUserInformation($stub)['uid'] ?? NULL;

    if (!is_scalar($uid) || (string) $uid === '') {
      throw new \RuntimeException(sprintf('Drush did not print the id of the user "%s".', $value));
    }

    return (string) $uid;
  }

  /**
   * {@inheritdoc}
   *
   * Drush 14 has no 'no-browser' option and never opens a browser.
   */
  protected function getOneTimeLoginOptions(EntityStubInterface $stub): array {
    [$key, $value] = $this->resolveUserIdentifier($stub);

    return [$key => $value];
  }

  /**
   * {@inheritdoc}
   *
   * Drush 14 prints only a success message, such as "Created a new user with
   * uid 123", where earlier majors print the account's information.
   */
  protected function parseUserId(string $info): ?int {
    if (preg_match('/Created a new user with uid (\d+)/', $info, $matches) === 1) {
      return (int) $matches[1];
    }

    return parent::parseUserId($info);
  }

}
