<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Steps\Drupal;

use Behat\Gherkin\Node\TableNode;
use DrevOps\BehatSteps\Steps\Drupal\MenuTrait;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\system\Entity\Menu;
use Drupal\system\MenuInterface;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for creating, finding and deleting menus and menu links.
 */
#[CoversTrait(MenuTrait::class)]
#[Group('behat')]
#[RunTestsInSeparateProcesses]
class MenuTraitKernelTest extends StepTraitKernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = ['system', 'user', 'link', 'menu_link_content'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('menu_link_content');

    Menu::create(['id' => 'footer', 'label' => 'Footer'])->save();
  }

  public function testCreateLinkMultipleCreatesAnOrphanLinkAtTheTopLevel(): void {
    $this->context->menuCreateLinkMultiple('Footer', new TableNode([
      ['title', 'uri', 'parent'],
      ['Orphan', 'https://www.example.com', 'Missing parent'],
    ]));

    $link = $this->context->menuFindLinkByTitle('Orphan', 'Footer');

    $this->assertInstanceOf(MenuLinkContent::class, $link);
    $this->assertSame('', $link->getParentId());
  }

  #[DataProvider('dataProviderFindLinkByTitleFindsNothing')]
  public function testFindLinkByTitleFindsNothing(string $title, string $menu_name): void {
    $this->createLink('footer', 'About');

    $this->assertNull($this->context->menuFindLinkByTitle($title, $menu_name));
  }

  public static function dataProviderFindLinkByTitleFindsNothing(): array {
    return [
      'a menu that does not exist' => ['About', 'Header'],
      'a title that no link in the menu carries' => ['Contact', 'Footer'],
    ];
  }

  public function testFindLinkByTitleReturnsTheNewestLink(): void {
    Menu::create(['id' => 'header', 'label' => 'Header'])->save();

    $this->createLink('footer', 'Shared');
    $newest = $this->createLink('footer', 'Shared');
    $this->createLink('header', 'Shared');

    $this->assertSame($newest->id(), $this->context->menuFindLinkByTitle('Shared', 'Footer')?->id());
  }

  public function testDeleteLinkMultipleRemovesEveryLinkWithTheTitle(): void {
    Menu::create(['id' => 'header', 'label' => 'Header'])->save();

    $this->createLink('footer', 'Shared');
    $this->createLink('footer', 'Shared');
    $kept = $this->createLink('footer', 'Other');
    $elsewhere = $this->createLink('header', 'Shared');

    $this->context->menuDeleteLinkMultiple('Footer', new TableNode([['Shared']]));

    $remaining = array_keys(MenuLinkContent::loadMultiple());
    $this->assertEqualsCanonicalizing([$kept->id(), $elsewhere->id()], $remaining);
  }

  public function testDeleteLinkIgnoresMissingMenu(): void {
    $link = $this->createLink('footer', 'Shared');

    $this->context->menuDeleteLink('Header', ['title' => 'Shared']);

    $this->assertInstanceOf(MenuLinkContent::class, MenuLinkContent::load($link->id()));
  }

  public function testLoadMultipleLoadsTheMatchingMenus(): void {
    $first = $this->createMenu('shared', 'Shared');
    $second = $this->createMenu('shared_2', 'Shared');

    $menus = $this->context->menuLoadMultiple(['label' => 'Shared']);

    $this->assertLoadedSet([$first, $second], $menus, MenuInterface::class);
  }

  public function testLoadMultipleReturnsAnEmptyArrayWhenNothingMatches(): void {
    $this->assertSame([], $this->context->menuLoadMultiple(['label' => 'Shared']));
  }

  public function testFindByLabelReturnsTheMenuCreatedLast(): void {
    $this->createMenu('shared_10', 'Shared');
    $this->context->menuCreate(['id' => 'shared_3', 'label' => 'Shared']);
    $this->context->menuCreate(['id' => 'shared_2', 'label' => 'Shared']);

    $this->assertSame('shared_2', $this->context->menuFindByLabel('Shared')?->id());
  }

  public function testFindByLabelReturnsTheLastSiteMenuInNaturalOrder(): void {
    $this->createMenu('shared', 'Shared');
    $this->createMenu('shared_10', 'Shared');
    $this->createMenu('shared_2', 'Shared');

    $this->assertSame('shared_10', $this->context->menuFindByLabel('Shared')?->id());
  }

  public function testDeleteRemovesEveryMenuWithTheLabel(): void {
    $this->createMenu('shared', 'Shared');
    $this->createMenu('shared_2', 'Shared');

    $this->context->menuDelete('Shared');

    $this->assertSame(['footer'], array_keys(Menu::loadMultiple()));
  }

  protected function createMenu(string $id, string $label): MenuInterface {
    $menu = Menu::create(['id' => $id, 'label' => $label]);
    $menu->save();

    return $menu;
  }

  protected function createLink(string $menu_name, string $title): MenuLinkContentInterface {
    $link = MenuLinkContent::create(['title' => $title, 'menu_name' => $menu_name, 'link' => ['uri' => 'https://www.example.com']]);
    $link->save();

    return $link;
  }

}
