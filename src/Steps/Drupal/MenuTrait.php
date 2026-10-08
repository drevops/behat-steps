<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Gherkin\Node\TableNode;
use Behat\Step\Given;
use DrevOps\BehatSteps\Backend\Capability\CoreCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\ModuleCapabilityInterface;
use DrevOps\BehatSteps\Behat\Prerequisite\Prerequisite;
use DrevOps\BehatSteps\Helper\Drupal\EntityLifecycleTrait;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\system\Entity\Menu;
use Drupal\system\MenuInterface;

/**
 * Manage Drupal menus and menu links.
 *
 * - Create and remove menus by label.
 * - Create and remove menu links, including parent-child hierarchies.
 * - Created menus and menu links are automatically removed at the end of the scenario.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
 */
trait MenuTrait {

  use EntityLifecycleTrait;

  /**
   * Remove a single menu by its label if it exists.
   *
   * @param string $menu_name
   *   The label of the menu to remove.
   *
   * @code
   *   Given the menu "Test Menu" does not exist
   * @endcode
   */
  #[Given('the menu :menu_name does not exist')]
  public function menuDelete(string $menu_name): void {
    $menu = $this->menuFindByLabel($menu_name);
    if ($menu instanceof MenuInterface) {
      $menu->delete();
    }
  }

  /**
   * Create menus.
   *
   * @code
   * Given the following menus exist:
   *   | label            | description                    |
   *   | Footer Menu     | Links displayed in the footer  |
   *   | Secondary Menu  | Secondary navigation menu      |
   * @endcode
   */
  #[Given('the following menus exist:')]
  public function menuCreateMultiple(TableNode $table): void {
    $this->backendFor(CoreCapabilityInterface::class);

    foreach ($table->getHash() as $menu_hash) {
      $this->menuCreate($menu_hash);
    }
  }

  /**
   * Remove menu links by title.
   *
   * @code
   * Given the following menu links do not exist in the menu "Main navigation":
   *   | About Us     |
   *   | Contact      |
   * @endcode
   */
  #[Given('the following menu links do not exist in the menu :menu_name:')]
  public function menuDeleteLinkMultiple(string $menu_name, TableNode $table): void {
    $this->backendFor(CoreCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    foreach ($table->getColumn(0) as $title) {
      $this->menuFindLinkByTitle($title, $menu_name)?->delete();
    }
  }

  /**
   * Create menu links.
   *
   * @code
   * Given the following menu links exist in the menu "Main navigation":
   *   | title           | enabled | uri                     | parent       |
   *   | Products        | 1       | /products               |              |
   *   | Latest Products | 1       | /products/latest        | Products     |
   * @endcode
   */
  #[Given('the following menu links exist in the menu :menu_name:')]
  public function menuCreateLinkMultiple(string $menu_name, TableNode $table): void {
    $this->backendFor(CoreCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    $menu = $this->menuFindByLabel($menu_name);

    if (!$menu instanceof MenuInterface) {
      throw new \RuntimeException(sprintf('Menu "%s" was not found.', $menu_name));
    }

    foreach ($table->getHash() as $menu_link_hash) {
      $this->menuCreateLink($menu, $menu_link_hash);
    }
  }

  /**
   * Create a menu.
   *
   * The menu is removed after the scenario.
   *
   * @param array<string, string> $values
   *   The menu values. A menu without an "id" takes 1 derived from its
   *   "label".
   *
   * @return \Drupal\system\MenuInterface
   *   The menu.
   */
  public function menuCreate(array $values): MenuInterface {
    $this->backendFor(CoreCapabilityInterface::class);

    if (empty($values['id'])) {
      $menu_id = strtolower((string) $values['label']);
      $menu_id = preg_replace('/[^a-z0-9_]+/', '_', $menu_id);
      $menu_id = preg_replace('/_+/', '_', (string) $menu_id);
      $values['id'] = $menu_id;
    }

    $menu = Menu::create($values);
    $menu->save();

    $this->entityLifecycleRegister($menu);

    return $menu;
  }

  /**
   * Create a link in a menu.
   *
   * The link is removed after the scenario.
   *
   * @param \Drupal\system\MenuInterface $menu
   *   The menu.
   * @param array<string, string> $values
   *   The link values. A "uri" value becomes the link URI, and a "parent"
   *   value is the title of a link in the same menu.
   *
   * @return \Drupal\menu_link_content\Entity\MenuLinkContent
   *   The menu link.
   */
  public function menuCreateLink(MenuInterface $menu, array $values): MenuLinkContent {
    $values['menu_name'] = $menu->id();

    if (isset($values['uri'])) {
      $values['link'] = [];
      $values['link']['uri'] = (string) $values['uri'];
      unset($values['uri']);
    }

    if (!empty($values['parent']) && is_string($values['parent'])) {
      $parent_link = $this->menuFindLinkByTitle($values['parent'], (string) $menu->label());
      if ($parent_link instanceof MenuLinkContent) {
        $values['parent'] = 'menu_link_content:' . $parent_link->uuid();
      }
      else {
        unset($values['parent']);
      }
    }
    else {
      unset($values['parent']);
    }

    $menu_link = MenuLinkContent::create($values);
    $menu_link->save();

    $this->entityLifecycleRegister($menu_link);

    return $menu_link;
  }

  /**
   * Find a menu by its label.
   *
   * @param string $label
   *   The label of the menu.
   *
   * @return \Drupal\system\MenuInterface|null
   *   The menu or NULL if not found.
   */
  public function menuFindByLabel(string $label): ?MenuInterface {
    $this->backendFor(CoreCapabilityInterface::class);

    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = \Drupal::entityTypeManager();
    $menu_ids = $entity_type_manager->getStorage('menu')->getQuery()
      ->accessCheck(FALSE)
      ->condition('label', $label)
      ->execute();

    if (empty($menu_ids)) {
      return NULL;
    }

    $menu_id = reset($menu_ids);

    return Menu::load($menu_id);
  }

  /**
   * Find a menu link by title and menu name.
   *
   * @param string $title
   *   The title of the menu link.
   * @param string $menu_name
   *   The name of the menu.
   *
   * @return \Drupal\menu_link_content\Entity\MenuLinkContent|null
   *   The menu link or NULL if not found.
   */
  public function menuFindLinkByTitle(string $title, string $menu_name): ?MenuLinkContent {
    $this->backendFor(CoreCapabilityInterface::class);

    $menu = $this->menuFindByLabel($menu_name);

    if (!$menu instanceof MenuInterface) {
      return NULL;
    }

    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = \Drupal::entityTypeManager();

    $menu_link_ids = $entity_type_manager->getStorage('menu_link_content')->getQuery()
      ->accessCheck(FALSE)
      ->condition('menu_name', $menu->id())
      ->condition('title', $title)
      ->execute();

    if (empty($menu_link_ids)) {
      return NULL;
    }

    $menu_link_id = reset($menu_link_ids);

    return MenuLinkContent::load($menu_link_id);
  }

  /**
   * Declares the prerequisites this trait asserts.
   *
   * @return array<int, \DrevOps\BehatSteps\Behat\Prerequisite\Prerequisite>
   *   The prerequisites this trait declares.
   */
  protected function menuPrerequisites(): array {
    return [
      Prerequisite::capability(CoreCapabilityInterface::class),
      Prerequisite::check(static fn(ModuleCapabilityInterface $backend): bool => $backend->moduleIsEnabled('menu_link_content'), 'the core "menu_link_content" module is enabled, for the menu link steps'),
    ];
  }

}
