<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Drupal\Capability;

use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;

/**
 * Capability: create, find and delete the redirects of the 'redirect' module.
 *
 * A source path is stored without its leading slash and an internal
 * destination under the 'internal:/' scheme, as the module stores them, so a
 * caller passes both as a scenario writes them.
 */
interface RedirectCapabilityInterface {

  /**
   * Creates a redirect.
   *
   * @param string $source
   *   The source path, with or without its leading slash.
   * @param string $destination
   *   The destination: an internal path, with or without its leading slash
   *   or the 'internal:' scheme, or an external URL.
   * @param int $status_code
   *   The HTTP status code.
   *
   * @return \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface
   *   A 'redirect' stub carrying its 'rid', flagged as saved with the
   *   redirect attached.
   */
  public function createRedirect(string $source, string $destination, int $status_code = 301): EntityStubInterface;

  /**
   * Deletes a redirect.
   *
   * Does nothing when the redirect does not exist.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The stub returned from a previous 'createRedirect()' call, or one that
   *   carries a 'rid' value resolving to an existing redirect.
   */
  public function deleteRedirect(EntityStubInterface $stub): void;

  /**
   * Finds the ids of the redirects from a source path.
   *
   * @param string $source
   *   The source path, with or without its leading slash.
   * @param string|null $destination
   *   The destination to match as well, in any form 'createRedirect()'
   *   takes, or NULL to match any destination.
   * @param int|null $status_code
   *   The HTTP status code to match as well, or NULL to match any.
   *
   * @return array<int, string>
   *   The redirect ids in natural order. Empty when nothing matches.
   */
  public function findRedirectIds(string $source, ?string $destination = NULL, ?int $status_code = NULL): array;

}
