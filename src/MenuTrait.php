<?php

namespace DrevOps\BehatSteps;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\system\Entity\Menu;

/**
 * Trait MenuTrait.
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
  public function menuDelete(TableNode $table) {
    foreach ($table->getColumn(0) as $label) {
      try {
        $menu = $this->loadMenuByLabel($label);
        $menu->delete();
      }
      catch (\Exception $e) {
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
  public function menuCreate(TableNode $table) {
    foreach ($table->getHash() as $menu_hash) {
      if (empty($menu_hash['id'])) {
        // Create menu id if one not provided.
        $menu_id = strtolower($menu_hash['label']);
        $menu_id = preg_replace('/[^a-z0-9_]+/', '_', $menu_id);
        $menu_id = preg_replace('/_+/', '_', $menu_id);
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
  public function menuLinksDelete($menu_name, TableNode $table) {
    foreach ($table->getColumn(0) as $title) {
      $menu_link = $this->loadMenuLinkByTitle($title, $menu_name);
      if ($menu_link) {
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
  public function menuLinksCreate($menu_name, TableNode $table) {
    $menu = $this->loadMenuByLabel($menu_name);
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
   * @AfterScenario
   */
  public function menuCleanAll(AfterScenarioScope $scope) {
    // Allow to skip this by adding a tag.
    if ($scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__)) {
      return;
    }
    // Clean up created menus.
    foreach ($this->menus as $menu) {
      try {
        $menu->delete();
      }
      catch (\Exception $exception) {
        // Ignore the exception and move on.
        continue;
      }
    }
    // Clean up menu links.
    foreach ($this->menuLinks as $menu_link) {
      try {
        $menu_link->delete();
      }
      catch (\Exception $exception) {
        // Ignore the exception and move on.
        continue;
      }
    }

    $this->menuLinks = [];

    $this->menus = [];
  }

  /**
   * Gets a menu by label.
   */
  protected function loadMenuByLabel($label) {
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = \Drupal::getContainer()->get('entity_type.manager');
    $query = $entity_type_manager->getStorage('menu')->getQuery();
    $query->condition('label', $label);
    $menu_ids = $query->execute();
    $menu_id = reset($menu_ids);
    if ($menu_id === FALSE) {
      throw new \Exception(sprintf('Could not find the %s menu.', $label));
    }

    return Menu::load($menu_id);
  }

  /**
   * Gets a menu link by title and menu name.
   */
  protected function loadMenuLinkByTitle($title, $menu_name) {
    $menu = $this->loadMenuByLabel($menu_name);
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = \Drupal::getContainer()->get('entity_type.manager');
    $query = $entity_type_manager->getStorage('menu_link_content')->getQuery();
    $menu_link_ids = $query->condition('menu_name', $menu->id())->condition('title', $title)->execute();
    $menu_link_id = reset($menu_link_ids);
    if ($menu_link_id === FALSE) {
      throw new \Exception(sprintf('Could not find the %s menu link in %s menu.', $title, $menu_name));
    }

    return MenuLinkContent::load($menu_link_id);
  }

}
