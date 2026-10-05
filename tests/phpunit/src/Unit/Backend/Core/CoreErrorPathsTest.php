<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Core;

use DrevOps\BehatSteps\Backend\Core\Core;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Backend\Exception\BootstrapException;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests for standalone error branches on 'Core' that need no Drupal kernel.
 */
#[CoversClass(Core::class)]
#[Group('core')]
class CoreErrorPathsTest extends TestCase {

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

  /**
   * Tests that 'deleteEntity()' rejects a stub whose id key holds no usable id.
   *
   * @param mixed $id
   *   The value stored under the entity type's id key.
   */
  #[DataProvider('dataProviderDeleteEntityRejectsEmptyId')]
  public function testDeleteEntityRejectsEmptyId(mixed $id): void {
    $core = $this->createCore();
    $this->setUpEntityTypeManager('widget', 'id');

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessageMatches('/Cannot delete an entity of type "widget" from a stub with an empty id key "id"/');

    $core->deleteEntity(new EntityStub('widget', NULL, ['id' => $id]));
  }

  public static function dataProviderDeleteEntityRejectsEmptyId(): \Iterator {
    yield 'empty string' => [''];
    yield 'null' => [NULL];
    yield 'array' => [[]];
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
   * Installs a container serving one mocked, bundle-less entity type.
   *
   * @param string $entity_type
   *   The entity type id the mocked definition answers for.
   * @param string|false $id_key
   *   What 'getKey("id")' returns, mirroring Drupal's 'string|false' contract.
   */
  protected function setUpEntityTypeManager(string $entity_type, string|false $id_key): void {
    $definition = $this->createMock(EntityTypeInterface::class);
    $definition->method('getKey')->willReturnMap([['bundle', FALSE], ['id', $id_key]]);

    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getDefinition')->with($entity_type)->willReturn($definition);

    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $entity_type_manager);
    \Drupal::setContainer($container);
  }

}
