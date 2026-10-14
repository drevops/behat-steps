<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Drupal\Core;

use DrevOps\BehatSteps\Backend\Drupal\Core\Core;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\pathauto\Entity\PathautoPattern;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for the path alias method on Core with pathauto enabled.
 */
#[CoversClass(Core::class)]
#[Group('core')]
#[RunTestsInSeparateProcesses]
class CorePathAliasPathautoKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = ['system', 'user', 'node', 'field', 'text', 'filter', 'path', 'path_alias', 'token', 'pathauto'];

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
    $this->installEntitySchema('path_alias');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'user', 'node', 'filter', 'pathauto']);

    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    PathautoPattern::create(['id' => 'content', 'type' => 'canonical_entities:node', 'pattern' => '/content/[node:title]'])->save();

    $this->core = new Core($this->root);
  }

  public function testSetEntityPathAliasKeepsTheAliasThroughALaterSave(): void {
    $node = Node::create(['type' => 'page', 'title' => 'Generated']);
    $node->save();
    $this->assertSame('/content/generated', $this->findAlias($node), 'Pathauto generated an alias on create.');

    $this->core->setEntityPathAlias(new EntityStub('node', 'page', ['nid' => $node->id()]), 'kept');
    $this->assertSame('/kept', $this->findAlias($node));

    $reloaded = \Drupal::entityTypeManager()->getStorage('node')->loadUnchanged($node->id());
    $this->assertInstanceOf(NodeInterface::class, $reloaded);
    $reloaded->setTitle('Renamed')->save();

    $this->assertSame('/kept', $this->findAlias($node), 'Pathauto left the alias in place on a later save.');
  }

  /**
   * Finds the alias of a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   */
  protected function findAlias(NodeInterface $node): ?string {
    return \Drupal::service('path_alias.repository')->lookupBySystemPath('/node/' . $node->id(), 'en')['alias'] ?? NULL;
  }

}
