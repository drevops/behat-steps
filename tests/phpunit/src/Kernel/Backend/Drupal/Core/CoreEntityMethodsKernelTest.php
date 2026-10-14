<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Drupal\Core;

use DrevOps\BehatSteps\Backend\Drupal\Core\Core;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\entity_test\EntityTestHelper;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\path_alias\Entity\PathAlias;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for generic entity methods on Core via the backend.
 *
 * Covers 'createEntity()', 'updateEntity()', 'getEntityValues()' and
 * 'deleteEntity()' (both the stub-object branch and the loaded-entity
 * branch). Base-field expansion is exercised implicitly by any create or
 * update whose stub sets a base field.
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
  protected static $modules = ['system', 'user', 'entity_test', 'field', 'options', 'path_alias'];

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
    $this->installEntitySchema('path_alias');
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

    // Deleting via the stub triggers the load-by-id branch of deleteEntity(),
    // resolved against the entity type id key.
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

  public function testGetEntityUrlReturnsThePathOfTheLink(): void {
    $created = $this->core->createEntity(new EntityStub('entity_test', NULL, ['name' => 'linked']));
    $id = $created->getValue('id');

    $this->assertSame('/entity_test/' . $id, $this->core->getEntityUrl(new EntityStub('entity_test', NULL, ['id' => $id])));
    $this->assertSame('/entity_test/manage/' . $id . '/edit', $this->core->getEntityUrl($created, 'edit-form'));
  }

  public function testGetEntityUrlRejectsMissingEntity(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('No entity of type "entity_test" with the id "999" exists.');

    $this->core->getEntityUrl(new EntityStub('entity_test', NULL, ['id' => 999]));
  }

  public function testGetEntityUrlLeavesThePathAliasOut(): void {
    $created = $this->core->createEntity(new EntityStub('entity_test', NULL, ['name' => 'aliased']));
    $path = '/entity_test/' . $created->getValue('id');
    PathAlias::create(['path' => $path, 'alias' => '/aliased'])->save();

    $this->assertSame('/aliased', \Drupal::service('path_alias.repository')->lookupBySystemPath($path, 'en')['alias'] ?? NULL, 'An alias exists for the path of the entity.');
    $this->assertSame($path, $this->core->getEntityUrl($created));
  }

  public function testGetEntityUrlLeavesTheBasePathOut(): void {
    $created = $this->core->createEntity(new EntityStub('entity_test', NULL, ['name' => 'subdirectory']));
    \Drupal::service('router.request_context')->setBaseUrl('/subdirectory');

    $entity = $created->getSavedEntity();
    $this->assertInstanceOf(EntityInterface::class, $entity);
    $this->assertStringStartsWith('/subdirectory/', $entity->toUrl()->toString(), 'The site answers in a subdirectory.');
    $this->assertSame('/entity_test/' . $created->getValue('id'), $this->core->getEntityUrl($created));
  }

  public function testGetEntityBundleReadsTheBundleOfTheEntity(): void {
    EntityTestHelper::createBundle('custom_bundle');
    $created = $this->core->createEntity(new EntityStub('entity_test', 'custom_bundle', ['name' => 'bundled']));

    $this->assertSame('custom_bundle', $this->core->getEntityBundle(new EntityStub('entity_test', NULL, ['id' => $created->getValue('id')])));
    $this->assertSame('custom_bundle', $this->core->getEntityBundle($created));
  }

  public function testGetEntityBundleReturnsTheEntityTypeForTypeWithoutBundles(): void {
    $account = $this->createUser('bundle-less');

    $this->assertSame('user', $this->core->getEntityBundle(new EntityStub('user', NULL, ['uid' => $account->id()])));
  }

  public function testGetEntityBundleRejectsMissingEntity(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('No entity of type "entity_test" with the id "999" exists.');

    $this->core->getEntityBundle(new EntityStub('entity_test', NULL, ['id' => 999]));
  }

  public function testGetEntityUrlRejectsAnUnknownLink(): void {
    $created = $this->core->createEntity(new EntityStub('entity_test', NULL, ['name' => 'linked']));

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The entity type "entity_test" has no "bogus" link.');

    $this->core->getEntityUrl($created, 'bogus');
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

  public function testUpdateEntitySetsTheValuesThroughTheHandlers(): void {
    Role::create(['id' => 'editor', 'label' => 'Editor'])->save();
    $account = $this->createUser('wes');

    $stub = new EntityStub('user', NULL, ['uid' => $account->id(), 'status' => '0', 'roles' => ['Editor']]);
    $updated = $this->core->updateEntity($stub);

    $this->assertSame($stub, $updated, 'updateEntity returns the same stub.');
    $this->assertTrue($updated->isSaved(), 'updateEntity marks the stub saved.');
    $this->assertSame([['target_id' => 'editor']], $stub->getValue('roles'), 'The stub holds the expanded values.');

    $reloaded = $this->reloadUser((int) $account->id());
    $this->assertFalse($reloaded->isActive());
    $this->assertContains('editor', $reloaded->getRoles(), 'The role label was resolved to its id.');
    $this->assertSame('wes', $reloaded->getAccountName(), 'A field the stub does not carry keeps its value.');
  }

  /**
   * Tests that a stub without a bundle expands against the loaded entity.
   *
   * A configurable field exists on 1 bundle only, so the handler that turns
   * the allowed-value label into its key runs only when the bundle is known.
   */
  public function testUpdateEntityExpandsAgainstTheBundleOfTheLoadedEntity(): void {
    EntityTestHelper::createBundle('custom_bundle');
    FieldStorageConfig::create(['field_name' => 'field_level', 'entity_type' => 'entity_test', 'type' => 'list_string', 'settings' => ['allowed_values' => ['low' => 'Low', 'high' => 'High']]])->save();
    FieldConfig::create(['field_name' => 'field_level', 'entity_type' => 'entity_test', 'bundle' => 'custom_bundle'])->save();

    $entity = \Drupal::entityTypeManager()->getStorage('entity_test')->create(['type' => 'custom_bundle', 'name' => 'Level']);
    $entity->save();

    $this->core->updateEntity(new EntityStub('entity_test', NULL, ['id' => $entity->id(), 'type' => 'other_bundle', 'field_level' => 'High']));

    $reloaded = \Drupal::entityTypeManager()->getStorage('entity_test')->loadUnchanged($entity->id());
    $this->assertInstanceOf(ContentEntityInterface::class, $reloaded);
    $this->assertSame('high', $reloaded->get('field_level')->value);
    $this->assertSame('custom_bundle', $reloaded->bundle(), 'The bundle key identifies the entity, so it is not written.');
  }

  public function testUpdateEntityUsesTheIdOfSavedStub(): void {
    $account = $this->createUser('yan');

    $this->core->updateEntity((new EntityStub('user'))->markSaved($account)->setValue('mail', 'renamed@example.com'));

    $this->assertSame('renamed@example.com', $this->reloadUser((int) $account->id())->getEmail());
  }

  public function testUpdateEntityRejectsAnEntityThatDoesNotExist(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Cannot update the entity of type "user" with the id "999" because it does not exist.');

    $this->core->updateEntity(new EntityStub('user', NULL, ['uid' => 999, 'name' => 'ghost']));
  }

  public function testUpdateEntityRejectsAnUnknownFieldAndChangesNothing(): void {
    $account = $this->createUser('xan');

    try {
      $this->core->updateEntity(new EntityStub('user', NULL, ['uid' => $account->id(), 'name' => 'renamed', 'field_missing' => 'value']));
      $this->fail('updateEntity accepted a field the entity does not have.');
    }
    catch (\RuntimeException $exception) {
      $this->assertSame(sprintf('Cannot update the entity of type "user" with the id "%s" because it has no field "field_missing".', $account->id()), $exception->getMessage());
    }

    $this->assertSame('xan', $this->reloadUser((int) $account->id())->getAccountName());
  }

  public function testGetEntityValuesReturnsTheStorageShape(): void {
    Role::create(['id' => 'editor', 'label' => 'Editor'])->save();
    $account = $this->createUser('zed', ['editor']);

    $values = $this->core->getEntityValues('user', $account->id(), ['name', 'roles']);

    $this->assertSame(['name', 'roles'], array_keys($values));
    $this->assertSame('zed', $values['name'][0]['value']);
    $this->assertSame('editor', $values['roles'][0]['target_id']);
  }

  public function testGetEntityValuesReturnsAnEmptyArrayForAnEntityThatDoesNotExist(): void {
    $this->assertSame([], $this->core->getEntityValues('user', 999, ['name']));
  }

  public function testGetEntityValuesRejectsAnUnknownField(): void {
    $account = $this->createUser('ann');

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage(sprintf('Cannot read the entity of type "user" with the id "%s" because it has no field "field_missing".', $account->id()));

    $this->core->getEntityValues('user', $account->id(), ['name', 'field_missing']);
  }

  /**
   * Creates an active user.
   *
   * @param string $name
   *   The user name.
   * @param array<int, string> $roles
   *   The role IDs.
   */
  protected function createUser(string $name, array $roles = []): User {
    $account = User::create(['name' => $name, 'mail' => $name . '@example.com', 'status' => 1, 'roles' => $roles]);
    $account->save();

    return $account;
  }

  /**
   * Loads a user again, bypassing the static cache.
   *
   * @param int $uid
   *   The user ID.
   */
  protected function reloadUser(int $uid): User {
    $account = \Drupal::entityTypeManager()->getStorage('user')->loadUnchanged($uid);
    $this->assertInstanceOf(User::class, $account);

    return $account;
  }

}
