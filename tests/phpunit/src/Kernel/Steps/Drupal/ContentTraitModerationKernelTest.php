<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Steps\Drupal;

use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Steps\Drupal\ContentTrait;
use Drupal\content_moderation\Plugin\WorkflowType\ContentModeration;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\workflows\Entity\Workflow;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for changing the moderation state of nodes through 'ContentTrait'.
 *
 * The 'editorial' workflow moderates the 'article' content type only, and
 * the 'review' workflow moderates no node type at all.
 */
#[CoversTrait(ContentTrait::class)]
#[Group('behat')]
#[RunTestsInSeparateProcesses]
class ContentTraitModerationKernelTest extends StepTraitKernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = ['system', 'user', 'field', 'text', 'filter', 'node', 'workflows', 'content_moderation'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('content_moderation_state');
    $this->installEntitySchema('workflow');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'filter', 'node']);

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();

    $this->createWorkflow('review', NULL);
    $this->createWorkflow('editorial', 'article');
  }

  /**
   * Tests that a node moves to a state its workflow defines.
   *
   * @param bool $has_bundle
   *   Whether the stub names the content type of the node.
   */
  #[DataProvider('dataProviderSetModerationStateMovesTheNodeToTheState')]
  public function testSetModerationStateMovesTheNodeToTheState(bool $has_bundle): void {
    $node = Node::create(['type' => 'article', 'title' => 'Draft article', 'moderation_state' => 'draft']);
    $node->save();

    $this->context->contentSetModerationState(new EntityStub('node', $has_bundle ? 'article' : NULL, ['nid' => $node->id()]), 'published');

    $reloaded = \Drupal::entityTypeManager()->getStorage('node')->loadUnchanged($node->id());
    $this->assertInstanceOf(NodeInterface::class, $reloaded);
    $this->assertSame('published', $reloaded->get('moderation_state')->value);
    $this->assertTrue($reloaded->isPublished());
  }

  public static function dataProviderSetModerationStateMovesTheNodeToTheState(): array {
    return [
      'a stub naming the content type' => [TRUE],
      'a stub carrying only the node ID' => [FALSE],
    ];
  }

  /**
   * Tests that a state no workflow of the content type defines is rejected.
   *
   * @param string $content_type
   *   The content type of the node.
   * @param string $state
   *   The state to move the node to.
   * @param string $expected_message
   *   The message the change throws.
   */
  #[DataProvider('dataProviderSetModerationStateRejectsAnUndefinedState')]
  public function testSetModerationStateRejectsAnUndefinedState(string $content_type, string $state, string $expected_message): void {
    $node = Node::create(['type' => $content_type, 'title' => 'A node']);
    $node->save();

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage($expected_message);

    $this->context->contentSetModerationState(new EntityStub('node', $content_type, ['nid' => $node->id()]), $state);
  }

  public static function dataProviderSetModerationStateRejectsAnUndefinedState(): array {
    return [
      'a state the workflow does not define' => ['article', 'archived', 'State "archived" is not defined in the workflow for "article" content type.'],
      'a content type no workflow moderates' => ['page', 'published', 'State "published" is not defined in the workflow for "page" content type.'],
    ];
  }

  public function testSetModerationStateFailsForMissingNode(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The node "999" does not exist.');

    $this->context->contentSetModerationState(new EntityStub('node', NULL, ['nid' => 999]), 'published');
  }

  public function testChangeModerationStateWithTitleMovesTheNewestNode(): void {
    Node::create(['type' => 'article', 'title' => 'Shared', 'moderation_state' => 'draft'])->save();
    $newest = Node::create(['type' => 'article', 'title' => 'Shared', 'moderation_state' => 'draft']);
    $newest->save();

    $this->context->contentChangeModerationStateWithTitle('article', 'Shared', 'published');

    $reloaded = \Drupal::entityTypeManager()->getStorage('node')->loadUnchanged($newest->id());
    $this->assertInstanceOf(NodeInterface::class, $reloaded);
    $this->assertSame('published', $reloaded->get('moderation_state')->value);
  }

  /**
   * Creates a content moderation workflow with a draft and a published state.
   *
   * @param string $id
   *   The workflow ID.
   * @param string|null $content_type
   *   The content type the workflow moderates, or NULL for none.
   */
  protected function createWorkflow(string $id, ?string $content_type): void {
    $workflow = Workflow::create([
      'id' => $id,
      'label' => ucfirst($id),
      'type' => 'content_moderation',
      'type_settings' => [
        'states' => [
          'draft' => ['label' => 'Draft', 'weight' => 0, 'published' => FALSE, 'default_revision' => FALSE],
          'published' => ['label' => 'Published', 'weight' => 1, 'published' => TRUE, 'default_revision' => TRUE],
        ],
        'transitions' => [
          'create_new_draft' => ['label' => 'Create draft', 'to' => 'draft', 'from' => ['draft', 'published'], 'weight' => 0],
          'publish' => ['label' => 'Publish', 'to' => 'published', 'from' => ['draft', 'published'], 'weight' => 1],
        ],
      ],
    ]);

    $type_plugin = $workflow->getTypePlugin();

    if (!$type_plugin instanceof ContentModeration) {
      throw new \LogicException('Expected a ContentModeration workflow type plugin.');
    }

    if ($content_type !== NULL) {
      $type_plugin->addEntityTypeAndBundle('node', $content_type);
    }

    $workflow->save();
  }

}
