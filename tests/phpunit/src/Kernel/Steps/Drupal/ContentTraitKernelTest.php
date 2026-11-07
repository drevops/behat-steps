<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Steps\Drupal;

use Behat\Gherkin\Node\TableNode;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Steps\Drupal\ContentTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\path_alias\PathAliasInterface;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for finding and changing nodes through 'ContentTrait'.
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
  protected static $modules = ['system', 'user', 'node', 'field', 'text', 'filter', 'path', 'path_alias'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('path_alias');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'user', 'node', 'filter']);

    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();

    FieldStorageConfig::create(['field_name' => 'field_keywords', 'entity_type' => 'node', 'type' => 'string', 'cardinality' => -1])->save();
    FieldConfig::create(['field_name' => 'field_keywords', 'entity_type' => 'node', 'bundle' => 'page'])->save();
  }

  public function testUpdateWritesParsedValuesToTheNode(): void {
    $node = $this->createNode('Shared', 1, ['old']);

    $this->context->contentUpdate($this->context->contentGetNodeByTitle('page', 'Shared'), ['title' => 'Renamed', 'status' => '0', 'field_keywords' => 'first, second']);

    $updated = $this->reloadNode($node);
    $this->assertSame('Renamed', $updated->getTitle());
    $this->assertFalse($updated->isPublished());
    $this->assertSame([['value' => 'first'], ['value' => 'second']], $updated->get('field_keywords')->getValue(), 'The comma-separated cell was parsed into 2 deltas.');
  }

  #[DataProvider('dataProviderUpdateClearsFieldGivenEmptyValue')]
  public function testUpdateClearsFieldGivenEmptyValue(?string $value): void {
    $node = $this->createNode('Shared', 1, ['old']);

    $this->context->contentUpdate(new EntityStub('node', NULL, ['nid' => $node->id()]), ['field_keywords' => $value]);

    $updated = $this->reloadNode($node);
    $this->assertTrue($updated->get('field_keywords')->isEmpty());
    $this->assertSame('Shared', $updated->getTitle(), 'A field the values do not name is left unchanged.');
  }

  public static function dataProviderUpdateClearsFieldGivenEmptyValue(): array {
    return [
      'an empty string' => [''],
      'whitespace' => ['  '],
      'NULL' => [NULL],
    ];
  }

  #[DataProvider('dataProviderUpdateFailsForUnknownField')]
  public function testUpdateFailsForUnknownField(string $value, string $expected_message): void {
    $node = $this->createNode('Shared', 1, ['old']);

    try {
      $this->context->contentUpdate(new EntityStub('node', 'page', ['nid' => $node->id()]), ['field_keywords' => 'new', 'field_missing' => $value]);
      $this->fail('The update did not fail for a field the node does not have.');
    }
    catch (\RuntimeException $exception) {
      $this->assertStringContainsString($expected_message, $exception->getMessage());
    }

    $this->assertSame([['value' => 'old']], $this->reloadNode($node)->get('field_keywords')->getValue(), 'The node is left unchanged.');
  }

  public static function dataProviderUpdateFailsForUnknownField(): array {
    return [
      'a value to write' => ['value', 'The field "field_missing" does not exist on entity type "node".'],
      'an empty value to clear' => ['', 'because it has no field "field_missing".'],
    ];
  }

  public function testUpdateRejectsFieldBothClearedAndGivenColumnValue(): void {
    $node = $this->createNode('Shared', 1, ['old']);

    try {
      $this->context->contentUpdate(new EntityStub('node', 'page', ['nid' => $node->id()]), ['field_keywords' => '', 'field_keywords:value' => 'new']);
      $this->fail('The update did not fail for a field both cleared and given a column value.');
    }
    catch (\RuntimeException $exception) {
      $this->assertSame('The field "field_keywords" cannot be both cleared and given a column value.', $exception->getMessage());
    }

    $this->assertSame([['value' => 'old']], $this->reloadNode($node)->get('field_keywords')->getValue(), 'The node is left unchanged.');
  }

  public function testUpdateWithTitleChangesOnlyTheNewestNode(): void {
    $older = $this->createNode('Shared', 1, ['old']);
    $newest = $this->createNode('Shared', 1, ['old']);

    $this->context->contentUpdateWithTitle('page', 'Shared', new TableNode([['field_keywords', 'new']]));

    $this->assertSame([['value' => 'new']], $this->reloadNode($newest)->get('field_keywords')->getValue());
    $this->assertSame([['value' => 'old']], $this->reloadNode($older)->get('field_keywords')->getValue());
  }

  public function testUpdateWithTitleRejectsMoreThan1ValueColumn(): void {
    $this->createNode('Shared');

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The table of fields must have 2 columns, a field name and a value, but it has 3.');

    $this->context->contentUpdateWithTitle('page', 'Shared', new TableNode([['field_keywords', 'first', 'second']]));
  }

  public function testGetNidByTitleReturnsTheNodeCreatedLast(): void {
    $older = $this->createNode('Shared');
    $newest = $this->createNode('Shared');

    $older->setNewRevision(TRUE);
    $older->save();

    $this->assertSame((int) $newest->id(), $this->context->contentGetNidByTitle('page', 'Shared'));

    $stub = $this->context->contentGetNodeByTitle('page', 'Shared');
    $this->assertSame('page', $stub->getBundle());
    $this->assertSame((int) $newest->id(), $stub->getValue('nid'));
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

  public function testSetModerationStateRejectsStateNoWorkflowDefines(): void {
    $this->createNode('Unmoderated');

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('State "published" is not defined in the workflow for "page" content type.');

    $this->context->contentSetModerationState($this->context->contentGetNodeByTitle('page', 'Unmoderated'), 'published');
  }

  public function testRebuildAccessGrantsRebuildsTheGrantsOfTheNode(): void {
    $node = $this->createNode('Granted');

    $this->context->contentRebuildAccessGrants(new EntityStub('node', 'page', ['nid' => $node->id()]));
    $this->context->contentRebuildAccessGrantsAll();

    $this->assertSame([0], array_map(intval(...), \Drupal::database()->select('node_access', 'na')->fields('na', ['nid'])->distinct()->execute()->fetchCol()), 'Without a module implementing node grants, the single default grant row covers every node.');
  }

  /**
   * Tests that the published assertions pass on the status of the node.
   *
   * A failing assertion reports through the Mink session, which a kernel
   * test has none of, so the failures are covered by the Behat scenarios.
   */
  public function testPublishedAssertionsPassOnTheStatusOfTheNode(): void {
    $this->createNode('Published');
    $this->createNode('Unpublished', 0);

    $this->context->contentAssertPublishedWithTitle('page', 'Published');
    $this->context->contentAssertNotPublishedWithTitle('page', 'Unpublished');

    $this->addToAssertionCount(2);
  }

  public function testSetPathAliasReplacesTheExistingAlias(): void {
    $node = $this->createNode('Shared');
    $stub = $this->context->contentGetNodeByTitle('page', 'Shared');

    $this->context->contentSetPathAlias($stub, 'first-alias');
    $this->context->contentSetPathAlias($stub, ' /second-alias ');

    $aliases = \Drupal::entityTypeManager()->getStorage('path_alias')->loadByProperties(['path' => '/node/' . $node->id()]);
    $this->assertCount(1, $aliases, 'The second alias replaced the first rather than adding to it.');

    $alias = reset($aliases);
    $this->assertInstanceOf(PathAliasInterface::class, $alias);
    $this->assertSame('/second-alias', $alias->getAlias());
  }

  public function testSetPathAliasAcceptsStubWithoutBundle(): void {
    $node = $this->createNode('Shared');

    $this->context->contentSetPathAlias(new EntityStub('node', NULL, ['nid' => $node->id()]), 'about');

    $this->assertSame('/about', \Drupal::service('path_alias.repository')->lookupBySystemPath('/node/' . $node->id(), 'en')['alias'] ?? NULL);
  }

  public function testRebuildAccessGrantsFailsForMissingNode(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('No node with the id "999" exists.');

    $this->context->contentRebuildAccessGrants(new EntityStub('node', 'page', ['nid' => 999]));
  }

  /**
   * Creates a page node.
   *
   * @param string $title
   *   The title.
   * @param int $status
   *   The published status.
   * @param array<int, string> $keywords
   *   The values of 'field_keywords'.
   */
  protected function createNode(string $title, int $status = 1, array $keywords = []): NodeInterface {
    $node = Node::create(['type' => 'page', 'title' => $title, 'status' => $status, 'field_keywords' => $keywords]);
    $node->save();

    return $node;
  }

  protected function reloadNode(NodeInterface $node): NodeInterface {
    $reloaded = \Drupal::entityTypeManager()->getStorage('node')->loadUnchanged($node->id());
    $this->assertInstanceOf(NodeInterface::class, $reloaded);

    return $reloaded;
  }

}
