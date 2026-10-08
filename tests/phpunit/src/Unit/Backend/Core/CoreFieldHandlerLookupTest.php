<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Core;

use DrevOps\BehatSteps\Backend\Core\Core;
use DrevOps\BehatSteps\Backend\Core\Field\AbstractHandler;
use DrevOps\BehatSteps\Backend\Core\Field\AddressHandler;
use DrevOps\BehatSteps\Backend\Core\Field\DefaultHandler;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests field handler resolution against the registry.
 */
#[CoversClass(Core::class)]
#[Group('core')]
#[Group('fields')]
class CoreFieldHandlerLookupTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setUpDrupalContainer();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    \Drupal::unsetContainer();
    parent::tearDown();
  }

  public function testConstructorRegistersBuiltInHandlers(): void {
    $core = new FieldTypeMapCore(__DIR__, 'default', ['field_address' => 'address']);

    $handler = $core->getFieldHandler(new EntityStub('node'), 'node', 'field_address');

    $this->assertInstanceOf(AddressHandler::class, $handler);
  }

  public function testConsumerRegistrationOverridesBuiltIn(): void {
    $core = new FieldTypeMapCore(__DIR__, 'default', ['field_address' => 'address']);
    $core->registerFieldHandler('address', CustomFieldHandler::class);

    $handler = $core->getFieldHandler(new EntityStub('node'), 'node', 'field_address');

    $this->assertInstanceOf(CustomFieldHandler::class, $handler);
  }

  public function testUnknownFieldTypeFallsBackToDefaultHandler(): void {
    $core = new FieldTypeMapCore(__DIR__, 'default', ['field_x' => 'nonexistent_type']);

    $handler = $core->getFieldHandler(new EntityStub('node'), 'node', 'field_x');

    $this->assertInstanceOf(DefaultHandler::class, $handler);
  }

  public function testThrowsWhenFieldIsMissing(): void {
    $core = new FieldTypeMapCore(__DIR__, 'default', []);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessageMatches('/Field "field_missing" not found/');

    $core->getFieldHandler(new EntityStub('node'), 'node', 'field_missing');
  }

  /**
   * Tests that registering a non-handler class throws at registration time.
   *
   * Failing at registration surfaces a consumer typo at test bootstrap
   * rather than when the affected field is first expanded.
   */
  public function testRegisterRejectsNonHandlerClass(): void {
    $core = new FieldTypeMapCore(__DIR__, 'default', []);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessageMatches('/must implement/');

    $core->registerFieldHandler('phone', \stdClass::class);
  }

  /**
   * Tests that registering an abstract handler class throws at registration.
   *
   * 'AbstractHandler' satisfies 'is_subclass_of(... FieldHandlerInterface)'
   * but cannot be instantiated, so 'getFieldHandler()' would fatal with
   * 'Cannot instantiate abstract class' at the first call.
   * 'CoreInterface::registerFieldHandler()' documents rejection at
   * registration time.
   */
  public function testRegisterRejectsAbstractHandlerClass(): void {
    $core = new FieldTypeMapCore(__DIR__, 'default', []);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessageMatches('/must be instantiable/');

    $core->registerFieldHandler('phone', AbstractHandler::class);
  }

  /**
   * Sets up a minimal Drupal container satisfying AbstractHandler construction.
   *
   * AbstractHandler's constructor reads the entity field manager and the
   * entity type manager from '\Drupal'; tests instantiate handlers through
   * the registry, so both services must resolve. Storage and field
   * definitions are stubbed loosely because no test depends on their shape.
   */
  protected function setUpDrupalContainer(): void {
    $field_definition = $this->createMock(FieldDefinitionInterface::class);
    $field_definition->method('getSettings')->willReturn([]);

    $storage_definition = $this->createMock(FieldStorageDefinitionInterface::class);
    $storage_definition->method('getType')->willReturn('string');
    // Core's classifier gate reads the property definitions when a field type
    // falls back to 'DefaultHandler'; a scalar with no properties stays
    // default-expandable.
    $storage_definition->method('getPropertyDefinitions')->willReturn([]);

    $entity_field_manager = $this->createMock(EntityFieldManagerInterface::class);
    $entity_field_manager->method('getFieldStorageDefinitions')
      ->willReturn([
        'field_address' => $storage_definition,
        'field_x' => $storage_definition,
      ]);
    $entity_field_manager->method('getFieldDefinitions')
      ->willReturn([
        'field_address' => $field_definition,
        'field_x' => $field_definition,
      ]);

    $entity_type = $this->createMock(EntityTypeInterface::class);
    $entity_type->method('getKey')->with('bundle')->willReturn('type');

    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getDefinition')->willReturn($entity_type);

    $container = new ContainerBuilder();
    $container->set('entity_field.manager', $entity_field_manager);
    $container->set('entity_type.manager', $entity_type_manager);
    \Drupal::setContainer($container);
  }

}

/**
 * Test Core subclass returning a caller-supplied field-type map.
 *
 * Stubs only 'getEntityFieldTypes()' so the tests drive
 * 'getFieldHandler()' without a real Drupal bootstrap. Everything else
 * comes from 'Core', including the default field-handler registration
 * the constructor performs.
 */
class FieldTypeMapCore extends Core {

  /**
   * Constructs a Core instance that returns a supplied field-type map.
   *
   * @param string $drupal_root
   *   Drupal root directory.
   * @param string $uri
   *   Site URI.
   * @param array<string, string> $fieldTypeMap
   *   Map of field name to field type id.
   */
  public function __construct(string $drupal_root, string $uri, protected array $fieldTypeMap) {
    parent::__construct($drupal_root, $uri);
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityFieldTypes(string $entity_type, ?string $bundle = NULL): array {
    return $this->fieldTypeMap;
  }

}

/**
 * Test handler used to verify consumer registrations override defaults.
 */
class CustomFieldHandler extends AbstractHandler {

  /**
   * {@inheritdoc}
   */
  protected function doExpand(array $records): array {
    return $records;
  }

}
