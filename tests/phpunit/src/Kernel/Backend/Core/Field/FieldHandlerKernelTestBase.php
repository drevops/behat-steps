<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Core\Field;

use DrevOps\BehatSteps\Backend\Core\Core;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\entity_test\EntityTestHelper;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Base class for field handler kernel round-trip tests.
 *
 * Provides scaffolding (entity_test bundle, field attachment helper) and a
 * round-trip assertion that drives entity creation entirely through the Core
 * backend. Subclasses add the handler's declaring module to the module list
 * and implement test methods that:
 *   1. Call attachField() to declare the field under test.
 *   2. Call assertFieldRoundTripViaBackend() with the input value.
 *
 * The round-trip assertion compares the backend-mutated EntityStub, which
 * holds the handler's expand() output, against the reloaded entity. It does
 * not assert specific expand() values; that coverage belongs in the
 * per-handler unit tests.
 */
#[RunTestsInSeparateProcesses]
abstract class FieldHandlerKernelTestBase extends KernelTestBase {

  /**
   * Absolute path to the backend fixture files, with a trailing separator.
   */
  protected const FIXTURES_PATH = __DIR__ . '/../../../../../fixtures/backend/files/';

  /**
   * Baseline modules every field handler kernel test needs.
   *
   * Subclasses redeclare $modules as [...self::BASE_MODULES, 'handler_module'].
   *
   * @var array<string>
   */
  protected const BASE_MODULES = [
    'system',
    'field',
    'entity_test',
    'user',
  ];

  /**
   * The entity type used to host test fields.
   */
  protected const ENTITY_TYPE = 'entity_test';

  /**
   * The bundle used to host test fields.
   */
  protected const BUNDLE = 'entity_test';

  /**
   * The backend under test.
   */
  protected Core $core;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema(static::ENTITY_TYPE);
    $this->installEntitySchema('user');
    $this->installConfig(['system']);

    // entity_test does not auto-register a default bundle in kernel tests.
    EntityTestHelper::createBundle(static::BUNDLE);

    // Core::bootstrap() is not called: KernelTestBase has already booted the
    // kernel, and a Core instance is only needed to call the backend API
    // methods on.
    $this->core = new Core($this->root);
  }

  /**
   * Attaches a field to the test bundle.
   *
   * @param string $field_name
   *   The machine name of the field.
   * @param string $type
   *   The field type (e.g. 'datetime', 'link', 'list_string').
   * @param array<string, mixed> $storage_settings
   *   Settings passed to FieldStorageConfig.
   * @param array<string, mixed> $field_settings
   *   Settings passed to FieldConfig.
   */
  protected function attachField(string $field_name, string $type, array $storage_settings = [], array $field_settings = []): void {
    FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => static::ENTITY_TYPE,
      'type' => $type,
      'settings' => $storage_settings,
    ])->save();

    FieldConfig::create([
      'field_name' => $field_name,
      'entity_type' => static::ENTITY_TYPE,
      'bundle' => static::BUNDLE,
      'settings' => $field_settings,
    ])->save();
  }

  /**
   * Drives entity creation through the backend and asserts field round-trip.
   *
   * Core::createEntity mutates the passed stub so its values reflect whatever
   * the handler emitted. This method iterates those post-expansion values and
   * asserts the reloaded entity holds the same data.
   *
   * For single-property scalar values, the assertion compares against the
   * main field column.
   *
   * For multi-property arrays (e.g. link.uri / link.title), the assertion
   * compares only the keys the test set. Computed or defaulted columns that
   * the storage layer may populate are ignored.
   *
   * @param string $field_name
   *   The field to round-trip.
   * @param array<int, mixed> $values
   *   Field deltas. Each delta is either a scalar (for single-property fields)
   *   or an associative array (for multi-property fields).
   */
  protected function assertFieldRoundTripViaBackend(string $field_name, array $values): void {
    $stub = new EntityStub(static::ENTITY_TYPE, static::BUNDLE, [
      'name' => 'test entity',
      $field_name => $values,
    ]);

    $this->core->createEntity($stub);

    $reloaded = \Drupal::entityTypeManager()
      ->getStorage(static::ENTITY_TYPE)
      ->loadUnchanged($stub->getValue('id'));
    $this->assertInstanceOf(ContentEntityInterface::class, $reloaded);

    // Some handlers (e.g. ImageHandler) emit a flat associative array as
    // single-delta shorthand rather than a list of deltas. Normalise that
    // shape into a 1-element list so the iteration below is uniform.
    $expanded = $stub->getValue($field_name);
    $deltas = is_array($expanded) && !array_is_list($expanded)
      ? [$expanded]
      : $expanded;

    // Assert the stored delta count matches the stub; the per-delta loop
    // alone would not detect a handler that duplicates or appends deltas.
    $field_items = $reloaded->get($field_name);
    $this->assertCount(
      count($deltas),
      $field_items,
      sprintf('Field "%s" stored an unexpected number of deltas.', $field_name),
    );

    foreach ($deltas as $delta => $expected) {
      $item = $field_items->get($delta);
      $this->assertNotNull($item, sprintf('Field "%s" is missing delta %d.', $field_name, $delta));

      if (is_array($expected)) {
        $actual = array_intersect_key($item->getValue(), $expected);
        $this->assertEquals($expected, $actual, sprintf('Field "%s" delta %d did not round-trip.', $field_name, $delta));
      }
      else {
        // Most fields use 'value', but entity_reference uses 'target_id' and
        // other types may use a different key. Loose equality is intentional:
        // SQLite returns integer/float columns as strings.
        $raw = $item->getValue();
        $actual = $raw['value'] ?? reset($raw);
        $this->assertEquals($expected, $actual, sprintf('Field "%s" delta %d did not round-trip.', $field_name, $delta));
      }
    }
  }

  /**
   * Returns the highest file id currently in storage.
   *
   * A handler that uploads writes a new File, and the test asserts against the
   * most recent one rather than an id fixed in advance.
   */
  protected function latestFileId(): int {
    $ids = \Drupal::entityTypeManager()
      ->getStorage('file')
      ->getQuery()
      ->accessCheck(FALSE)
      ->sort('fid', 'DESC')
      ->range(0, 1)
      ->execute();

    return (int) reset($ids);
  }

}
