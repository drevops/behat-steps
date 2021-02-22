<?php

namespace IntegratedExperts\BehatSteps\D8;

use Behat\Gherkin\Node\TableNode;
use Drupal\system\Entity\Menu;

/**
 * Trait MenuTrait.
 *
 * @package IntegratedExperts\BehatSteps\D8
 */
trait MenuTrait {

  /**
   * Remove menu.
   *
   * Provide menu data in the following format:
   *
   * | id        |
   * | menu_fish |
   * | ...       |
   *
   * @Given no menus:
   */
  public function menuDelete(TableNode $table) {
    foreach ($table->getColumn(0) as $name) {
      $menu = Menu::load($name);
      if ($menu) {
        $menu->delete();
      }
    }
  }

  /**
   * Create a menu if one does not exist.
   *
   * Provide menu data in the following format:
   *
   * | id        | label        | description     |
   * | menu_fish | Fish Menu   | Menu of fish    |
   * | ...       | ...         | ...             |
   *
   * Only the 'id' field is required.
   *
   * @Given menus:
   */
  public function menuCreate(TableNode $table) {
    $this->menuDelete($table);
    foreach ($table->getHash() as $menu_hash) {
      $menu = Menu::create($menu_hash);
      $menu->save();
    }
  }

}
