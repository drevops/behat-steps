<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Drupal\Core;

use DrevOps\BehatSteps\Backend\Drupal\Core\Core;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Backend\Exception\BootstrapException;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for standalone error branches on 'Core' that need no Drupal kernel.
 */
#[CoversClass(Core::class)]
#[Group('core')]
class CoreErrorPathsTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    \Drupal::unsetContainer();
    parent::tearDown();
  }

  public function testConstructorThrowsWhenRootUnresolvable(): void {
    $this->expectException(BootstrapException::class);
    $this->expectExceptionMessageMatches('/Could not resolve Drupal root/');

    new Core('/absolutely/not/a/real/path/for/behat-steps-tests');
  }

  /**
   * Tests that 'createEntity()' rejects an empty entity type before booting.
   *
   * The throw happens before any 'Drupal::service()' call, so no kernel is
   * needed.
   */
  public function testCreateEntityRejectsEmptyEntityType(): void {
    $core = $this->createCore();

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessageMatches('/You must specify an entity type/');

    $core->createEntity(new EntityStub(''));
  }

  public function testResolveUidThrowsWhenStubHasNoId(): void {
    $core = $this->createCore();
    $reflection = new \ReflectionMethod($core, 'resolveUid');

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessageMatches('/Cannot resolve a user id from the stub/');

    $reflection->invoke($core, new EntityStub('user'));
  }

  /**
   * Tests that the language methods reject a stub without a usable langcode.
   *
   * 'resolveLangcode()' runs before any storage call, so both public entry
   * points fail without a kernel.
   *
   * @param string $method
   *   The 'Core' method to call.
   * @param array<string, mixed> $values
   *   Values to seed the language stub with.
   */
  #[DataProvider('dataProviderLanguageMethodsRejectMissingLangcode')]
  public function testLanguageMethodsRejectMissingLangcode(string $method, array $values): void {
    $core = $this->createCore();

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessageMatches('/Cannot operate on a language without a non-empty "langcode" value/');

    $core->{$method}(new EntityStub('language', NULL, $values));
  }

  public static function dataProviderLanguageMethodsRejectMissingLangcode(): \Iterator {
    yield 'create without langcode' => ['createLanguage', []];
    yield 'create with empty langcode' => ['createLanguage', ['langcode' => '']];
    yield 'delete without langcode' => ['deleteLanguage', []];
    yield 'delete with non-string langcode' => ['deleteLanguage', ['langcode' => 123]];
  }

  /**
   * Tests that 'createEntity()' rejects an entity type declaring no id key.
   *
   * Drupal ships no entity type without an id key, so the definition is
   * mocked.
   */
  public function testCreateEntityRejectsEntityTypeWithoutIdKey(): void {
    $core = $this->createCore();
    $this->setUpEntityTypeManager('widget', FALSE);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessageMatches('/Cannot create an entity of type "widget" because it declares no id key/');

    $core->createEntity(new EntityStub('widget'));
  }

  public function testGetEntityIdKeyRejectsEntityTypeWithoutIdKey(): void {
    $core = $this->createCore();
    $this->setUpEntityTypeManager('widget', FALSE);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The entity type "widget" declares no id key.');

    $core->getEntityIdKey('widget');
  }

  /**
   * Tests that a stub method rejects a stub whose id key holds no usable id.
   *
   * @param string $method
   *   The 'Core' method to call with the stub.
   * @param mixed $id
   *   The value stored under the entity type's id key.
   */
  #[DataProvider('dataProviderStubMethodRejectsEmptyId')]
  public function testStubMethodRejectsEmptyId(string $method, mixed $id): void {
    $core = $this->createCore();
    $this->setUpEntityTypeManager('widget', 'id', TRUE);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessageMatches('/Cannot resolve an entity of type "widget" from a stub with an empty id key "id"/');

    $core->{$method}(new EntityStub('widget', NULL, ['id' => $id]));
  }

  public static function dataProviderStubMethodRejectsEmptyId(): \Iterator {
    foreach (['deleteEntity', 'getEntityUrl', 'updateEntity'] as $method) {
      yield $method . ' with an empty string' => [$method, ''];
      yield $method . ' with null' => [$method, NULL];
      yield $method . ' with an array' => [$method, []];
    }
  }

  /**
   * Tests that a stub method rejects a stub that carries no id key.
   *
   * @param string $method
   *   The 'Core' method to call with the stub.
   */
  #[DataProvider('dataProviderStubMethodRejectsMissingIdKey')]
  public function testStubMethodRejectsMissingIdKey(string $method): void {
    $core = $this->createCore();
    $this->setUpEntityTypeManager('widget', 'id', TRUE);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Cannot resolve an entity of type "widget" from a stub without the id key "id" set.');

    $core->{$method}(new EntityStub('widget', NULL, ['name' => 'A name']));
  }

  public static function dataProviderStubMethodRejectsMissingIdKey(): \Iterator {
    yield 'deleteEntity' => ['deleteEntity'];
    yield 'getEntityUrl' => ['getEntityUrl'];
    yield 'updateEntity' => ['updateEntity'];
  }

  /**
   * Tests that a typed delete rejects a stub that carries no id.
   *
   * @param string $method
   *   The 'Core' delete method to call.
   * @param string $entity_type
   *   The entity type of the stub.
   * @param string $expected_message
   *   The message the delete throws.
   */
  #[DataProvider('dataProviderTypedDeleteRejectsStubWithoutId')]
  public function testTypedDeleteRejectsStubWithoutId(string $method, string $entity_type, string $expected_message): void {
    $core = $this->createCore();

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage($expected_message);

    $core->{$method}(new EntityStub($entity_type));
  }

  public static function dataProviderTypedDeleteRejectsStubWithoutId(): \Iterator {
    yield 'node' => ['deleteNode', 'node', 'Cannot resolve a node id from the stub: neither the saved entity nor a "nid" value is set.'];
    yield 'term' => ['deleteTerm', 'taxonomy_term', 'Cannot resolve a term id from the stub: neither the saved entity nor a "tid" value is set.'];
  }

  /**
   * Tests that the content methods reject an entity type without fields.
   *
   * @param string $method
   *   The 'Core' method to call.
   * @param array<int, mixed> $args
   *   The arguments to call it with.
   * @param string $expected_message
   *   The message the method throws.
   */
  #[DataProvider('dataProviderContentMethodsRejectEntityTypeWithoutFields')]
  public function testContentMethodsRejectEntityTypeWithoutFields(string $method, array $args, string $expected_message): void {
    $core = $this->createCore();
    $this->setUpEntityTypeManager('widget', 'id');

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage($expected_message);

    $core->{$method}(...$args);
  }

  public static function dataProviderContentMethodsRejectEntityTypeWithoutFields(): \Iterator {
    yield 'updateEntity' => ['updateEntity', [new EntityStub('widget', NULL, ['id' => 'a'])], 'Cannot update an entity of type "widget" because it is not a content entity type.'];
    yield 'getEntityValues' => ['getEntityValues', ['widget', 'a', ['label']], 'Cannot read the values of an entity of type "widget" because it is not a content entity type.'];
  }

  /**
   * Tests that the config entity methods reject a type stored otherwise.
   *
   * @param string $method
   *   The 'Core' method to call.
   * @param array<int, mixed> $args
   *   The arguments to call it with.
   */
  #[DataProvider('dataProviderConfigEntityMethodsRejectOtherEntityTypes')]
  public function testConfigEntityMethodsRejectOtherEntityTypes(string $method, array $args): void {
    $core = $this->createCore();
    $this->setUpEntityTypeManager('widget', 'id', TRUE);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Entity type "widget" is not a config entity type.');

    $core->{$method}(...$args);
  }

  public static function dataProviderConfigEntityMethodsRejectOtherEntityTypes(): \Iterator {
    $stub = new EntityStub('widget', NULL, ['id' => 'a']);

    yield 'createConfigEntity' => ['createConfigEntity', [$stub]];
    yield 'updateConfigEntity' => ['updateConfigEntity', [$stub]];
    yield 'deleteConfigEntity' => ['deleteConfigEntity', [$stub]];
    yield 'duplicateConfigEntity' => ['duplicateConfigEntity', [$stub, ['id' => 'b']]];
  }

  /**
   * Builds a 'Core' instance pointed at a valid path.
   *
   * None of the error paths under test reach the filesystem, so any existing
   * directory serves as the root.
   */
  protected function createCore(): Core {
    return new Core(__DIR__);
  }

  /**
   * Installs a container serving 1 mocked, bundle-less entity type.
   *
   * @param string $entity_type
   *   The entity type id the mocked definition answers for.
   * @param string|false $id_key
   *   What 'getKey("id")' returns, mirroring Drupal's 'string|false' contract.
   * @param bool $is_fieldable
   *   Whether the entity class of the type has fields, as a content entity
   *   class does.
   */
  protected function setUpEntityTypeManager(string $entity_type, string|false $id_key, bool $is_fieldable = FALSE): void {
    $definition = $this->createMock(EntityTypeInterface::class);
    $definition->method('getKey')->willReturnMap([['bundle', FALSE], ['id', $id_key]]);
    $definition->method('entityClassImplements')->willReturn($is_fieldable);

    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getDefinition')->with($entity_type)->willReturn($definition);

    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $entity_type_manager);
    \Drupal::setContainer($container);
  }

}
