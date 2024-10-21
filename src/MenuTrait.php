<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\system\Entity\Menu;
use Drupal\system\MenuInterface;

/**
 * Trait MenuTrait.
 *
 * Menu-related steps.
 *
 * @package DrevOps\BehatSteps
 */
trait MenuTrait {

  /**
   * Menus.
   *
   * @var \Drupal\system\Entity\Menu[]
   */
  protected $menus = [];

  /**
   * Menu links.
   *
   * @var \Drupal\menu_link_content\Entity\MenuLinkContent[]
   */
  protected $menuLinks = [];

  /**
   * Remove menu by menu name.
   *
   * Provide menu labels in the following format:
   * | Fish Menu    |
   * | ...          |
   *
   * @Given no menus:
   */
  public function menuDelete(TableNode $table): void {
    foreach ($table->getColumn(0) as $label) {
      $menu = $this->loadMenuByLabel($label);
      if ($menu instanceof MenuInterface) {
        $menu->delete();
      }
    }
  }

  /**
   * Create a menu if one does not exist.
   *
   * Provide menu data in the following format:
   *
   * | label        | description     |
   * | Fish Menu    | Menu of fish    |
   * | ...          | ...             |
   *
   * @Given menus:
   */
  public function menuCreate(TableNode $table): void {
    foreach ($table->getHash() as $menu_hash) {
      if (empty($menu_hash['id'])) {
        // Create menu id if one was not provided.
        $menu_id = strtolower((string) $menu_hash['label']);
        $menu_id = preg_replace('/[^a-z0-9_]+/', '_', $menu_id);
        $menu_id = preg_replace('/_+/', '_', (string) $menu_id);
        $menu_hash['id'] = $menu_id;
      }

      $menu = Menu::create($menu_hash);
      $menu->save();

      $this->menus[] = $menu;
    }
  }

  /**
   * Remove menu links by title.
   *
   * Provide menu link titles in the following format:
   * | Test Menu    |
   * | ...          |
   *
   * @Given no :menu_name menu_links:
   */
  public function menuLinksDelete(string $menu_name, TableNode $table): void {
    foreach ($table->getColumn(0) as $title) {
      $menu_link = $this->loadMenuLinkByTitle($title, $menu_name);
      if ($menu_link instanceof MenuLinkContent) {
        $menu_link->delete();
      }
    }
  }

  /**
   * Create menu links.
   *
   * Provide menu link data in the following format:
   *
   * | title         | enabled | uri                     | parent             |
   * | Parent Link   | 1       | https://www.example.com |                    |
   * | Child Link    | 1       | https://www.example.com | Parent Link        |
   * | ...           | ...     | ...                     | ...                |
   *
   * @Given :menu_name menu_links:
   */
  public function menuLinksCreate(string $menu_name, TableNode $table): void {
    $menu = $this->loadMenuByLabel($menu_name);

    if (!$menu instanceof MenuInterface) {
      throw new \RuntimeException(sprintf('Menu "%s" not found', $menu_name));
    }

    foreach ($table->getHash() as $menu_link_hash) {
      $menu_link_hash['menu_name'] = $menu->id();
      // Add uri to correct property.
      $menu_link_hash['link']['uri'] = $menu_link_hash['uri'];
      unset($menu_link_hash['uri']);
      // Create parent property in format required.
      if (!empty($menu_link_hash['parent'])) {
        $parent_link = $this->loadMenuLinkByTitle($menu_link_hash['parent'], $menu_name);
        $menu_link_hash['parent'] = 'menu_link_content:' . $parent_link->uuid();
      }
      else {
        unset($menu_link_hash['parent']);
      }
      $menu_link = MenuLinkContent::create($menu_link_hash);
      $menu_link->save();
      $this->menuLinks[] = $menu_link;
    }
  }

  /**
   * Remove all menu items after scenario run.
   *
   * @AfterScenario
   */
  public function menuCleanAll(AfterScenarioScope $scope): void {
    // Allow to skip this by adding a tag.
    if ($scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__)) {
      return;
    }

    foreach ($this->menuLinks as $menu_link) {
      $menu_link->delete();
    }
    $this->menuLinks = [];

    foreach ($this->menus as $menu) {
      $menu->delete();
    }
    $this->menus = [];
  }

  /**
   * Gets a menu by label.
   *
   * @param string $label
   *   The label of the menu.
   *
   * @return \Drupal\system\MenuInterface|null
   *   The menu or NULL if not found.
   */
  protected function loadMenuByLabel(string $label): ?MenuInterface {
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = \Drupal::getContainer()->get('entity_type.manager');
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
   * Gets a menu link by title and menu name.
   *
   * @param string $title
   *   The title of the menu link.
   * @param string $menu_name
   *   The name of the menu.
   *
   * @return \Drupal\menu_link_content\Entity\MenuLinkContent|null
   *   The menu link or NULL if not found.
   */
  protected function loadMenuLinkByTitle(string $title, string $menu_name): ?MenuLinkContent {
    $menu = $this->loadMenuByLabel($menu_name);

    if (!$menu instanceof MenuInterface) {
      return NULL;
    }

    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = \Drupal::getContainer()->get('entity_type.manager');

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

}
