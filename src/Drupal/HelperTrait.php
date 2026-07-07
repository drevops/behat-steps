<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Drupal;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Hook\AfterScenario;
use DrevOps\BehatSteps\HelperTrait as CommonHelperTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Driver\DrupalDriverInterface;
use Drupal\Driver\Entity\EntityStubInterface;

/**
 * Internal Drupal helper methods for Behat step definitions.
 *
 * Drupal-specific counterpart to the generic HelperTrait: fixture path
 * expansion for file and image fields, managed-file lookups, and a shared
 * per-scenario entity registry. Creation steps register the entities they
 * create; a separate teardown step deletes the registered entities in reverse
 * creation order at scenario end. Includes the generic helper trait so a
 * consumer trait can rely on a single include for both generic and Drupal
 * helpers.
 *
 * Entity types owned by the base Drupal Extension cleanup (node, user,
 * taxonomy_term, user_role, language, configurable_language) are never
 * registered here, so there is no double-deletion.
 *
 * Skip all cleanup with tag: `@behat-steps-skip:entityCleanupAfterScenario`
 * Skip cleanup for one entity type with tag:
 * `@behat-steps-entity-cleanup-skip:media`
 *
 * This is an internal trait and should not be used directly in step definitions.
 */
trait HelperTrait {

  use CommonHelperTrait;

  /**
   * Entity types owned by the base Drupal Extension's own cleanup.
   *
   * Never deleted by this trait to avoid double-deletion with the base
   * extension (re-deleting a user or language throws, unlike nodes/terms).
   */
  const ENTITY_CLEANUP_EXCLUDED_TYPES = [
    'node',
    'user',
    'taxonomy_term',
    'user_role',
    'language',
    'configurable_language',
  ];

  /**
   * Entities registered during the scenario, in creation order.
   *
   * Each item is a [entity_type_id, entity_id] pair. Ids are stored as scalars
   * so each entity is reloaded fresh at cleanup time and an already-deleted row
   * is tolerated. Registering an entity only records it here; deleting
   * registered entities is a separate concern handled at scenario teardown.
   *
   * @var array<int, array{0: string, 1: int|string}>
   */
  protected array $entityRegistry = [];

  /**
   * Register a saved entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The saved entity to register.
   */
  protected function entityRegister(EntityInterface $entity): void {
    $id = $entity->id();

    if ($id !== NULL) {
      $this->entityRegisterId($entity->getEntityTypeId(), $id);
    }
  }

  /**
   * Register an entity by type and id.
   *
   * @param string $entity_type_id
   *   The entity type machine name.
   * @param int|string $entity_id
   *   The entity id.
   */
  protected function entityRegisterId(string $entity_type_id, int|string $entity_id): void {
    $this->entityRegistry[] = [$entity_type_id, $entity_id];
  }

  /**
   * Delete registered entities in reverse creation order.
   */
  #[AfterScenario('@api')]
  public function entityCleanupAfterScenario(AfterScenarioScope $scope): void {
    if ($scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__)) {
      return;
    }

    // @codeCoverageIgnoreStart
    $skip_types = $this->entityCleanupSkippedTypes($scope->getScenario()->getTags());

    foreach (array_reverse($this->entityRegistry) as [$entity_type_id, $entity_id]) {
      if (in_array($entity_type_id, self::ENTITY_CLEANUP_EXCLUDED_TYPES, TRUE) || in_array($entity_type_id, $skip_types, TRUE)) {
        continue;
      }

      $entity = \Drupal::entityTypeManager()->getStorage($entity_type_id)->load($entity_id);
      $entity?->delete();
    }

    $this->entityRegistry = [];
    // @codeCoverageIgnoreEnd
  }

  /**
   * Collect entity types named in the scenario's per-type bypass tags.
   *
   * @param array<int, string> $tags
   *   The scenario's tag names (without the leading '@').
   *
   * @return array<int, string>
   *   Entity type ids to skip, parsed from
   *   'behat-steps-entity-cleanup-skip:<entity_type_id>' tags.
   */
  protected function entityCleanupSkippedTypes(array $tags): array {
    $prefix = 'behat-steps-entity-cleanup-skip:';
    $types = [];

    foreach ($tags as $tag) {
      if (str_starts_with($tag, $prefix)) {
        $types[] = substr($tag, strlen($prefix));
      }
    }

    return $types;
  }

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
   * Requires a Drupal context: the consumer must expose 'getMinkParameter()'
   * and 'getDriver()' (e.g. via MinkContext / RawDrupalContext) and Drupal
   * must be bootstrapped at call time.
   *
   * @param string $entity_type
   *   The entity type machine name (e.g. 'node', 'media').
   * @param \Drupal\Driver\Entity\EntityStubInterface $stub
   *   The entity stub mutated in place.
   */
  protected function helperExpandEntityFieldsFixtures(string $entity_type, EntityStubInterface $stub): void {
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

      // Raw compound string (e.g. 'target_id:"foo.jpg", alt:"A"') as written
      // in the Behat table. Hooks fired by 'RawDrupalContext::nodeCreate()'
      // run before 'parseEntityFields()', so on the node path the helper sees
      // the unparsed cell. Rewrite the basename inside the 'target_id:"..."'
      // segment in place and leave the rest of the cell to the parser.
      if (is_string($value) && $this->helperLooksLikeCompoundCell($value)) {
        $rewritten = $this->helperExpandCompoundCellFixtures($value, $fixture_path);

        if ($rewritten !== $value) {
          $stub->setValue($name, $rewritten);
        }

        continue;
      }

      // Parsed shapes produced by 'EntityFieldParser' or the legacy parser:
      // - scalar: 'foo.jpg' (treated as single-value)
      // - scalar list: ['foo.jpg', 'bar.jpg'] (multi-value)
      // - keyed record: ['target_id' => 'foo.jpg', 'alt' => 'A'] (single compound)
      // - list of records: [['target_id' => 'foo.jpg', 'alt' => 'A'], ...] (multi-value compound)
      //
      // Numerically-indexed arrays (lists) are iterated element-by-element so
      // every delta gets resolved. Keyed records and bare scalars are wrapped
      // in a single-element list, processed once, and unwrapped on the way
      // back into the stub.
      $is_list = is_array($value) && array_is_list($value);
      $records = $is_list ? $value : [$value];
      $mutated = FALSE;

      foreach ($records as $index => $record) {
        $basename = is_array($record) ? $record['target_id'] ?? $record[0] ?? NULL : $record;

        if (!is_string($basename) || $basename === '') {
          continue;
        }

        if (str_contains($basename, '/') || str_contains($basename, '\\') || $basename !== basename($basename)) {
          continue;
        }

        if ($this->helperManagedFileExists($basename)) {
          continue;
        }

        if (!is_file($fixture_path . $basename)) {
          continue;
        }

        if (is_array($record)) {
          if (array_key_exists('target_id', $record)) {
            $records[$index]['target_id'] = $fixture_path . $basename;
          }
          else {
            $records[$index][0] = $fixture_path . $basename;
          }
        }
        else {
          $records[$index] = $fixture_path . $basename;
        }

        $mutated = TRUE;
      }

      if (!$mutated) {
        continue;
      }

      $stub->setValue($name, $is_list ? $records : $records[0]);
    }
  }

  /**
   * Detect a raw compound cell string of the shape 'key:"..."' or 'key:[...]'.
   *
   * Mirrors the top-level pattern 'EntityFieldParser' uses to enter compound
   * mode: an identifier, optional whitespace, ':', optional whitespace, then
   * a '"' or '['. Used to distinguish a compound cell that needs in-string
   * basename rewriting from a bare scalar basename like 'document.pdf'.
   */
  protected function helperLooksLikeCompoundCell(string $value): bool {
    return preg_match('/^\s*[a-z_][a-z0-9_]*\s*:\s*[\"\[]/i', $value) === 1;
  }

  /**
   * Rewrite each 'target_id:"basename"' segment to embed the fixture path.
   *
   * Only the 'target_id' key is touched and only when the quoted value is a
   * pure basename (no separators), is not backed by an existing managed file
   * and resolves to a real file under the fixtures dir. Other compound
   * columns (e.g. 'alt', 'description') are left untouched so the parser can
   * still process them.
   */
  protected function helperExpandCompoundCellFixtures(string $value, string $fixture_path): string {
    $callback = function (array $matches) use ($fixture_path): string {
      $basename = $matches[2];

      if ($basename === '' || str_contains($basename, '/') || str_contains($basename, '\\')) {
        return $matches[0];
      }

      if ($basename !== basename($basename)) {
        return $matches[0];
      }

      if ($this->helperManagedFileExists($basename)) {
        return $matches[0];
      }

      if (!is_file($fixture_path . $basename)) {
        return $matches[0];
      }

      return $matches[1] . $fixture_path . $basename . $matches[3];
    };

    return (string) preg_replace_callback('/(target_id\s*:\s*")([^"\\\\]+)(")/i', $callback, $value);
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
  protected function helperManagedFileExists(string $basename): bool {
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
