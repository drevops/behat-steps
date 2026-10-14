<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Steps\Drupal;

use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Steps\Drupal\SearchApiTrait;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for indexing content through 'SearchApiTrait'.
 *
 * The index includes only the 'article' content type and sits on the
 * 'search_api_test' backend, which records every item it indexes in state.
 */
#[CoversTrait(SearchApiTrait::class)]
#[Group('behat')]
#[RunTestsInSeparateProcesses]
class SearchApiTraitKernelTest extends StepTraitKernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = ['system', 'user', 'node', 'search_api', 'search_api_test'];

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
  }

  public function testIndexContentIndexesOnlyTheNamedNode(): void {
    $this->createNode('article', 'Pending');
    $named = $this->createNode('article', 'Named');

    $this->context->searchApiIndexContent('article', 'Named');

    $this->assertSame([sprintf('entity:node/%s:%s', $named->id(), $named->language()->getId())], $this->readIndexedItemIds());
  }

  public function testIndexNodeFailsWhenNoIndexIncludesTheContentType(): void {
    $page = $this->createNode('page', 'Page');

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('No active search index includes the "page" content with the title "Page".');

    $this->context->searchApiIndexNode(new EntityStub('node', 'page', ['nid' => $page->id()]));
  }

  public function testIndexNodeSkipsTheDisabledIndex(): void {
    $article = $this->createNode('article', 'Article');
    Index::load('test_index')?->disable()->save();

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('No active search index includes the "article" content with the title "Article".');

    $this->context->searchApiIndexNode(new EntityStub('node', 'article', ['nid' => $article->id()]));
  }

  public function testIndexNodeFailsForMissingNode(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('No content entity of type "node" with the id "999" exists.');

    $this->context->searchApiIndexNode(new EntityStub('node', 'article', ['nid' => 999]));
  }

  public function testFindNodeByTitleReturnsTheStubOfTheNewestNode(): void {
    $this->createNode('article', 'Shared');
    $newest = $this->createNode('article', 'Shared');

    $stub = $this->context->searchApiFindNodeByTitle('article', 'Shared');

    $this->assertNotNull($stub);
    $this->assertSame((string) $newest->id(), $stub->getValue('nid'));
    $this->assertNull($this->context->searchApiFindNodeByTitle('article', 'Missing'));
  }

  protected function createNode(string $content_type, string $title): NodeInterface {
    $node = Node::create(['type' => $content_type, 'title' => $title]);
    $node->save();

    return $node;
  }

  /**
   * Reads the IDs of the items the test backend indexed.
   *
   * @return array<int, string>
   *   The item IDs.
   */
  protected function readIndexedItemIds(): array {
    return array_map(strval(...), array_keys(\Drupal::state()->get('search_api_test.backend.indexed.test_index', [])));
  }

}
