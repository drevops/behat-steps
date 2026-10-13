<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Drupal\Core;

use DrevOps\BehatSteps\Backend\Drupal\Core\Core;
use Drupal\content_moderation\Plugin\WorkflowType\ContentModeration;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\workflows\Entity\Workflow;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for the workflow capability method on Core.
 *
 * An editorial workflow with the 'draft' and 'published' states applies to
 * the 'article' content type only.
 */
#[CoversClass(Core::class)]
#[Group('core')]
#[RunTestsInSeparateProcesses]
class CoreWorkflowMethodsKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = ['system', 'user', 'field', 'text', 'filter', 'node', 'workflows', 'content_moderation'];

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
    $this->installEntitySchema('content_moderation_state');
    $this->installEntitySchema('workflow');
    $this->installConfig(['system', 'filter', 'node']);

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();

    $workflow = Workflow::create([
      'id' => 'editorial',
      'label' => 'Editorial',
      'type' => 'content_moderation',
      'type_settings' => [
        'states' => [
          'draft' => ['label' => 'Draft', 'weight' => 0, 'published' => FALSE, 'default_revision' => FALSE],
          'published' => ['label' => 'Published', 'weight' => 1, 'published' => TRUE, 'default_revision' => TRUE],
        ],
        'transitions' => [
          'create_new_draft' => ['label' => 'Create draft', 'to' => 'draft', 'from' => ['draft'], 'weight' => 0],
          'publish' => ['label' => 'Publish', 'to' => 'published', 'from' => ['draft'], 'weight' => 1],
        ],
      ],
    ]);
    $type_plugin = $workflow->getTypePlugin();

    if (!$type_plugin instanceof ContentModeration) {
      throw new \LogicException('Expected a ContentModeration workflow type plugin.');
    }

    $type_plugin->addEntityTypeAndBundle('node', 'article');
    $workflow->save();

    $this->core = new Core($this->root);
  }

  /**
   * Tests that a state is found only on a workflow covering the bundle.
   *
   * @param string $entity_type
   *   The entity type id.
   * @param string $bundle
   *   The bundle.
   * @param string $state
   *   The state id.
   * @param bool $expected
   *   Whether the workflow defines the state for the bundle.
   */
  #[DataProvider('dataProviderHasWorkflowState')]
  public function testHasWorkflowState(string $entity_type, string $bundle, string $state, bool $expected): void {
    $this->assertSame($expected, $this->core->hasWorkflowState($entity_type, $bundle, $state));
  }

  public static function dataProviderHasWorkflowState(): \Iterator {
    yield 'a state of the workflow covering the bundle' => ['node', 'article', 'published', TRUE];
    yield 'another state of the same workflow' => ['node', 'article', 'draft', TRUE];
    yield 'a state the workflow does not define' => ['node', 'article', 'archived', FALSE];
    yield 'a bundle no workflow covers' => ['node', 'page', 'published', FALSE];
    yield 'an entity type no workflow covers' => ['taxonomy_term', 'article', 'published', FALSE];
  }

}
