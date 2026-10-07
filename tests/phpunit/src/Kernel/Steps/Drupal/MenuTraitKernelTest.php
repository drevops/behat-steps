<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Kernel\Steps\Drupal;

use Behat\Gherkin\Node\TableNode;
use DrevOps\BehatSteps\Steps\Drupal\MenuTrait;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\system\Entity\Menu;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for creating and finding menu links through 'MenuTrait'.
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

  public function testCreateLinkMultipleCreatesALinkWithAMissingParentAtTheTopLevel(): void {
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
    MenuLinkContent::create(['title' => 'About', 'menu_name' => 'footer', 'link' => ['uri' => 'https://www.example.com']])->save();

    $this->assertNull($this->context->menuFindLinkByTitle($title, $menu_name));
  }

  public static function dataProviderFindLinkByTitleFindsNothing(): array {
    return [
      'a menu that does not exist' => ['About', 'Header'],
      'a title that no link in the menu carries' => ['Contact', 'Footer'],
    ];
  }

}
