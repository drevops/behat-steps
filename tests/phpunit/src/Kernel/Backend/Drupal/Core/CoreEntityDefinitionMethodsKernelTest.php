<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Drupal\Core;

use DrevOps\BehatSteps\Backend\Drupal\Core\Core;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use Drupal\entity_test\EntityTestHelper;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for the entity definition methods on Core.
 *
 * The 'entity_test' type carries a multi-value string field on
 * 'custom_bundle' only.
 */
#[CoversClass(Core::class)]
#[Group('core')]
#[RunTestsInSeparateProcesses]
class CoreEntityDefinitionMethodsKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = ['system', 'user', 'entity_test', 'field'];

  /**
   * The Core backend under test.
   */
  protected Core $core;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('entity_test');

    EntityTestHelper::createBundle('custom_bundle');
    EntityTestHelper::createBundle('other_bundle');
    FieldStorageConfig::create(['field_name' => 'field_tags', 'entity_type' => 'entity_test', 'type' => 'string', 'cardinality' => -1])->save();
    FieldConfig::create(['field_name' => 'field_tags', 'entity_type' => 'entity_test', 'bundle' => 'custom_bundle'])->save();

    $this->core = new Core($this->root);
  }

  #[DataProvider('dataProviderGetEntityIdKey')]
  public function testGetEntityIdKey(string $entity_type, string $expected): void {
    $this->assertSame($expected, $this->core->getEntityIdKey($entity_type));
  }

  public static function dataProviderGetEntityIdKey(): \Iterator {
    yield 'a user' => ['user', 'uid'];
    yield 'a test entity' => ['entity_test', 'id'];
  }

  public function testGetEntityIdKeyRejectsAnUnknownEntityType(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessageMatches('/Unknown entity type "nonexistent_type"/');

    $this->core->getEntityIdKey('nonexistent_type');
  }

  public function testGetEntityFieldTypesScopesConfigurableFieldsToTheBundle(): void {
    $this->assertSame('string', $this->core->getEntityFieldTypes('entity_test', 'custom_bundle')['field_tags'] ?? NULL);
    $this->assertArrayNotHasKey('field_tags', $this->core->getEntityFieldTypes('entity_test', 'other_bundle'));
    $this->assertSame('string', $this->core->getEntityFieldTypes('entity_test')['field_tags'] ?? NULL, 'Without a bundle, every configurable field is listed.');
  }

  public function testParseEntityFieldsSplitsTheCellsOfConfigurableFields(): void {
    $stub = new EntityStub('entity_test', 'custom_bundle', ['name' => 'First, second', 'field_tags' => 'one, two', 'author' => 'admin']);

    $this->core->parseEntityFields($stub, ['author']);

    $this->assertSame(['name' => 'First, second', 'field_tags' => ['one', 'two'], 'author' => 'admin'], $stub->getValues(), 'A base field and an ignored value stay as written.');
  }

  public function testParseEntityFieldsRejectsAnUnknownField(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The field "field_missing" does not exist on entity type "entity_test".');

    $this->core->parseEntityFields(new EntityStub('entity_test', 'custom_bundle', ['field_missing' => 'value']));
  }

}
