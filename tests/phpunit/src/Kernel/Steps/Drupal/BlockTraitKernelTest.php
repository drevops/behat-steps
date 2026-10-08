<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Steps\Drupal;

use DrevOps\BehatSteps\Steps\Drupal\BlockTrait;
use Drupal\block\Entity\Block;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for finding blocks by label through 'BlockTrait'.
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
  protected static $modules = ['system', 'user', 'block'];

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

    $this->assertInstanceOf(Block::class, $placed);
    $this->assertSame('stark_poweredbydrupal_10', $placed->id());
    $this->assertSame('stark_poweredbydrupal_10', $this->context->blockFindByLabel('Powered by Drupal')?->id());
  }

  public function testFindByLabelReturnsTheBlockPlacedLastFromAnotherPlugin(): void {
    $this->context->blockCreate('Powered by Drupal');
    $placed = $this->context->blockCreate('Messages');
    $this->context->blockApplyConfiguration($placed, ['label' => 'Powered by Drupal']);

    $this->assertSame($placed->id(), $this->context->blockFindByLabel('Powered by Drupal')?->id());
  }

  public function testFindByLabelReturnsTheBlockPlacedInFreedSuffix(): void {
    $this->context->blockCreate('Powered by Drupal');
    $freed = $this->context->blockCreate('Powered by Drupal');
    $this->context->blockCreate('Powered by Drupal');

    $freed_id = $freed->id();
    $freed->delete();

    $placed = $this->context->blockCreate('Powered by Drupal');

    $this->assertSame($freed_id, $placed->id());
    $this->assertSame($freed_id, $this->context->blockFindByLabel('Powered by Drupal')?->id());
  }

  public function testFindByLabelPrefersThePlacedBlockOverSiteBlock(): void {
    $this->createSiteBlock('stark_zzz', 'Powered by Drupal');
    $placed = $this->context->blockCreate('Powered by Drupal');

    $this->assertSame($placed->id(), $this->context->blockFindByLabel('Powered by Drupal')?->id());
  }

  public function testFindByLabelReturnsTheLastSiteBlockInNaturalOrder(): void {
    $this->createSiteBlock('stark_shared', 'Shared');
    $this->createSiteBlock('stark_shared_10', 'Shared');
    $this->createSiteBlock('stark_shared_2', 'Shared');

    $this->assertSame('stark_shared_10', $this->context->blockFindByLabel('Shared')?->id());
  }

  public function testFindByLabelFindsNothing(): void {
    $this->createSiteBlock('stark_shared', 'Shared');

    $this->assertNull($this->context->blockFindByLabel('Missing'));
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
