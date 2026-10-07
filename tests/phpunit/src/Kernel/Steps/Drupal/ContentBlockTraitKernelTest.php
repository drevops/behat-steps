<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Steps\Drupal;

use DrevOps\BehatSteps\Steps\Drupal\ContentBlockTrait;
use Drupal\block_content\BlockContentInterface;
use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for loading content blocks through 'ContentBlockTrait'.
 */
#[CoversTrait(ContentBlockTrait::class)]
#[Group('behat')]
#[RunTestsInSeparateProcesses]
class ContentBlockTraitKernelTest extends StepTraitKernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = ['system', 'user', 'block', 'block_content'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('block_content');
    $this->installConfig(['system']);

    BlockContentType::create(['id' => 'basic', 'label' => 'Basic'])->save();
    BlockContentType::create(['id' => 'other', 'label' => 'Other'])->save();
  }

  public function testLoadMultipleLoadsTheMatchingContentBlocks(): void {
    $first = $this->createContentBlock('basic', 'Shared');
    $second = $this->createContentBlock('basic', 'Shared');
    $this->createContentBlock('basic', 'Other');
    $this->createContentBlock('other', 'Shared');

    $content_blocks = $this->context->contentBlockLoadMultiple('basic', ['info' => 'Shared']);

    $this->assertLoadedSet([$first, $second], $content_blocks, BlockContentInterface::class);
  }

  public function testLoadMultipleReturnsAnEmptyArrayWhenNothingMatches(): void {
    $this->createContentBlock('other', 'Shared');

    $this->assertSame([], $this->context->contentBlockLoadMultiple('basic', ['info' => 'Shared']));
  }

  protected function createContentBlock(string $content_block_type, string $info): BlockContentInterface {
    $content_block = BlockContent::create(['type' => $content_block_type, 'info' => $info]);
    $content_block->save();

    return $content_block;
  }

}
