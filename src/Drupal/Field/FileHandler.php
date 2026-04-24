<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Drupal\Field;

use Drupal\Driver\Core\Field\FileHandler as DriverFileHandler;
use Drupal\file\FileInterface;

/**
 * File field handler that prefers existing managed files over re-uploading.
 *
 * The driver's 'FileHandler' calls 'file_get_contents()' on the supplied
 * path and writes the bytes to a fresh 'public://uniqid().<ext>' destination
 * on every entity create. That breaks two patterns this project exercises:
 *
 * - Pre-existing managed files (via 'fileCreateManaged()') referenced from
 *   a 'createNodes()' table by basename: the bare filename is not a
 *   filesystem path, so 'file_get_contents()' fails and the field cannot be
 *   populated.
 * - Tests that look up a file link by its original filename: the upstream
 *   'FileHandler' renames the file to a unique id at write time, so the
 *   rendered link no longer matches the gherkin step argument.
 *
 * This subclass tries to resolve the supplied value against an existing
 * managed file first - by full URI ('public://text.txt') or by basename
 * ('text.txt' resolved to 'public://text.txt') - and reuses the existing
 * file id when found. Only when no managed file matches does it fall back
 * to the driver's upload-from-path behaviour.
 */
class FileHandler extends DriverFileHandler {

  /**
   * {@inheritdoc}
   */
  public function expand(mixed $values): array {
    $files = [];

    foreach ((array) $values as $value) {
      $is_array = is_array($value);
      $file_path = (string) ($is_array ? $value['target_id'] ?? $value[0] : $value);

      $existing = $this->resolveExistingFile($file_path);
      if ($existing instanceof FileInterface) {
        $files[] = [
          'target_id' => $existing->id(),
          'display' => $is_array ? ($value['display'] ?? 1) : 1,
          'description' => $is_array ? ($value['description'] ?? '') : '',
        ];

        continue;
      }

      $file_extension = pathinfo($file_path, PATHINFO_EXTENSION);
      $data = file_get_contents($file_path);

      if ($data === FALSE) {
        throw new \RuntimeException(sprintf('Error reading file %s.', $file_path));
      }

      /** @var \Drupal\file\FileInterface $file */
      // @phpstan-ignore-next-line globalDrupalDependencyInjection.useDependencyInjection
      $file = \Drupal::service('file.repository')
        ->writeData($data, 'public://' . uniqid() . '.' . $file_extension);
      $file->save();

      $files[] = [
        'target_id' => $file->id(),
        'display' => $is_array ? ($value['display'] ?? 1) : 1,
        'description' => $is_array ? ($value['description'] ?? '') : '',
      ];
    }

    return $files;
  }

  /**
   * Resolves an input value to an existing managed file, if any.
   *
   * Recognised inputs:
   * - Full stream-wrapper URI ('public://foo.txt'): looked up by URI.
   * - Bare filename ('foo.txt'): tried as 'public://foo.txt'.
   *
   * @param string $value
   *   The raw value supplied for the field cell.
   *
   * @return \Drupal\file\FileInterface|null
   *   The matching managed file, or NULL when no managed file is found.
   */
  protected function resolveExistingFile(string $value): ?FileInterface {
    if (str_contains($value, '://')) {
      // @phpstan-ignore-next-line globalDrupalDependencyInjection.useDependencyInjection
      $matches = \Drupal::entityTypeManager()->getStorage('file')
        ->loadByProperties(['uri' => $value]);

      $file = reset($matches);

      return $file instanceof FileInterface ? $file : NULL;
    }

    if (!str_contains($value, '/')) {
      // @phpstan-ignore-next-line globalDrupalDependencyInjection.useDependencyInjection
      $matches = \Drupal::entityTypeManager()->getStorage('file')
        ->loadByProperties(['uri' => 'public://' . $value]);

      $file = reset($matches);

      return $file instanceof FileInterface ? $file : NULL;
    }

    return NULL;
  }

}
