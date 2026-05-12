<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Drupal;

use Drupal\Driver\DrupalDriverInterface;
use Drupal\Driver\Entity\EntityStubInterface;

/**
 * Internal helper for fixture path expansion on entity stubs.
 *
 * Shared by Drupal entity creation traits (e.g. ContentTrait, MediaTrait)
 * that need to rewrite bare fixture filenames on 'file' and 'image' fields
 * to absolute fixture paths before drupal-driver's FileHandler reads them.
 *
 * Requires a Drupal context: the consumer must expose 'getMinkParameter()'
 * and 'getDriver()' (e.g. via MinkContext / RawDrupalContext) and Drupal
 * must be bootstrapped at call time.
 *
 * This is an internal trait and should not be used directly in step
 * definitions.
 */
trait EntityFixtureTrait {

  /**
   * Expand fixture file paths for file/image fields on an entity stub.
   *
   * Rewrites bare fixture filenames (e.g. 'document.pdf') on 'file' and
   * 'image' field types to absolute paths under the Mink 'files_path' so
   * drupal-driver's FileHandler can read and upload them during entity
   * creation. Skips expansion when a managed file with the same basename
   * already exists in public:// or private://, so existing files take
   * precedence and behaviour stays backward compatible.
   *
   * @param string $entity_type
   *   The entity type machine name (e.g. 'node', 'media').
   * @param \Drupal\Driver\Entity\EntityStubInterface $stub
   *   The entity stub mutated in place.
   */
  protected function entityFixtureExpand(string $entity_type, EntityStubInterface $stub): void {
    $files_path = $this->getMinkParameter('files_path');

    if (empty($files_path)) {
      return;
    }

    $resolved_files_path = realpath((string) $files_path);

    if ($resolved_files_path === FALSE || !is_dir($resolved_files_path)) {
      return;
    }

    $fixture_path = rtrim($resolved_files_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    $driver = $this->getDriver();

    if (!$driver instanceof DrupalDriverInterface) {
      return;
    }

    $field_types = $driver->getCore()->getEntityFieldTypes($entity_type);

    foreach ($stub->getValues() as $name => $value) {
      if (empty($field_types[$name]) || ($field_types[$name] !== 'image' && $field_types[$name] !== 'file')) {
        continue;
      }

      $basename = is_array($value) ? ($value[0] ?? NULL) : $value;

      if (!is_string($basename) || $basename === '') {
        continue;
      }

      if (str_contains($basename, '/') || str_contains($basename, '\\') || $basename !== basename($basename)) {
        continue;
      }

      if ($this->entityFixtureManagedFileExists($basename)) {
        continue;
      }

      if (!is_file($fixture_path . $basename)) {
        continue;
      }

      if (is_array($value)) {
        $value[0] = $fixture_path . $basename;
        $stub->setValue($name, $value);
      }
      else {
        $stub->setValue($name, $fixture_path . $basename);
      }
    }
  }

  /**
   * Check whether a managed file with the given basename already exists.
   *
   * Mirrors drupal-driver FileHandler::resolveExistingFile() for bare
   * basenames so callers do not pre-empt the driver's own lookup.
   *
   * @param string $basename
   *   Candidate basename (no path separators).
   *
   * @return bool
   *   TRUE when a managed file exists at public://basename or
   *   private://basename.
   */
  protected function entityFixtureManagedFileExists(string $basename): bool {
    if (str_contains($basename, '/') || str_contains($basename, '\\')) {
      return FALSE;
    }

    $storage = \Drupal::entityTypeManager()->getStorage('file');

    foreach (['public', 'private'] as $scheme) {
      if ($storage->loadByProperties(['uri' => $scheme . '://' . $basename])) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
