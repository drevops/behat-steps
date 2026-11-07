<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Drupal\Core\Field;

use DrevOps\BehatSteps\Backend\Drupal\Core\Field\ColorFieldTypeHandler;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel round-trip test for ColorFieldTypeHandler via the Core backend.
 *
 * The 'color_field_type' field type is provided by drupal/color_field and
 * stores 2 columns, 'color' and 'opacity'.
 */
#[CoversClass(ColorFieldTypeHandler::class)]
#[Group('fields')]
#[RunTestsInSeparateProcesses]
class ColorFieldTypeHandlerKernelTest extends FieldHandlerKernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = [
    ...self::BASE_MODULES,
    'color_field',
  ];

  public function testColorFieldResolvesHandler(): void {
    $this->attachField('field_colour', 'color_field_type');

    $stub = new EntityStub('entity_test', 'entity_test', ['name' => 'resolved']);

    $this->assertInstanceOf(ColorFieldTypeHandler::class, $this->core->getFieldHandler($stub, 'entity_test', 'field_colour'));
  }

  public function testColorFieldHexRoundTrip(): void {
    $this->attachField('field_colour', 'color_field_type');

    $this->assertFieldRoundTripViaBackend('field_colour', ['#502001']);
  }

  public function testColorFieldRecordWithOpacityRoundTrip(): void {
    $this->attachField('field_colour', 'color_field_type');

    $this->assertFieldRoundTripViaBackend('field_colour', [
      ['color' => '#502001', 'opacity' => 0.5],
    ]);
  }

  public function testColorFieldMultipleDeltasRoundTrip(): void {
    // attachField() always creates a single-value field, so the
    // unlimited-cardinality storage is configured inline.
    FieldStorageConfig::create([
      'field_name' => 'field_colours',
      'entity_type' => static::ENTITY_TYPE,
      'type' => 'color_field_type',
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_colours',
      'entity_type' => static::ENTITY_TYPE,
      'bundle' => static::BUNDLE,
    ])->save();

    $this->assertFieldRoundTripViaBackend('field_colours', [
      '#502001',
      ['color' => '#FFFFFF', 'opacity' => 0.25],
    ]);
  }

  public function testColorFieldEmptyValueLeavesFieldUnset(): void {
    $this->attachField('field_colour', 'color_field_type');

    $stub = new EntityStub('entity_test', 'entity_test', [
      'name' => 'empty',
      'field_colour' => '',
    ]);
    $this->core->createEntity($stub);

    $this->assertTrue(EntityTest::load($stub->getValue('id'))->get('field_colour')->isEmpty());
  }

  public function testColorFieldStoresTheFormattedColor(): void {
    $this->attachField('field_colour', 'color_field_type');

    // The default '#HEXHEX' storage format upper-cases the value on save.
    $stub = new EntityStub('entity_test', 'entity_test', [
      'name' => 'formatted',
      'field_colour' => '#ab12cd',
    ]);
    $this->core->createEntity($stub);

    $values = EntityTest::load($stub->getValue('id'))->get('field_colour')->getValue();
    $this->assertSame('#AB12CD', $values[0]['color']);
    $this->assertNull($values[0]['opacity']);
  }

  public function testColorFieldRejectsOpacityWhenNotRecorded(): void {
    $this->attachField('field_colour', 'color_field_type', [], ['opacity' => FALSE]);

    $stub = new EntityStub('entity_test', 'entity_test', [
      'name' => 'no opacity',
      'field_colour' => ['color' => '#502001', 'opacity' => '0.5'],
    ]);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Cannot set the opacity "0.5" because opacity is not recorded on this field.');

    $this->core->createEntity($stub);
  }

}
