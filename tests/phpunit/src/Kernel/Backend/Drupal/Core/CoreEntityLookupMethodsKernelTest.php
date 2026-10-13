<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Drupal\Core;

use DrevOps\BehatSteps\Backend\Drupal\Core\Core;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for the entity lookup methods on Core.
 *
 * Covers 'findEntityIds()' over a content entity type with bundles and a
 * config entity type without them.
 */
#[CoversClass(Core::class)]
#[Group('core')]
#[RunTestsInSeparateProcesses]
class CoreEntityLookupMethodsKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = ['system', 'user', 'node', 'field', 'text', 'filter'];

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

    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

    $this->core = new Core($this->root);
  }

  public function testFindEntityIdsMatchesValueWithEquals(): void {
    $first = $this->createNode('page', 'Shared');
    $this->createNode('page', 'Other');
    $second = $this->createNode('article', 'Shared');

    $this->assertSame([(string) $first->id(), (string) $second->id()], $this->core->findEntityIds('node', ['title' => 'Shared']));
  }

  public function testFindEntityIdsRestrictsToTheBundle(): void {
    $page = $this->createNode('page', 'Shared');
    $this->createNode('article', 'Shared');

    $this->assertSame([(string) $page->id()], $this->core->findEntityIds('node', ['title' => 'Shared'], 'page'));
  }

  public function testFindEntityIdsReturnsEveryEntityWithoutConditions(): void {
    $first = $this->createNode('page', 'Shared');
    $second = $this->createNode('article', 'Other');

    $this->assertSame([(string) $first->id(), (string) $second->id()], $this->core->findEntityIds('node'));
  }

  public function testFindEntityIdsReturnsNothingWhenNothingMatches(): void {
    $this->createNode('page', 'Shared');

    $this->assertSame([], $this->core->findEntityIds('node', ['title' => 'Missing']));
    $this->assertSame([], $this->core->findEntityIds('node', ['title' => 'Shared'], 'article'));
  }

  /**
   * Tests that an [operator, value] pair matches with the operator it names.
   *
   * @param array<string, mixed> $conditions
   *   The conditions to look up with.
   * @param array<int, string> $expected
   *   The titles of the nodes the lookup finds, in creation order.
   */
  #[DataProvider('dataProviderFindEntityIdsMatchesWithTheOperator')]
  public function testFindEntityIdsMatchesWithTheOperator(array $conditions, array $expected): void {
    $ids = [];

    foreach (['Alpha one', 'Alpha two', 'Beta one'] as $title) {
      $ids[$title] = (string) $this->createNode('page', $title)->id();
    }

    $expected_ids = array_map(static fn(string $title): string => $ids[$title], $expected);

    $this->assertSame($expected_ids, $this->core->findEntityIds('node', $conditions));
  }

  public static function dataProviderFindEntityIdsMatchesWithTheOperator(): \Iterator {
    yield 'equals pair' => [['title' => ['=', 'Alpha one']], ['Alpha one']];
    yield 'IN' => [['title' => ['IN', ['Alpha one', 'Beta one']]], ['Alpha one', 'Beta one']];
    yield 'CONTAINS' => [['title' => ['CONTAINS', 'one']], ['Alpha one', 'Beta one']];
    yield 'LIKE' => [['title' => ['LIKE', 'Alpha%']], ['Alpha one', 'Alpha two']];
    yield 'operator in lower case' => [['title' => ['like', 'Alpha%']], ['Alpha one', 'Alpha two']];
    yield 'several conditions' => [['title' => ['CONTAINS', 'one'], 'status' => 1], ['Alpha one', 'Beta one']];
    yield 'conditions that exclude each other' => [['title' => ['CONTAINS', 'Alpha'], 'status' => 0], []];
  }

  public function testFindEntityIdsReturnsIdsInNaturalOrder(): void {
    $ids = [];

    for ($i = 0; $i < 11; $i++) {
      $ids[] = (string) $this->createNode('page', 'Shared')->id();
    }

    $this->assertSame('11', end($ids));
    $this->assertSame($ids, $this->core->findEntityIds('node', ['title' => 'Shared']));
  }

  public function testFindEntityIdsLooksUpConfigEntities(): void {
    Role::create(['id' => 'editor', 'label' => '[TEST] Editor'])->save();
    Role::create(['id' => 'reviewer', 'label' => '[TEST] Reviewer'])->save();

    $this->assertSame(['editor'], $this->core->findEntityIds('user_role', ['label' => '[TEST] Editor']));
    $this->assertSame(['editor', 'reviewer'], $this->core->findEntityIds('user_role', ['label' => ['CONTAINS', '[TEST]']]));
    $this->assertSame(['editor', 'reviewer'], $this->core->findEntityIds('user_role', ['id' => ['IN', ['editor', 'reviewer']]]));
  }

  public function testFindEntityIdsRejectsAnUnknownEntityType(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Unknown entity type "nonexistent_type".');

    $this->core->findEntityIds('nonexistent_type');
  }

  public function testFindEntityIdsRejectsBundleForEntityTypeWithoutBundles(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Entity type "user" declares no bundle key, so it cannot be looked up by bundle.');

    $this->core->findEntityIds('user', [], 'editor');
  }

  public function testFindEntityIdsRejectsMalformedCondition(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The condition on "title" is a value or an [operator, value] pair.');

    $this->core->findEntityIds('node', ['title' => ['Shared', 'Other', 'Third']]);
  }

  protected function createNode(string $content_type, string $title): NodeInterface {
    $node = Node::create(['type' => $content_type, 'title' => $title]);
    $node->save();

    return $node;
  }

}
