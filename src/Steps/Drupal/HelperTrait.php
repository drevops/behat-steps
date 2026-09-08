<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use DrevOps\BehatSteps\Driver\DrupalDriverInterface;
use DrevOps\BehatSteps\Driver\Entity\EntityStubInterface;
use DrevOps\BehatSteps\Steps\Generic\HelperTrait as CommonHelperTrait;

/**
 * Internal Drupal helper methods for Behat step definitions.
 *
 * Drupal-specific counterpart to the generic HelperTrait: fixture path
 * expansion for file and image fields, managed-file lookups, and node id
 * queries. Includes the generic helper trait so a consumer trait can rely on a
 * single include for both generic and Drupal helpers.
 *
 * Entities saved outside the driver's create pipeline join the scenario
 * teardown through 'RawContext::entityRegister()'.
 *
 * This is an internal trait and should not be used directly in step definitions.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\RawContext
 */
trait HelperTrait {

  use CommonHelperTrait;

  /**
   * Expand fixture file paths for file/image fields on an entity stub.
   *
   * Rewrites fixture paths on 'file' and 'image' field types to absolute
   * paths under the Mink 'files_path' so drupal-driver's FileHandler can read
   * and upload them during entity creation. A path is taken relative to the
   * fixtures directory, so both 'document.pdf' and 'images/photo.png'
   * resolve. Skips expansion when a managed file with the same basename
   * already exists in public:// or private://, so existing files take
   * precedence.
   *
   * @param string $entity_type
   *   The entity type machine name (e.g. 'node', 'media').
   * @param \DrevOps\BehatSteps\Driver\Entity\EntityStubInterface $stub
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

      // Hooks fired by 'RawDrupalContext::nodeCreate()' run before
      // 'parseEntityFields()', so on the node path the value is the raw
      // compound cell as written in the Behat table
      // (e.g. 'target_id:"foo.jpg", alt:"A"').
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
      // every delta is resolved. Keyed records and bare scalars are wrapped
      // in a single-element list, processed once, and unwrapped when written
      // back to the stub.
      $is_list = is_array($value) && array_is_list($value);
      $records = $is_list ? $value : [$value];
      $mutated = FALSE;

      foreach ($records as $index => $record) {
        $path = is_array($record) ? $record['target_id'] ?? $record[0] ?? NULL : $record;

        if (!is_string($path) || $path === '') {
          continue;
        }

        if ($this->helperManagedFileExists($path)) {
          continue;
        }

        $resolved = $this->helperResolveFixtureFile($path, $fixture_path);

        if ($resolved === NULL) {
          continue;
        }

        if (is_array($record)) {
          if (array_key_exists('target_id', $record)) {
            $records[$index]['target_id'] = $resolved;
          }
          else {
            $records[$index][0] = $resolved;
          }
        }
        else {
          $records[$index] = $resolved;
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
   * mode.
   */
  protected function helperLooksLikeCompoundCell(string $value): bool {
    return preg_match('/^\s*[a-z_][a-z0-9_]*\s*:\s*[\"\[]/i', $value) === 1;
  }

  /**
   * Rewrite each 'target_id:"path"' segment to embed the fixture path.
   *
   * Only the 'target_id' key is touched and only when the quoted value is not
   * backed by an existing managed file and resolves to a real file under the
   * fixtures dir. Other compound columns (e.g. 'alt', 'description') are left
   * untouched so the parser can still process them.
   */
  protected function helperExpandCompoundCellFixtures(string $value, string $fixture_path): string {
    $callback = function (array $matches) use ($fixture_path): string {
      $path = $matches[2];

      if ($this->helperManagedFileExists($path)) {
        return $matches[0];
      }

      $resolved = $this->helperResolveFixtureFile($path, $fixture_path);

      return $resolved === NULL ? $matches[0] : $matches[1] . $resolved . $matches[3];
    };

    return (string) preg_replace_callback('/(target_id\s*:\s*")([^"\\\\]+)(")/i', $callback, $value);
  }

  /**
   * Resolve a field value against the fixtures directory.
   *
   * @param string $value
   *   The raw field value: a path relative to the fixtures directory, a
   *   stream URI or an absolute filesystem path.
   * @param string $fixture_path
   *   The resolved fixtures directory, with a trailing separator.
   *
   * @return string|null
   *   The absolute path to the fixture file, or NULL when the value does not
   *   resolve to a file inside the fixtures directory.
   */
  protected function helperResolveFixtureFile(string $value, string $fixture_path): ?string {
    // drupal-driver resolves stream URIs and absolute paths itself.
    if (str_contains($value, '://')) {
      return NULL;
    }

    if (str_starts_with($value, '/') || str_starts_with($value, '\\') || preg_match('#^[a-z]:[\\\\/]#i', $value) === 1) {
      return NULL;
    }

    if (!is_file($fixture_path . $value)) {
      return NULL;
    }

    $resolved = realpath($fixture_path . $value);

    // is_file() also succeeds for a '..' path that lands outside the
    // fixtures directory.
    if ($resolved === FALSE || !str_starts_with($resolved, $fixture_path)) {
      return NULL;
    }

    return $resolved;
  }

  /**
   * Check whether a managed file with the given basename already exists.
   *
   * Mirrors drupal-driver FileHandler::resolveExistingFile() for bare
   * basenames so the driver's own lookup is not pre-empted.
   *
   * @param string $basename
   *   Candidate basename (no path separators).
   *
   * @return bool
   *   TRUE when a managed file exists at public://basename or
   *   private://basename.
   */
  protected function helperManagedFileExists(string $basename): bool {
    $this->drupal();

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

  /**
   * Load the ids of the nodes of a content type matching the conditions.
   *
   * @param string $content_type
   *   The content type machine name.
   * @param array<string, mixed> $conditions
   *   Conditions keyed by field names.
   *
   * @return array<int, string>
   *   Array of node ids.
   */
  protected function helperLoadNodeIds(string $content_type, array $conditions = []): array {
    $this->drupal();

    $query = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('type', $content_type);

    foreach ($conditions as $field => $value) {
      $and = $query->andConditionGroup();
      $and->condition($field, $value);
      $query->condition($and);
    }

    return $query->execute();
  }

  /**
   * Assert that a module backing a set of steps is enabled.
   *
   * Without this check a step reaches a contrib module's API regardless, and
   * the failure arrives as a fatal on an unresolvable class or a raw database
   * error rather than a message naming the module.
   *
   * @param string $module
   *   The module machine name.
   * @param string $package
   *   Optional Composer package to name in the message. Pass an empty string
   *   for a module that ships with Drupal core.
   *
   * @throws \RuntimeException
   *   When the module is not enabled.
   */
  protected function helperAssertModuleEnabled(string $module, string $package = ''): void {
    $this->drupal();

    // @codeCoverageIgnoreStart
    if (\Drupal::moduleHandler()->moduleExists($module)) {
      return;
    }

    $remedy = $package === ''
      ? 'Enable it as part of the site setup; it ships with Drupal core.'
      : sprintf('Add "%s" to the consumer project\'s composer.json and enable the module as part of the site setup.', $package);

    throw new \RuntimeException(sprintf('The "%s" module is not enabled. %s', $module, $remedy));
    // @codeCoverageIgnoreEnd
  }

}
