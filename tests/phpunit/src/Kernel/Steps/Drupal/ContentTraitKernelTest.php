<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Steps\Drupal;

use DrevOps\BehatSteps\Steps\Drupal\ContentTrait;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for finding nodes by title through 'ContentTrait'.
 */
#[CoversTrait(ContentTrait::class)]
#[Group('behat')]
#[RunTestsInSeparateProcesses]
class ContentTraitKernelTest extends StepTraitKernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = ['system', 'user', 'node', 'field', 'text', 'filter'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'user', 'node', 'filter']);

    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
  }

  public function testGetNidByTitleReturnsTheNodeCreatedLast(): void {
    $older = $this->createNode('Shared');
    $newest = $this->createNode('Shared');

    $older->setNewRevision(TRUE);
    $older->save();

    $this->assertSame((int) $newest->id(), $this->context->contentGetNidByTitle('page', 'Shared'));
    $this->assertSame($newest->id(), $this->context->contentGetNodeByTitle('page', 'Shared')->id());
  }

  #[DataProvider('dataProviderGetNidByTitleFails')]
  public function testGetNidByTitleFails(string $content_type, string $title, string $expected_message): void {
    $this->createNode('Shared');

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage($expected_message);

    $this->context->contentGetNidByTitle($content_type, $title);
  }

  public static function dataProviderGetNidByTitleFails(): array {
    return [
      'a content type that does not exist' => ['missing', 'Shared', 'The content type "missing" does not exist.'],
      'a title no node carries' => ['page', 'Missing', 'Unable to find "page" content with title "Missing".'],
    ];
  }

  protected function createNode(string $title): NodeInterface {
    $node = Node::create(['type' => 'page', 'title' => $title]);
    $node->save();

    return $node;
  }

}
