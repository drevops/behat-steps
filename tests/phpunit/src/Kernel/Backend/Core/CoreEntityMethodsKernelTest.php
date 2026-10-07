<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Core;

use DrevOps\BehatSteps\Backend\Core\Core;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use Drupal\Core\Entity\EntityInterface;
use Drupal\entity_test\EntityTestHelper;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for generic entity methods on Core via the backend.
 *
 * Covers 'createEntity()' and 'deleteEntity()' (both the stub-object branch
 * and the loaded-entity branch). Base-field expansion is exercised
 * implicitly by any 'createEntity()' call whose stub sets a base field.
 */
#[CoversClass(Core::class)]
#[Group('core')]
#[RunTestsInSeparateProcesses]
class CoreEntityMethodsKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = ['system', 'user', 'entity_test'];

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
    $this->installSchema('user', ['users_data']);
    $this->installConfig(['system', 'user']);
    $this->core = new Core($this->root);
  }

  /**
   * Tests 'createEntity()' followed by 'deleteEntity()' using a stub object.
   *
   * The user entity type's id key is 'uid', so createEntity should populate
   * the stub under 'uid' (not the generic 'id' property). deleteEntity should
   * load by that same key.
   *
   * createNode/deleteNode (nid), createUser (uid) and createTerm/deleteTerm
   * (tid) follow the same convention.
   */
  public function testCreateEntityAndDeleteWithStub(): void {
    $stub = new EntityStub('user', NULL, [
      'name' => 'zoe',
      'mail' => 'zoe@example.com',
      'status' => 1,
    ]);

    $created = $this->core->createEntity($stub);

    $this->assertSame($stub, $created, 'createEntity returns the same stub.');
    $this->assertTrue($created->isSaved(), 'createEntity marks the stub saved.');
    $this->assertInstanceOf(EntityInterface::class, $created->getSavedEntity());
    $this->assertNotEmpty($stub->getValue('uid'), 'createEntity populated the entity type id key (uid) on the stub.');
    $this->assertFalse($stub->hasValue('id'), 'createEntity did not populate a generic "id" value on the stub.');

    // Delete via the stub, which triggers the load-by-id branch of
    // deleteEntity() resolved against the entity type id key.
    $this->core->deleteEntity($stub);

    $this->assertNull(User::load((int) $stub->getValue('uid')));
  }

  /**
   * Tests 'createEntity()' auto-expands base fields set on the stub.
   *
   * 'name' is a base field on the user entity type. Base fields are not
   * registered field storage configs, so the handler pipeline reaches them
   * only through auto-detection.
   *
   * DefaultHandler wraps the scalar value into the array form the field API
   * expects, so the stub holds that array after create.
   */
  public function testCreateEntityAutoExpandsBaseFieldsSetOnStub(): void {
    $stub = new EntityStub('user', NULL, [
      'name' => 'uma',
      'mail' => 'uma@example.com',
      'status' => 1,
    ]);

    $this->core->createEntity($stub);

    $this->assertSame([['value' => 'uma']], $stub->getValue('name'), 'base field "name" was routed through the handler pipeline.');
  }

  public function testDeleteEntityRejectsStubMissingIdKey(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessageMatches('/stub without the id key "uid" set/');

    $this->core->deleteEntity(new EntityStub('user', NULL, ['name' => 'missing-uid']));
  }

  /**
   * Tests base entity-reference fields round-trip through createEntity().
   *
   * 'user.roles' is a base entity_reference field targeting the user_role
   * config entity type. A stub sets it by label or id, and the backend must
   * resolve and attach the reference.
   */
  public function testCreateEntityExpandsBaseEntityReferenceFieldOnStub(): void {
    Role::create(['id' => 'editor', 'label' => 'Editor'])->save();

    $stub = new EntityStub('user', NULL, [
      'name' => 'vic',
      'mail' => 'vic@example.com',
      'status' => 1,
      'roles' => ['editor'],
    ]);

    $this->core->createEntity($stub);

    $account = User::load((int) $stub->getValue('uid'));
    $this->assertInstanceOf(User::class, $account);
    $this->assertContains('editor', $account->getRoles(), 'createEntity routed user.roles through EntityReferenceHandler for base-field expansion.');
  }

  public function testDeleteEntityUsesSavedEntity(): void {
    $entity = User::create([
      'name' => 'taylor',
      'mail' => 'taylor@example.com',
      'status' => 1,
    ]);
    $entity->save();

    $stub = (new EntityStub('user'))->markSaved($entity);
    $this->core->deleteEntity($stub);

    $this->assertNull(User::load((int) $entity->id()));
  }

  /**
   * Tests 'createEntity()' promotes the typed bundle onto the bundle key.
   *
   * 'entity_test' has a 'type' bundle key, so the typed 'bundle' constructor
   * argument should be promoted to 'type' before the entity is saved.
   */
  public function testCreateEntityPromotesTypedBundle(): void {
    EntityTestHelper::createBundle('custom_bundle');

    $stub = new EntityStub('entity_test', 'custom_bundle', ['name' => 'sam']);
    $created = $this->core->createEntity($stub);

    $this->assertSame('custom_bundle', $stub->getValue('type'), 'typed bundle was promoted to the bundle key.');

    $saved = $created->getSavedEntity();
    $this->assertInstanceOf(EntityInterface::class, $saved);
    $this->assertSame('custom_bundle', $saved->bundle());
  }

  /**
   * Tests 'createEntity()' rejects an unknown entity type with a clear message.
   *
   * Drupal's 'EntityTypeManager::getDefinition()' raises a
   * 'PluginNotFoundException' whose message uses plugin-system terms and does
   * not describe the scenario author's mistake. The backend wraps it as a
   * 'RuntimeException' that names the offending entity type.
   */
  public function testCreateEntityRejectsUnknownEntityType(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessageMatches('/Unknown entity type "nonexistent_type"/');

    $this->core->createEntity(new EntityStub('nonexistent_type', NULL, ['name' => 'foo']));
  }

  public function testDeleteEntityRejectsUnknownEntityType(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessageMatches('/Unknown entity type "nonexistent_type"/');

    $this->core->deleteEntity(new EntityStub('nonexistent_type', NULL, ['id' => 1]));
  }

  /**
   * Tests that 'createEntity()' rejects an unknown bundle for a bundled type.
   *
   * 'entity_test' has a 'type' bundle key but no bundles registered unless
   * explicitly created; any supplied bundle therefore triggers the guard.
   */
  public function testCreateEntityRejectsUnknownBundle(): void {
    $stub = new EntityStub('entity_test', 'not_a_real_bundle', ['name' => 'orphan']);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessageMatches('/Cannot create entity because provided bundle "not_a_real_bundle" does not exist/');

    $this->core->createEntity($stub);
  }

}
