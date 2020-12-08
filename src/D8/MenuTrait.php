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
   * @code
   * @Given no menus:
   * | main_menu   |
   * | footer_menu |
   * @endcode
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

}
