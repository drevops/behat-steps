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
   * @Given no menus:
   */
  public function menuDelete(TableNode $menusTable) {
    foreach ($menusTable->getColumn(0) as $name) {

      $menu = Menu::load($name);
      if ($menu) {
        $menu->delete();
      }
    }
  }

}
