<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Backend\Drupal\Core;

use DrevOps\BehatSteps\Backend\Drupal\Core\Core;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use Drupal\KernelTests\KernelTestBase;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for the menu link method on Core.
 */
#[CoversClass(Core::class)]
#[Group('core')]
#[RunTestsInSeparateProcesses]
class CoreMenuLinkMethodsKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = ['system', 'user', 'link', 'menu_link_content'];

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
    $this->installEntitySchema('menu_link_content');

    $this->core = new Core($this->root);
  }

  public function testGetMenuLinkPluginIdNamesTheLinkByItsUuid(): void {
    $link = $this->createLink();

    $this->assertSame('menu_link_content:' . $link->uuid(), $this->core->getMenuLinkPluginId(new EntityStub('menu_link_content', NULL, ['id' => $link->id()])));
  }

  public function testGetMenuLinkPluginIdTakesASavedStub(): void {
    $link = $this->createLink();

    $this->assertSame('menu_link_content:' . $link->uuid(), $this->core->getMenuLinkPluginId((new EntityStub('menu_link_content'))->markSaved($link)));
  }

  public function testGetMenuLinkPluginIdRejectsAMissingLink(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('No menu link with the id "999" exists.');

    $this->core->getMenuLinkPluginId(new EntityStub('menu_link_content', NULL, ['id' => 999]));
  }

  public function testGetMenuLinkPluginIdRejectsAnEntityOfAnotherType(): void {
    $account = User::create(['name' => 'not-a-link', 'status' => 1]);
    $account->save();

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage(sprintf('No menu link with the id "%s" exists.', $account->id()));

    $this->core->getMenuLinkPluginId(new EntityStub('user', NULL, ['uid' => $account->id()]));
  }

  public function testGetMenuLinkPluginIdRejectsAStubWithoutAnId(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Cannot resolve an entity of type "menu_link_content" from a stub without the id key "id" set.');

    $this->core->getMenuLinkPluginId(new EntityStub('menu_link_content'));
  }

  /**
   * Creates a link in the main menu.
   */
  protected function createLink(): MenuLinkContent {
    $link = MenuLinkContent::create(['title' => 'Products', 'menu_name' => 'main', 'link' => ['uri' => 'internal:/products']]);
    $link->save();

    return $link;
  }

}
