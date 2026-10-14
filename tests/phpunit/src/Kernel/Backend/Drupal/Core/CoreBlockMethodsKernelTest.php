<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Drupal\Core;

use DrevOps\BehatSteps\Backend\Drupal\Core\Core;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use Drupal\block\Entity\Block;
use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for the block capability methods on Core.
 *
 * Blocks are placed in 'stark', which the test makes the default theme.
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
  protected static $modules = ['system', 'user', 'block', 'block_content', 'path_alias'];

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
    $this->config('system.theme')->set('default', 'stark')->save();
    $this->core = new Core($this->root);
  }

  public function testPlaceBlockAndDeleteRoundTrip(): void {
    $created = $this->core->placeBlock('system_powered_by_block', 'Powered by');

    $this->assertSame('block', $created->getEntityType());
    $this->assertTrue($created->isSaved());
    $this->assertInstanceOf(Block::class, $created->getSavedEntity());
    $this->assertSame('stark_poweredbydrupal', $created->getValue('id'));

    $placed = $this->loadBlock('stark_poweredbydrupal');
    $this->assertSame('stark', $placed->getTheme(), 'The block was placed in the default theme.');
    $this->assertSame('system_powered_by_block', $placed->getPluginId());
    $this->assertSame('Powered by', $placed->label());

    $this->core->deleteBlock($created);
    $this->assertNull(Block::load('stark_poweredbydrupal'));
  }

  public function testPlaceBlockSuffixesTakenMachineName(): void {
    $this->core->placeBlock('system_powered_by_block', 'First');

    $second = $this->core->placeBlock('system_powered_by_block', 'Second');

    $this->assertSame('stark_poweredbydrupal_2', $second->getValue('id'), 'A taken suggestion gains a numeric suffix, as the block UI does.');
    $this->assertSame('Second', $this->loadBlock('stark_poweredbydrupal_2')->label());
  }

  public function testDeleteBlockLoadsTheBlockById(): void {
    $this->core->placeBlock('system_powered_by_block', 'By id');

    $this->core->deleteBlock(new EntityStub('block', NULL, ['id' => 'stark_poweredbydrupal']));

    $this->assertNull(Block::load('stark_poweredbydrupal'));
  }

  public function testDeleteBlockRequiresIdOnStub(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessageMatches('/id/');

    $this->core->deleteBlock(new EntityStub('block', NULL, ['plugin' => 'system_powered_by_block']));
  }

  public function testConfigureBlockSetsTheKnownKeys(): void {
    $created = $this->core->placeBlock('system_powered_by_block', 'Powered by');

    $this->core->configureBlock($created, ['label' => 'Renamed', 'label_display' => TRUE, 'region' => 'sidebar_first', 'status' => FALSE, 'weight' => 10]);

    $saved = $this->loadBlock((string) $created->getValue('id'));
    $this->assertSame('Renamed', $saved->get('settings')['label']);
    $this->assertSame('1', (string) $saved->get('settings')['label_display']);
    $this->assertSame('sidebar_first', $saved->getRegion());
    $this->assertFalse($saved->status());
    $this->assertSame(0, (int) $saved->getWeight(), 'A key the method does not know is ignored.');
  }

  public function testConfigureBlockKeepsWhatTheConfigurationLeavesOut(): void {
    $created = $this->core->placeBlock('system_powered_by_block', 'Powered by');
    $this->core->configureBlock($created, ['region' => 'header']);

    $this->core->configureBlock($created, ['label_display' => FALSE]);

    $saved = $this->loadBlock((string) $created->getValue('id'));
    $this->assertSame('Powered by', $saved->label(), 'The label survives a change to the label display.');
    $this->assertSame('header', $saved->getRegion());
  }

  public function testConfigureBlockWritesNoOverrideIntoTheStoredSettings(): void {
    $created = $this->core->placeBlock('system_powered_by_block', 'Powered by');
    $id = (string) $created->getValue('id');

    $GLOBALS['config']['block.block.' . $id]['settings']['label'] = 'Overridden';
    \Drupal::configFactory()->reset('block.block.' . $id);
    $this->assertSame('Overridden', \Drupal::config('block.block.' . $id)->get('settings.label'));

    $this->core->configureBlock($created, ['label_display' => TRUE]);

    $this->assertSame('Powered by', \Drupal::config('block.block.' . $id)->getOriginal('settings.label', FALSE));
  }

  public function testSetBlockVisibilityReplacesTheConfigurationOfTheCondition(): void {
    $created = $this->core->placeBlock('system_powered_by_block', 'Powered by');
    $this->core->setBlockVisibility($created, 'request_path', ['pages' => '/user/*']);

    $this->core->setBlockVisibility($created, 'request_path', ['pages' => '/node/*', 'negate' => TRUE]);

    $visibility = $this->loadBlock((string) $created->getValue('id'))->getVisibility();
    $this->assertSame('/node/*', $visibility['request_path']['pages']);
    $this->assertTrue($visibility['request_path']['negate']);
  }

  public function testUnsetBlockVisibilityRemovesOnlyTheCondition(): void {
    $created = $this->core->placeBlock('system_powered_by_block', 'Powered by');
    $this->core->setBlockVisibility($created, 'request_path', ['pages' => '/user/*']);
    $this->core->setBlockVisibility($created, 'user_role', ['roles' => ['authenticated' => 'authenticated']]);

    $this->core->unsetBlockVisibility($created, 'request_path');

    $this->assertSame(['user_role'], array_keys($this->loadBlock((string) $created->getValue('id'))->getVisibility()));
  }

  public function testUnsetBlockVisibilityLeavesBlockWithoutTheConditionUnchanged(): void {
    $created = $this->core->placeBlock('system_powered_by_block', 'Powered by');

    $this->core->unsetBlockVisibility($created, 'request_path');

    $this->assertSame([], $this->loadBlock((string) $created->getValue('id'))->getVisibility());
  }

  public function testGetBlockRegionReadsTheOverriddenRegion(): void {
    $created = $this->core->placeBlock('system_powered_by_block', 'Powered by');
    $this->core->configureBlock($created, ['region' => 'header']);
    $this->assertSame('header', $this->core->getBlockRegion($created));

    $GLOBALS['config']['block.block.' . $created->getValue('id')]['region'] = 'footer';
    \Drupal::configFactory()->reset('block.block.' . $created->getValue('id'));
    \Drupal::entityTypeManager()->getStorage('block')->resetCache();

    $this->assertSame('footer', $this->core->getBlockRegion(new EntityStub('block', NULL, ['id' => $created->getValue('id')])));
  }

  /**
   * Tests that a block method rejects a stub naming no block.
   *
   * @param string $method
   *   The Core method to call.
   * @param array<int, mixed> $args
   *   The arguments after the stub.
   */
  #[DataProvider('dataProviderBlockMethodsRejectMissingBlock')]
  public function testBlockMethodsRejectMissingBlock(string $method, array $args): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('No block with the id "missing" exists.');

    $this->core->{$method}(new EntityStub('block', NULL, ['id' => 'missing']), ...$args);
  }

  public static function dataProviderBlockMethodsRejectMissingBlock(): \Iterator {
    yield 'configureBlock' => ['configureBlock', [['region' => 'header']]];
    yield 'setBlockVisibility' => ['setBlockVisibility', ['request_path', []]];
    yield 'unsetBlockVisibility' => ['unsetBlockVisibility', ['request_path']];
    yield 'getBlockRegion' => ['getBlockRegion', []];
  }

  public function testFindBlockIdsByLabelFindsTheBlocksOfTheDefaultTheme(): void {
    \Drupal::service('theme_installer')->install(['olivero']);
    Block::create(['id' => 'olivero_shared', 'plugin' => 'system_powered_by_block', 'theme' => 'olivero', 'region' => 'content', 'settings' => ['label' => 'Shared']])->save();
    $this->core->placeBlock('system_powered_by_block', 'Shared');
    $this->core->placeBlock('system_powered_by_block', 'Other');
    $this->core->placeBlock('system_powered_by_block', 'Shared');

    $this->assertSame(['stark_poweredbydrupal', 'stark_poweredbydrupal_3'], $this->core->findBlockIdsByLabel('Shared'));
    $this->assertSame([], $this->core->findBlockIdsByLabel('Missing'));
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

  public function testFindBlockPluginIdMatchesTheAdminLabel(): void {
    $this->assertSame('system_powered_by_block', $this->core->findBlockPluginId('Powered by Drupal'));
    $this->assertNull($this->core->findBlockPluginId('No such block'));
  }

  public function testHasConditionPluginReportsTheCondition(): void {
    $this->assertTrue($this->core->hasConditionPlugin('request_path'));
    $this->assertFalse($this->core->hasConditionPlugin('request_paths'));
  }

  /**
   * Loads a placed block, bypassing the static cache.
   *
   * @param string $id
   *   The block id.
   */
  protected function loadBlock(string $id): Block {
    $block = \Drupal::entityTypeManager()->getStorage('block')->loadUnchanged($id);
    $this->assertInstanceOf(Block::class, $block);

    return $block;
  }

}
