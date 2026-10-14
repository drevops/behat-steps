<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Drupal\Core;

use DrevOps\BehatSteps\Backend\Drupal\Core\Core;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\path_alias\PathAliasInterface;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for the path alias method on Core.
 */
#[CoversClass(Core::class)]
#[Group('core')]
#[RunTestsInSeparateProcesses]
class CorePathAliasMethodsKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = ['system', 'user', 'node', 'field', 'text', 'filter', 'path', 'path_alias'];

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
    $this->installConfig(['system', 'user', 'node', 'filter']);

    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();

    $this->core = new Core($this->root);
  }

  public function testSetEntityPathAliasReplacesTheExistingAlias(): void {
    $node = $this->createNode();
    $stub = new EntityStub('node', 'page', ['nid' => $node->id()]);

    $this->core->setEntityPathAlias($stub, 'first-alias');
    $this->core->setEntityPathAlias($stub, ' /second-alias ');

    $aliases = \Drupal::entityTypeManager()->getStorage('path_alias')->loadByProperties(['path' => '/node/' . $node->id()]);
    $this->assertCount(1, $aliases, 'The second alias replaced the first rather than adding to it.');

    $alias = reset($aliases);
    $this->assertInstanceOf(PathAliasInterface::class, $alias);
    $this->assertSame('/second-alias', $alias->getAlias());
  }

  public function testSetEntityPathAliasTakesASavedStub(): void {
    $node = $this->createNode();

    $this->core->setEntityPathAlias((new EntityStub('node'))->markSaved($node), 'saved');

    $this->assertSame('/saved', \Drupal::service('path_alias.repository')->lookupBySystemPath('/node/' . $node->id(), 'en')['alias'] ?? NULL);
  }

  /**
   * Tests that an alias naming no path is rejected.
   *
   * @param string $alias
   *   The alias to set.
   */
  #[DataProvider('dataProviderSetEntityPathAliasRejectsAnAliasNamingNoPath')]
  public function testSetEntityPathAliasRejectsAnAliasNamingNoPath(string $alias): void {
    $node = $this->createNode();

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage(sprintf('Cannot set the path alias "%s" because it names no path.', $alias));

    $this->core->setEntityPathAlias(new EntityStub('node', 'page', ['nid' => $node->id()]), $alias);
  }

  public static function dataProviderSetEntityPathAliasRejectsAnAliasNamingNoPath(): \Iterator {
    yield 'empty' => [''];
    yield 'whitespace' => ['  '];
    yield 'a slash' => ['/'];
    yield 'slashes and whitespace' => [' // '];
  }

  public function testSetEntityPathAliasRejectsAMissingEntity(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Cannot update the entity of type "node" with the id "999" because it does not exist.');

    $this->core->setEntityPathAlias(new EntityStub('node', 'page', ['nid' => 999]), 'missing');
  }

  public function testSetEntityPathAliasRejectsAnEntityWithoutAPathField(): void {
    $account = User::create(['name' => 'alias-less', 'status' => 1]);
    $account->save();

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage(sprintf('Cannot read the entity of type "user" with the id "%s" because it has no field "path".', $account->id()));

    $this->core->setEntityPathAlias(new EntityStub('user', NULL, ['uid' => $account->id()]), 'people');
  }

  public function testSetEntityPathAliasRejectsAStubWithoutAnId(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Cannot resolve an entity of type "node" from a stub without the id key "nid" set.');

    $this->core->setEntityPathAlias(new EntityStub('node', 'page'), 'nowhere');
  }

  /**
   * Creates a published page.
   */
  protected function createNode(): NodeInterface {
    $node = Node::create(['type' => 'page', 'title' => 'Aliased']);
    $node->save();

    return $node;
  }

}
