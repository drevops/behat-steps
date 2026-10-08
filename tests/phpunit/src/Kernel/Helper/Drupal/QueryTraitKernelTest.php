<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Helper\Drupal;

use DrevOps\BehatSteps\Helper\Drupal\QueryTrait;
use DrevOps\BehatSteps\Tests\Kernel\Steps\Drupal\StepTraitKernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for finding the newest matching entity through 'QueryTrait'.
 */
#[CoversTrait(QueryTrait::class)]
#[Group('behat')]
#[RunTestsInSeparateProcesses]
class QueryTraitKernelTest extends StepTraitKernelTestBase {

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
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
  }

  public function testFindNewestEntityIdReturnsTheEntityCreatedLast(): void {
    $this->createNode('page', 'Shared');
    $newest = $this->createNode('page', 'Shared');
    $this->createNode('page', 'Other');
    $this->createNode('article', 'Shared');

    $this->assertSame((string) $newest->id(), $this->context->queryFindNewestEntityId('node', ['title' => 'Shared'], 'page'));
  }

  public function testFindNewestEntityIdIgnoresLaterRevisionOfOlderEntity(): void {
    $older = $this->createNode('page', 'Shared');
    $newest = $this->createNode('page', 'Shared');

    $older->setNewRevision(TRUE);
    $older->save();

    $this->assertGreaterThan((int) $newest->getRevisionId(), (int) $older->getRevisionId());
    $this->assertSame((string) $newest->id(), $this->context->queryFindNewestEntityId('node', ['title' => 'Shared'], 'page'));
  }

  public function testFindNewestEntityIdComparesIdsAsNumbers(): void {
    $nodes = [];

    for ($i = 0; $i < 10; $i++) {
      $nodes[] = $this->createNode('page', 'Shared');
    }

    $newest = end($nodes);

    $this->assertSame('10', (string) $newest->id());
    $this->assertSame('10', $this->context->queryFindNewestEntityId('node', ['title' => 'Shared'], 'page'));
  }

  public function testFindNewestEntityIdReturnsNullWhenNothingMatches(): void {
    $this->createNode('article', 'Shared');

    $this->assertNull($this->context->queryFindNewestEntityId('node', ['title' => 'Shared'], 'page'));
  }

  protected function createNode(string $content_type, string $title): NodeInterface {
    $node = Node::create(['type' => $content_type, 'title' => $title]);
    $node->save();

    return $node;
  }

}
