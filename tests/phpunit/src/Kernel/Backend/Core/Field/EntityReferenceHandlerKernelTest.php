<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Core\Field;

use DrevOps\BehatSteps\Backend\Core\Field\EntityReferenceHandler;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel round-trip test for EntityReferenceHandler via the Core backend.
 *
 * The handler resolves human-readable labels (user names, node titles, etc.)
 * to entity ids. This test exercises the label-to-id lookup against a real
 * user, then verifies the stored target_id round-trips.
 */
#[CoversClass(EntityReferenceHandler::class)]
#[Group('fields')]
#[RunTestsInSeparateProcesses]
class EntityReferenceHandlerKernelTest extends FieldHandlerKernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = [
    ...self::BASE_MODULES,
    'taxonomy',
    'text',
    'filter',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('taxonomy_term');
  }

  public function testUserReferenceByNameRoundTrip(): void {
    $this->attachField('field_owner', 'entity_reference', [
      'target_type' => 'user',
    ]);

    $user = User::create(['name' => 'alice']);
    $user->save();

    // The handler resolves 'alice' to the user's uid; the base helper iterates
    // the backend-mutated stub so this works regardless of the id assigned.
    $this->assertFieldRoundTripViaBackend('field_owner', ['alice']);
  }

  public function testUserReferenceByIdRoundTrip(): void {
    $this->attachField('field_owner', 'entity_reference', [
      'target_type' => 'user',
    ]);

    $user = User::create(['name' => 'bob']);
    $user->save();

    $this->assertFieldRoundTripViaBackend('field_owner', [(int) $user->id()]);
  }

  /**
   * Tests round-trip when a delta is an associative array.
   *
   * A delta may use the field-item shape of file, image or
   * entity_reference_revisions values, e.g. '['target_id' => 'alice',
   * 'display' => 1]'.
   *
   * The handler treats the main property value as the lookup label and
   * resolves it to an id. The original array shape is preserved so any extra
   * item properties round-trip through to storage.
   */
  public function testUserReferenceResolvesAssociativeArrayDelta(): void {
    $this->attachField('field_owner', 'entity_reference', [
      'target_type' => 'user',
    ]);

    User::create(['name' => 'alice'])->save();

    $this->assertFieldRoundTripViaBackend('field_owner', [['target_id' => 'alice']]);
  }

  public function testUserReferenceResolvesMixedScalarAndAssociativeDeltas(): void {
    // Needs an unlimited-cardinality field to store 2 deltas; attachField()
    // always creates a single-value field, so the storage is configured inline.
    FieldStorageConfig::create([
      'field_name' => 'field_owners',
      'entity_type' => self::ENTITY_TYPE,
      'type' => 'entity_reference',
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
      'settings' => ['target_type' => 'user'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_owners',
      'entity_type' => self::ENTITY_TYPE,
      'bundle' => self::BUNDLE,
    ])->save();

    User::create(['name' => 'alice'])->save();
    User::create(['name' => 'bob'])->save();

    $this->assertFieldRoundTripViaBackend('field_owners', [
      ['target_id' => 'alice'],
      'bob',
    ]);
  }

  /**
   * Tests round-trip for an entity_reference field targeting taxonomy terms.
   *
   * Taxonomy terms are referenced through 'entity_reference' with
   * 'target_type = taxonomy_term', so the backend routes through
   * EntityReferenceHandler.
   */
  public function testTaxonomyTermReferenceByNameRoundTrip(): void {
    Vocabulary::create(['vid' => 'tags', 'name' => 'Tags'])->save();
    Term::create(['name' => 'drupal', 'vid' => 'tags'])->save();

    $this->attachField('field_tags', 'entity_reference', [
      'target_type' => 'taxonomy_term',
    ]);

    $this->assertFieldRoundTripViaBackend('field_tags', ['drupal']);
  }

}
