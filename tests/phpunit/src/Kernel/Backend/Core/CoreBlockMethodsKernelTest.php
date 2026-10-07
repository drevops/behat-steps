<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Core;

use DrevOps\BehatSteps\Backend\Core\Core;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use Drupal\block\Entity\Block;
use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for the block capability methods on Core.
 *
 * Covers all 4 methods of 'BlockCapabilityInterface':
 *  - 'placeBlock()' / 'deleteBlock()' round-trip a 'block' config entity
 *    (placement in a region of a theme).
 *  - 'createBlockContent()' / 'deleteBlockContent()' round-trip a
 *    'block_content' content entity (the reusable block body).
 */
#[CoversClass(Core::class)]
#[Group('core')]
#[Group('block')]
#[RunTestsInSeparateProcesses]
class CoreBlockMethodsKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = ['system', 'user', 'block', 'block_content'];

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
    $this->installEntitySchema('block_content');
    // Installing 'block_content' config on Drupal 11-lowest pulls in
    // 'field.storage.block_content.body', whose schema references the 'text'
    // module. The test creates its own body-less 'block_content_type' inline,
    // so only 'system' config is installed.
    $this->installConfig(['system']);
    \Drupal::service('theme_installer')->install(['stark']);
    $this->core = new Core($this->root);
  }

  public function testPlaceBlockAndDeleteRoundTrip(): void {
    $stub = new EntityStub('block', NULL, [
      'id' => 'test_powered_by',
      'plugin' => 'system_powered_by_block',
      'theme' => 'stark',
      'region' => 'content',
      'weight' => 0,
      'settings' => ['label' => 'Powered by', 'label_display' => 'visible'],
    ]);

    $created = $this->core->placeBlock($stub);

    $this->assertSame($stub, $created);
    $this->assertTrue($created->isSaved());
    $this->assertInstanceOf(Block::class, $created->getSavedEntity());

    $reloaded = Block::load('test_powered_by');
    $this->assertInstanceOf(Block::class, $reloaded);
    $this->assertSame('content', $reloaded->getRegion());
    $this->assertSame('stark', $reloaded->getTheme());
    $this->assertSame('system_powered_by_block', $reloaded->getPluginId());

    $this->core->deleteBlock($created);
    $this->assertNull(Block::load('test_powered_by'));
  }

  public function testPlaceBlockGeneratesIdWhenAbsent(): void {
    $stub = new EntityStub('block', NULL, [
      'plugin' => 'system_powered_by_block',
      'theme' => 'stark',
      'region' => 'footer',
    ]);

    $created = $this->core->placeBlock($stub);

    $this->assertTrue($created->isSaved());
    $placement = $created->getSavedEntity();
    $this->assertInstanceOf(Block::class, $placement);
    $this->assertNotEmpty($placement->id(), 'placeBlock populated an id on the saved placement.');
    $this->assertNotNull(Block::load($placement->id()));
  }

  public function testDeleteBlockUsesSavedEntity(): void {
    $stub = new EntityStub('block', NULL, [
      'id' => 'test_via_entity',
      'plugin' => 'system_powered_by_block',
      'theme' => 'stark',
      'region' => 'content',
    ]);
    $this->core->placeBlock($stub);

    $this->assertNotNull(Block::load('test_via_entity'));
    $this->core->deleteBlock($stub);
    $this->assertNull(Block::load('test_via_entity'));
  }

  public function testDeleteBlockRequiresIdOnStub(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessageMatches('/id/');

    $this->core->deleteBlock(new EntityStub('block', NULL, ['plugin' => 'system_powered_by_block']));
  }

  public function testCreateBlockContentAndDeleteRoundTrip(): void {
    BlockContentType::create(['id' => 'basic', 'label' => 'Basic'])->save();

    $stub = new EntityStub('block_content', 'basic', [
      'info' => 'backend-test content block',
      'reusable' => TRUE,
    ]);

    $created = $this->core->createBlockContent($stub);

    $this->assertSame($stub, $created);
    $this->assertTrue($created->isSaved());
    $this->assertInstanceOf(BlockContent::class, $created->getSavedEntity());
    $this->assertNotEmpty($stub->getValue('id'), 'createBlockContent populated the id key on the stub.');
    $this->assertSame('backend-test content block', $created->getSavedEntity()->label());

    $this->core->deleteBlockContent($stub);
    $this->assertNull(BlockContent::load((int) $stub->getValue('id')));
  }

}
