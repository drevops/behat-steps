<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Drupal;

use ConsumerProject\Backend\Alias\AuthorIdAlias;
use ConsumerProject\Backend\Alias\HeadlineAlias;
use DrevOps\BehatSteps\Backend\Drupal\Core\Core;
use DrevOps\BehatSteps\Backend\Drupal\DrupalBackend;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Tests\Kernel\Fixtures\BootedDrupalBackend;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for creation aliases registered through 'DrupalBackend'.
 *
 * Registers consumer aliases on the backend and parses and creates nodes
 * through it, so the aliases run in the Core that saves the node.
 */
#[CoversClass(DrupalBackend::class)]
#[Group('backends')]
#[Group('drupal')]
#[Group('aliases')]
#[RunTestsInSeparateProcesses]
class DrupalBackendCreationAliasesKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = ['system', 'user', 'node', 'field', 'text', 'filter'];

  /**
   * The backend under test.
   */
  protected DrupalBackend $backend;

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

    $this->backend = new BootedDrupalBackend($this->root, 'default');
    $this->backend->setCore(new Core($this->root));
  }

  public function testRegisteredAliasResolvesWhenTheNodeIsCreated(): void {
    $this->backend->registerCreationAlias(new HeadlineAlias());

    $stub = $this->backend->createNode(new EntityStub('node', 'article', ['headline' => 'Lab report']));

    $node = Node::load($stub->getValue('nid'));
    $this->assertInstanceOf(NodeInterface::class, $node);
    $this->assertSame('Lab report', $node->getTitle());
    $this->assertFalse($stub->hasValue('headline'));
  }

  public function testRegisteredAliasColumnPassesTheFieldParse(): void {
    $this->backend->registerCreationAlias(new HeadlineAlias());
    $stub = new EntityStub('node', 'article', ['headline' => 'Lab report']);

    $this->backend->parseEntityFields($stub);
    $this->backend->createNode($stub);

    $node = Node::load($stub->getValue('nid'));
    $this->assertInstanceOf(NodeInterface::class, $node);
    $this->assertSame('Lab report', $node->getTitle());
  }

  public function testUnregisteredColumnFailsTheFieldParse(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The field "headline" does not exist on entity type "node".');

    $this->backend->parseEntityFields(new EntityStub('node', 'article', ['headline' => 'Lab report']));
  }

  public function testRegisteredAliasReplacesTheShippedOne(): void {
    $author = User::create(['name' => 'editor', 'status' => 1]);
    $author->save();

    $this->backend->registerCreationAlias(new AuthorIdAlias());

    // The shipped alias would look the ID up as a username and fail.
    $stub = $this->backend->createNode(new EntityStub('node', 'article', ['title' => 'Lab report', 'author' => (string) $author->id()]));

    $node = Node::load($stub->getValue('nid'));
    $this->assertInstanceOf(NodeInterface::class, $node);
    $this->assertSame((int) $author->id(), (int) $node->getOwnerId());
  }

}
