<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Drupal\Core;

use DrevOps\BehatSteps\Backend\Drupal\Core\Core;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for the search index capability methods on Core.
 *
 * The index includes only the 'article' content type and sits on the
 * 'search_api_test' backend, which records every item it indexes in state.
 */
#[CoversClass(Core::class)]
#[Group('core')]
#[RunTestsInSeparateProcesses]
class CoreSearchIndexMethodsKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = ['system', 'user', 'node', 'search_api', 'search_api_test'];

  /**
   * The Core backend under test.
   */
  protected Core $core;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('search_api', ['search_api_item']);
    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('search_api_task');
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(['search_api']);

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();

    Server::create(['id' => 'test_server', 'name' => 'Test server', 'backend' => 'search_api_test'])->save();
    Index::create([
      'id' => 'test_index',
      'name' => 'Test index',
      'status' => TRUE,
      'server' => 'test_server',
      'datasource_settings' => ['entity:node' => ['bundles' => ['default' => FALSE, 'selected' => ['article']]]],
      'tracker_settings' => ['default' => []],
      'options' => ['index_directly' => FALSE],
    ])->save();

    $this->core = new Core($this->root);
  }

  public function testIndexEntityIndexesOnlyTheEntity(): void {
    $this->createNode('article', 'Pending');
    $named = $this->createNode('article', 'Named');

    $this->assertTrue($this->core->searchIndexEntity('node', (string) $named->id()));

    $this->assertSame([$this->buildItemId($named)], $this->readIndexedItemIds());
  }

  public function testIndexEntityReportsAnEntityNoIndexIncludes(): void {
    $page = $this->createNode('page', 'Page');

    $this->assertFalse($this->core->searchIndexEntity('node', (string) $page->id()));
    $this->assertSame([], $this->readIndexedItemIds());
  }

  public function testIndexEntitySkipsDisabledIndex(): void {
    $article = $this->createNode('article', 'Article');
    Index::load('test_index')?->disable()->save();

    $this->assertFalse($this->core->searchIndexEntity('node', (string) $article->id()));
  }

  public function testIndexEntityRejectsMissingEntity(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('No content entity of type "node" with the id "999" exists.');

    $this->core->searchIndexEntity('node', 999);
  }

  public function testIndexEntityRejectsAnUnknownEntityType(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Unknown entity type "widget".');

    $this->core->searchIndexEntity('widget', 1);
  }

  public function testIndexItemsIndexesTheWaitingItems(): void {
    $this->createNode('article', 'First');
    $this->createNode('article', 'Second');

    $this->core->searchIndexItems(1);
    $this->assertCount(1, $this->readIndexedItemIds());

    $this->core->searchIndexItems(5);
    $this->assertCount(2, $this->readIndexedItemIds());
  }

  public function testIndexItemsWithZeroIndexesNothing(): void {
    $this->createNode('article', 'Waiting');

    $this->core->searchIndexItems(0);

    $this->assertSame([], $this->readIndexedItemIds());
  }

  public function testIndexItemsFailsWithoutAnActiveIndex(): void {
    Index::load('test_index')?->disable()->save();

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('No active search indexes found.');

    $this->core->searchIndexItems(1);
  }

  public function testRunCronIndexesTheWaitingItems(): void {
    $waiting = $this->createNode('article', 'Waiting');

    $this->core->searchRunCron();

    $this->assertSame([$this->buildItemId($waiting)], $this->readIndexedItemIds());
  }

  protected function createNode(string $content_type, string $title): NodeInterface {
    $node = Node::create(['type' => $content_type, 'title' => $title]);
    $node->save();

    return $node;
  }

  /**
   * Builds the id a node is indexed under.
   */
  protected function buildItemId(NodeInterface $node): string {
    return sprintf('entity:node/%s:%s', $node->id(), $node->language()->getId());
  }

  /**
   * Reads the ids of the items the test backend indexed.
   *
   * @return array<int, string>
   *   The item ids.
   */
  protected function readIndexedItemIds(): array {
    return array_map(strval(...), array_keys(\Drupal::state()->get('search_api_test.backend.indexed.test_index', [])));
  }

}
