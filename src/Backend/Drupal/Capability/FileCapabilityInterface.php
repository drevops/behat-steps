<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Drupal\Capability;

use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;

/**
 * Capability: read and write files through the site's stream wrappers.
 */
interface FileCapabilityInterface {

  /**
   * Writes an unmanaged file, creating its directory when it is missing.
   *
   * @param string $uri
   *   The file URI, such as 'public://sample.txt'.
   * @param string $content
   *   The file content.
   *
   * @throws \RuntimeException
   *   When the directory cannot be prepared or the file cannot be written.
   */
  public function fileWriteUnmanaged(string $uri, string $content): void;

  /**
   * Returns the content of an unmanaged file.
   *
   * @param string $uri
   *   The file URI.
   *
   * @throws \RuntimeException
   *   When the file does not exist or cannot be read.
   */
  public function fileReadUnmanaged(string $uri): string;

  /**
   * Whether an unmanaged file exists.
   *
   * @param string $uri
   *   The file URI.
   */
  public function fileExistsUnmanaged(string $uri): bool;

  /**
   * Deletes an unmanaged file.
   *
   * Does nothing when the file does not exist.
   *
   * @param string $uri
   *   The file URI.
   */
  public function fileDeleteUnmanaged(string $uri): void;

  /**
   * Creates the directory a URI points at, when it is missing.
   *
   * @param string $uri
   *   The directory URI, such as 'private://' or 'public://images'.
   *
   * @return bool
   *   TRUE when the directory exists or was created. FALSE when it cannot be,
   *   as for a stream wrapper the site has not configured.
   */
  public function filePrepareDirectory(string $uri): bool;

  /**
   * Writes a file and saves a managed file entity for it.
   *
   * A file already at the URI is replaced.
   *
   * @param string $uri
   *   The destination URI.
   * @param string $content
   *   The file content.
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The file stub. Its values are set on the file entity before the save.
   *
   * @return \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface
   *   The same stub, now flagged as saved with the file attached and its
   *   'fid' value set.
   *
   * @throws \RuntimeException
   *   When the directory cannot be prepared or the file cannot be written.
   */
  public function fileWriteManaged(string $uri, string $content, EntityStubInterface $stub): EntityStubInterface;

}
