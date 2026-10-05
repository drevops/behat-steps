<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Core\Field;

use DrevOps\BehatSteps\Backend\Core\Field\FieldShapeClassifier;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\DataReferenceTargetDefinition;
use Drupal\Core\TypedData\MapDataDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests value-shape classification by stored property definitions.
 */
#[CoversClass(FieldShapeClassifier::class)]
#[Group('core')]
#[Group('fields')]
class FieldShapeClassifierTest extends UnitTestCase {

  /**
   * Tests entity-reference detection by a DataReferenceTargetDefinition.
   */
  public function testFieldIsEntityReference(): void {
    $classifier = new FieldShapeClassifier();

    $this->assertTrue($classifier->fieldIsEntityReference($this->storageWithProperties([
      'target_id' => DataReferenceTargetDefinition::create('integer'),
    ])));

    $this->assertFalse($classifier->fieldIsEntityReference($this->storageWithProperties([
      'value' => DataDefinition::create('string'),
    ])));

    $this->assertFalse($classifier->fieldIsEntityReference($this->storageWithProperties([
      'value' => DataDefinition::create('datetime_iso8601'),
    ])));

    // A computed reference is storage-derived, not author-supplied, so it is
    // ignored.
    $this->assertFalse($classifier->fieldIsEntityReference($this->storageWithProperties([
      'value' => DataDefinition::create('string'),
      'entity' => DataReferenceTargetDefinition::create('integer')->setComputed(TRUE),
    ])));
  }

  /**
   * Tests complex-value detection by a ComplexDataDefinitionInterface.
   */
  public function testFieldIsComplexValue(): void {
    $classifier = new FieldShapeClassifier();

    $this->assertTrue($classifier->fieldIsComplexValue($this->storageWithProperties([
      'value' => DataDefinition::create('string'),
      'options' => MapDataDefinition::create(),
    ])));

    $this->assertFalse($classifier->fieldIsComplexValue($this->storageWithProperties([
      'value' => DataDefinition::create('string'),
      'format' => DataDefinition::create('string'),
    ])));
  }

  /**
   * Builds a storage definition mock exposing the given property definitions.
   *
   * @param array<string, \Drupal\Core\TypedData\DataDefinitionInterface> $properties
   *   Property definitions keyed by property name.
   */
  protected function storageWithProperties(array $properties): FieldStorageDefinitionInterface {
    $storage = $this->createMock(FieldStorageDefinitionInterface::class);
    $storage->method('getPropertyDefinitions')->willReturn($properties);

    return $storage;
  }

}
