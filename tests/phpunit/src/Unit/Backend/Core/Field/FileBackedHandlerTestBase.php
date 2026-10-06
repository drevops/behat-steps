<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Core\Field;

/**
 * Base unit test for handlers that write uploads through 'file.repository'.
 *
 * Supplies the test doubles the file, image and supported-image handlers
 * share. The doubles are a File entity exposing 'id()', the repository that
 * returns one on write, and an entity type manager whose file storage answers
 * URI lookups.
 *
 * Subclasses build their own container in 'setUp()' from these, registering
 * only the services the handler under test uses.
 */
abstract class FileBackedHandlerTestBase extends FieldHandlerUnitTestBase {

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    \Drupal::unsetContainer();
    parent::tearDown();
  }

  /**
   * Builds a fake File entity exposing 'id()'.
   */
  protected static function createFakeFile(int $id): object {
    return new readonly class($id) {

      public function __construct(protected int $id) {}

      /**
       * Returns the configured file entity id.
       */
      public function id(): int {
        return $this->id;
      }

      /**
       * Saves the file entity (no-op in the test double).
       */
      public function save(): void {}

    };
  }

  /**
   * Builds a file.repository stub returning the same File on every write.
   */
  protected function createFileRepository(int $upload_id): object {
    $file = self::createFakeFile($upload_id);

    return new readonly class($file) {

      public function __construct(protected object $file) {}

      /**
       * Returns the configured file entity for any write.
       */
      public function writeData(string $data, string $destination): object {
        return $this->file;
      }

    };
  }

  /**
   * Builds an entity_type.manager stub for the file storage lookup branch.
   *
   * @param array<string, int> $registered_files
   *   Map of URI to file id; anything outside the map produces no match.
   */
  protected function createEntityTypeManager(array $registered_files): object {
    $files_by_uri = [];

    foreach ($registered_files as $uri => $id) {
      $files_by_uri[$uri] = self::createFakeFile($id);
    }

    $storage = new readonly class($files_by_uri) {

      /**
       * @param array<string, object> $filesByUri
       *   Files keyed by URI.
       */
      public function __construct(protected array $filesByUri) {}

      /**
       * Returns the file matching the given URI, or an empty list.
       *
       * @param array<string, string> $properties
       *   Lookup properties keyed by name.
       *
       * @return array<int, object>
       *   Single-element list when matched, empty otherwise.
       */
      public function loadByProperties(array $properties): array {
        $uri = $properties['uri'] ?? NULL;

        return $uri !== NULL && isset($this->filesByUri[$uri])
          ? [$this->filesByUri[$uri]]
          : [];
      }

    };

    return new readonly class($storage) {

      public function __construct(protected object $storage) {}

      /**
       * Returns the stub file storage.
       */
      public function getStorage(string $entity_type_id): object {
        return $this->storage;
      }

    };
  }

}
