<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Drupal\Core;

use DrevOps\BehatSteps\Backend\Drupal\Core\Core;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for rebuilding node access grants on Core.
 *
 * The 'node_access_test' module implements the node grants hooks, so a
 * rebuild writes grant rows for each node rather than the single default
 * row.
 */
#[CoversClass(Core::class)]
#[Group('core')]
#[RunTestsInSeparateProcesses]
class CoreNodeAccessMethodsKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = ['system', 'user', 'node', 'field', 'text', 'filter', 'node_access_test'];

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
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'user', 'node', 'filter']);

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

    $this->core = new Core($this->root);
  }

  public function testRebuildNodeAccessWritesTheGrantsOfOneNode(): void {
    $this->createNode('First');
    $second = $this->createNode('Second');
    \Drupal::database()->delete('node_access')->execute();

    $this->core->rebuildNodeAccess((int) $second->id());

    $this->assertSame([(int) $second->id()], $this->readGrantedNids());
  }

  public function testRebuildNodeAccessWritesTheGrantsOfEveryNode(): void {
    $first = $this->createNode('First');
    $second = $this->createNode('Second');
    \Drupal::database()->delete('node_access')->execute();

    $this->core->rebuildNodeAccess();

    $this->assertSame([(int) $first->id(), (int) $second->id()], $this->readGrantedNids());
  }

  public function testRebuildNodeAccessRejectsMissingNode(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('No node with the id "999" exists.');

    $this->core->rebuildNodeAccess(999);
  }

  protected function createNode(string $title): NodeInterface {
    $node = Node::create(['type' => 'article', 'title' => $title]);
    $node->save();

    return $node;
  }

  /**
   * Reads the ids of the nodes holding a grant row.
   *
   * @return array<int, int>
   *   The node ids, in order.
   */
  protected function readGrantedNids(): array {
    return array_map(intval(...), \Drupal::database()->select('node_access', 'na')->fields('na', ['nid'])->distinct()->orderBy('nid')->execute()->fetchCol());
  }

}
