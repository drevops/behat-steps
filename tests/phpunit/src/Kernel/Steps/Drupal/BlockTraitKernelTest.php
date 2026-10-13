<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Steps\Drupal;

use DrevOps\BehatSteps\Steps\Drupal\BlockTrait;
use Drupal\block\Entity\Block;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for finding and configuring blocks through 'BlockTrait'.
 *
 * Blocks are placed in the 'stark' theme. A block placed through
 * 'blockCreate()' takes its plugin's admin label as its label.
 */
#[CoversTrait(BlockTrait::class)]
#[Group('behat')]
#[RunTestsInSeparateProcesses]
class BlockTraitKernelTest extends StepTraitKernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = ['system', 'user', 'block', 'path_alias'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    \Drupal::service('theme_installer')->install(['stark']);
    $this->config('system.theme')->set('default', 'stark')->save();
  }

  public function testFindByLabelReturnsTheBlockPlacedLast(): void {
    $placed = NULL;

    for ($i = 0; $i < 10; $i++) {
      $placed = $this->context->blockCreate('Powered by Drupal');
    }

    $this->assertSame('stark_poweredbydrupal_10', $placed->getValue('id'));
    $this->assertSame('stark_poweredbydrupal_10', $this->context->blockFindByLabel('Powered by Drupal')?->getValue('id'));
  }

  public function testFindByLabelReturnsTheBlockPlacedLastFromAnotherPlugin(): void {
    $this->context->blockCreate('Powered by Drupal');
    $placed = $this->context->blockCreate('Messages');
    $this->context->blockApplyConfiguration($placed, ['label' => 'Powered by Drupal']);

    $this->assertSame($placed->getValue('id'), $this->context->blockFindByLabel('Powered by Drupal')?->getValue('id'));
  }

  public function testFindByLabelReturnsTheBlockPlacedInFreedSuffix(): void {
    $this->context->blockCreate('Powered by Drupal');
    $freed = $this->context->blockCreate('Powered by Drupal');
    $this->context->blockCreate('Powered by Drupal');

    $freed_id = (string) $freed->getValue('id');
    $this->loadBlock($freed_id)->delete();

    $placed = $this->context->blockCreate('Powered by Drupal');

    $this->assertSame($freed_id, $placed->getValue('id'));
    $this->assertSame($freed_id, $this->context->blockFindByLabel('Powered by Drupal')?->getValue('id'));
  }

  public function testFindByLabelPrefersThePlacedBlockOverSiteBlock(): void {
    $this->createSiteBlock('stark_zzz', 'Powered by Drupal');
    $placed = $this->context->blockCreate('Powered by Drupal');

    $this->assertSame($placed->getValue('id'), $this->context->blockFindByLabel('Powered by Drupal')?->getValue('id'));
  }

  public function testFindByLabelReturnsTheLastSiteBlockInNaturalOrder(): void {
    $this->createSiteBlock('stark_shared', 'Shared');
    $this->createSiteBlock('stark_shared_10', 'Shared');
    $this->createSiteBlock('stark_shared_2', 'Shared');

    $this->assertSame('stark_shared_10', $this->context->blockFindByLabel('Shared')?->getValue('id'));
  }

  public function testFindByLabelFindsNothing(): void {
    $this->createSiteBlock('stark_shared', 'Shared');

    $this->assertNull($this->context->blockFindByLabel('Missing'));
  }

  public function testApplyConfigurationSetsTheKnownKeys(): void {
    $block = $this->context->blockCreate('Powered by Drupal');

    $this->context->blockApplyConfiguration($block, [
      'label' => 'Renamed',
      'label_display' => '1',
      'region' => 'sidebar_first',
      'status' => '0',
      'weight' => '10',
    ]);

    $saved = $this->loadBlock((string) $block->getValue('id'));
    $this->assertSame('Renamed', $saved->get('settings')['label']);
    $this->assertNotEmpty($saved->get('settings')['label_display']);
    $this->assertSame('sidebar_first', $saved->getRegion());
    $this->assertFalse($saved->status());
    $this->assertSame(0, (int) $saved->getWeight(), 'A key the helper does not know is ignored.');
  }

  public function testApplyConfigurationRejectsNonStringRegion(): void {
    $block = $this->context->blockCreate('Powered by Drupal');

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Expected region as string.');

    $this->context->blockApplyConfiguration($block, ['region' => ['content']]);
  }

  public function testApplyConfigurationWritesNoOverrideIntoTheStoredSettings(): void {
    $block = $this->context->blockCreate('Powered by Drupal');
    $id = (string) $block->getValue('id');

    $GLOBALS['config']['block.block.' . $id]['settings']['label'] = 'Overridden';
    \Drupal::configFactory()->reset('block.block.' . $id);
    $this->assertSame('Overridden', \Drupal::config('block.block.' . $id)->get('settings.label'));

    $this->context->blockApplyConfiguration($block, ['label_display' => '1']);

    $this->assertSame('Powered by Drupal', \Drupal::config('block.block.' . $id)->getOriginal('settings.label', FALSE));
  }

  public function testEnableAndDisableSetTheStatus(): void {
    $block = $this->context->blockCreate('Powered by Drupal');

    $this->context->blockDisable('Powered by Drupal');
    $this->assertFalse($this->loadBlock((string) $block->getValue('id'))->status());

    $this->context->blockEnable('Powered by Drupal');
    $this->assertTrue($this->loadBlock((string) $block->getValue('id'))->status());
  }

  /**
   * Tests that the region assertions pass on the region of the block.
   *
   * A failing assertion reports through the Mink session, which a kernel
   * test has none of, so the failures are covered by the Behat scenarios.
   */
  public function testRegionAssertionsPassOnTheRegionOfTheBlock(): void {
    $block = $this->context->blockCreate('Powered by Drupal');
    $this->context->blockApplyConfiguration($block, ['region' => 'header']);

    $this->context->blockAssertExistsInRegion('Powered by Drupal', 'header');
    $this->context->blockAssertNotExistsInRegion('Powered by Drupal', 'content');

    $this->addToAssertionCount(2);
  }

  public function testUnsetVisibilityConditionRemovesOnlyTheCondition(): void {
    $block = $this->context->blockCreate('Powered by Drupal');
    $this->context->blockSetVisibilityCondition($block, 'request_path', ['pages' => '/user/*']);
    $this->context->blockSetVisibilityCondition($block, 'user_role', ['roles' => ['authenticated' => 'authenticated']]);

    $this->context->blockUnsetVisibilityCondition($block, 'request_path');

    $saved = $this->loadBlock((string) $block->getValue('id'));
    $this->assertFalse($saved->getVisibilityConditions()->has('request_path'), 'The block no longer carries the condition.');
    $this->assertSame(['user_role'], array_keys($saved->getVisibility()), 'The saved block keeps only the other condition.');
  }

  public function testUnsetVisibilityConditionLeavesTheBlockWithoutTheConditionUnchanged(): void {
    $block = $this->context->blockCreate('Powered by Drupal');

    $this->context->blockUnsetVisibilityCondition($block, 'request_path');

    $this->assertSame([], $this->loadBlock((string) $block->getValue('id'))->getVisibility());
  }

  public function testUnsetVisibilityConditionRejectsAnUnknownCondition(): void {
    $block = $this->context->blockCreate('Powered by Drupal');

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The condition "request_paths" does not exist.');

    $this->context->blockUnsetVisibilityCondition($block, 'request_paths');
  }

  public function testSetVisibilityConditionReplacesTheConfigurationOfTheCondition(): void {
    $block = $this->context->blockCreate('Powered by Drupal');
    $this->context->blockSetVisibilityCondition($block, 'request_path', ['pages' => '/user/*']);

    $this->context->blockSetVisibilityCondition($block, 'request_path', ['pages' => '/node/*', 'negate' => TRUE]);

    $visibility = $this->loadBlock((string) $block->getValue('id'))->getVisibility();
    $this->assertSame('/node/*', $visibility['request_path']['pages']);
    $this->assertTrue($visibility['request_path']['negate']);
  }

  public function testRemoveVisibilityConditionRemovesTheConditionByLabel(): void {
    $block = $this->context->blockCreate('Powered by Drupal');
    $this->context->blockSetVisibilityCondition($block, 'request_path', ['pages' => '/user/*']);

    $this->context->blockRemoveVisibilityCondition('Powered by Drupal', 'request_path');

    $this->assertSame([], $this->loadBlock((string) $block->getValue('id'))->getVisibility());
  }

  /**
   * Loads a saved block.
   *
   * @param string $id
   *   The block id.
   */
  protected function loadBlock(string $id): Block {
    $block = Block::load($id);
    $this->assertInstanceOf(Block::class, $block);

    return $block;
  }

  /**
   * Creates a block the scenario did not place.
   *
   * @param string $id
   *   The block id.
   * @param string $label
   *   The block label.
   */
  protected function createSiteBlock(string $id, string $label): void {
    Block::create(['id' => $id, 'plugin' => 'system_powered_by_block', 'theme' => 'stark', 'region' => 'content', 'settings' => ['label' => $label]])->save();
  }

}
