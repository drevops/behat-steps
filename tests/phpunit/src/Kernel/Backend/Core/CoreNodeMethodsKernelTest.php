<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Core;

use DrevOps\BehatSteps\Backend\Core\Core;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Backend\Exception\CreationAliasResolutionException;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for node-related methods on Core via the backend.
 *
 * Exercises Core::createNode and Core::deleteNode end-to-end: bundle
 * validation, the optional 'author' to 'uid' remapping, expandEntityFields
 * (a no-op here, with no fields attached), save, and delete.
 */
#[CoversClass(Core::class)]
#[Group('core')]
#[RunTestsInSeparateProcesses]
class CoreNodeMethodsKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'text',
    'filter',
  ];

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

  /**
   * Tests the node lifecycle: create with author mapping, then delete.
   */
  public function testNodeLifecycle(): void {
    $author = User::create(['name' => 'article_author', 'status' => 1]);
    $author->save();

    $stub = new EntityStub('node', 'article', [
      'title' => 'Hello world',
      'author' => 'article_author',
    ]);

    $created = $this->core->createNode($stub);

    $this->assertSame($stub, $created, 'createNode returns the same stub.');
    $this->assertNotEmpty($created->getValue('nid'), 'createNode populated nid.');
    $this->assertTrue($created->isSaved(), 'createNode marked the stub saved.');
    $node = Node::load($created->getValue('nid'));
    $this->assertInstanceOf(Node::class, $node);
    $this->assertSame('Hello world', $node->getTitle());
    $this->assertSame((int) $author->id(), (int) $node->getOwnerId(), 'author mapped to uid.');

    $this->core->deleteNode($created);
    $this->assertNull(Node::load($created->getValue('nid')));
  }

  public function testCreateNodeRejectsUnknownBundle(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Cannot create content because provided content type bogus does not exist.');

    $this->core->createNode(new EntityStub('node', 'bogus', ['title' => 'Nope']));
  }

  public function testCreateNodeRejectsMissingType(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage("Cannot create content because it is missing the required property 'type'.");

    $this->core->createNode(new EntityStub('node', NULL, ['title' => 'Nope']));
  }

  public function testCreateNodeRejectsUnknownAuthor(): void {
    $this->expectException(CreationAliasResolutionException::class);
    $this->expectExceptionMessageMatches('/user "auther".*does not exist/');

    $this->core->createNode(new EntityStub('node', 'article', [
      'title' => 'Hello',
      'author' => 'auther',
    ]));
  }

}
