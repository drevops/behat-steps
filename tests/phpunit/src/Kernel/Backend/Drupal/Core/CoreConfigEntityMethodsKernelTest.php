<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Drupal\Core;

use DrevOps\BehatSteps\Backend\Drupal\Core\Core;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use Drupal\KernelTests\KernelTestBase;
use Drupal\system\Entity\Menu;
use Drupal\system\MenuInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for the config entity methods on Core.
 *
 * The 'menu' config entity of the system module stands in for any config
 * entity type. An override set through '$GLOBALS['config']' acts as a
 * 'settings.php' override would.
 */
#[CoversClass(Core::class)]
#[Group('core')]
#[RunTestsInSeparateProcesses]
class CoreConfigEntityMethodsKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = ['system', 'user'];

  /**
   * The Core backend under test.
   */
  protected Core $core;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->core = new Core($this->root);
  }

  public function testCreateConfigEntitySavesTheEntity(): void {
    $stub = new EntityStub('menu', NULL, ['id' => 'footer', 'label' => 'Footer', 'description' => 'Links in the footer']);

    $created = $this->core->createConfigEntity($stub);

    $this->assertSame($stub, $created, 'createConfigEntity returns the same stub.');
    $this->assertTrue($created->isSaved(), 'createConfigEntity marks the stub saved.');
    $this->assertSame('footer', $created->getValue('id'));
    $this->assertSame('Links in the footer', $this->loadMenu('footer')->getDescription());
  }

  public function testUpdateConfigEntityWritesNoOverrideIntoTheStoredConfig(): void {
    Menu::create(['id' => 'footer', 'label' => 'Footer', 'description' => 'Stored'])->save();
    $this->overrideMenu('footer', 'label', 'Overridden');

    $stub = new EntityStub('menu', NULL, ['id' => 'footer', 'description' => 'Updated']);
    $updated = $this->core->updateConfigEntity($stub);

    $this->assertSame($stub, $updated, 'updateConfigEntity returns the same stub.');
    $this->assertTrue($updated->isSaved(), 'updateConfigEntity marks the stub saved.');

    $config = \Drupal::config('system.menu.footer');
    $this->assertSame('Updated', $config->getOriginal('description', FALSE));
    $this->assertSame('Footer', $config->getOriginal('label', FALSE), 'The override was not written into the stored configuration.');
  }

  public function testUpdateConfigEntityRejectsAnEntityThatDoesNotExist(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Cannot update the entity of type "menu" with the id "missing" because it does not exist.');

    $this->core->updateConfigEntity(new EntityStub('menu', NULL, ['id' => 'missing', 'label' => 'Missing']));
  }

  public function testDeleteConfigEntityDeletesTheEntity(): void {
    Menu::create(['id' => 'footer', 'label' => 'Footer'])->save();

    $this->core->deleteConfigEntity(new EntityStub('menu', NULL, ['id' => 'footer']));

    $this->assertNull(Menu::load('footer'));
  }

  public function testDeleteConfigEntityIgnoresAnEntityThatDoesNotExist(): void {
    Menu::create(['id' => 'footer', 'label' => 'Footer'])->save();

    $this->core->deleteConfigEntity(new EntityStub('menu', NULL, ['id' => 'missing']));

    $this->assertInstanceOf(MenuInterface::class, Menu::load('footer'));
  }

  public function testDeleteConfigEntityRejectsStubWithoutId(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Cannot resolve an entity of type "menu" from a stub without the id key "id" set.');

    $this->core->deleteConfigEntity(new EntityStub('menu', NULL, ['label' => 'Footer']));
  }

  public function testDuplicateConfigEntityCopiesTheStoredEntity(): void {
    Menu::create(['id' => 'footer', 'label' => 'Footer', 'description' => 'Stored'])->save();
    $this->overrideMenu('footer', 'description', 'Overridden');

    $copy = $this->core->duplicateConfigEntity(new EntityStub('menu', NULL, ['id' => 'footer']), ['id' => 'footer_copy', 'label' => 'Footer copy']);

    $this->assertTrue($copy->isSaved(), 'duplicateConfigEntity marks the stub of the copy saved.');
    $this->assertSame(['id' => 'footer_copy', 'label' => 'Footer copy'], $copy->getValues());

    $menu = $this->loadMenu('footer_copy');
    $this->assertSame('Footer copy', $menu->label());
    $this->assertSame('Stored', $menu->getDescription(), 'The copy takes the stored value, not the override of its source.');
    $this->assertSame('Footer', $this->loadMenu('footer')->label(), 'The source is left as it was.');
  }

  /**
   * Tests that a copy is refused without a source or a new id.
   *
   * @param array<string, mixed> $source
   *   The values of the source stub.
   * @param array<string, mixed> $overrides
   *   The overrides for the copy.
   * @param string $expected_message
   *   The message the copy throws.
   */
  #[DataProvider('dataProviderDuplicateConfigEntityFails')]
  public function testDuplicateConfigEntityFails(array $source, array $overrides, string $expected_message): void {
    Menu::create(['id' => 'footer', 'label' => 'Footer'])->save();

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage($expected_message);

    $this->core->duplicateConfigEntity(new EntityStub('menu', NULL, $source), $overrides);
  }

  public static function dataProviderDuplicateConfigEntityFails(): \Iterator {
    yield 'a source that does not exist' => [['id' => 'missing'], ['id' => 'copy'], 'Cannot duplicate the entity of type "menu" with the id "missing" because it does not exist.'];
    yield 'no new id' => [['id' => 'footer'], ['label' => 'Copy'], 'Cannot duplicate the entity of type "menu" with the id "footer" without a new id under "id".'];
    yield 'an empty new id' => [['id' => 'footer'], ['id' => ''], 'Cannot duplicate the entity of type "menu" with the id "footer" without a new id under "id".'];
    yield 'a source without an id' => [['label' => 'Footer'], ['id' => 'copy'], 'Cannot resolve an entity of type "menu" from a stub without the id key "id" set.'];
  }

  public function testConfigEntityMethodsRejectContentEntityType(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Entity type "user" is not a config entity type.');

    $this->core->createConfigEntity(new EntityStub('user', NULL, ['name' => 'jane']));
  }

  /**
   * Overrides a property of a menu as 'settings.php' would.
   *
   * @param string $id
   *   The menu ID.
   * @param string $key
   *   The property to override.
   * @param string $value
   *   The value the override sets.
   */
  protected function overrideMenu(string $id, string $key, string $value): void {
    $GLOBALS['config']['system.menu.' . $id][$key] = $value;
    \Drupal::configFactory()->reset('system.menu.' . $id);

    $this->assertSame($value, \Drupal::config('system.menu.' . $id)->get($key), 'The override is in effect.');
  }

  /**
   * Loads a menu.
   *
   * @param string $id
   *   The menu ID.
   */
  protected function loadMenu(string $id): MenuInterface {
    $menu = Menu::load($id);
    $this->assertInstanceOf(MenuInterface::class, $menu);

    return $menu;
  }

}
