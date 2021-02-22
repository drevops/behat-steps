<?php

namespace IntegratedExperts\BehatSteps\D8;

use Behat\Gherkin\Node\TableNode;
use Drupal\menu_link_content\Entity\MenuLinkContent;

/**
 * Trait MenuLinkContentTrait.
 *
 * @package IntegratedExperts\BehatSteps\D8
 */
trait MenuLinkContentTrait {

  /**
   * Remove menu.
   *
   * Provide menu data in the following format:
   *
   * | id        |
   * | 9991      |
   * | ...       |
   *
   * @Given no menu_links:
   */
  public function menuLinksDelete(TableNode $table) {
    foreach ($table->getColumn(0) as $id) {
      $menu_link = MenuLinkContent::load($id);
      if ($menu_link) {
        $menu_link->delete();
      }
    }
  }

  /**
   * Create a menu if one does not exist.
   *
   * Provide menu data in the following format:
   *
   * | id    | uuid   | title        | description     | menu_name | enabled |
   * link__uri               | parent                   |
   * | 99991 | <uuid> | Parent Link  | Link to fish    | fish_menu | 1       |
   * https://www.example.com | null                     |
   * | 99992 | <uuid> | Child Link   | Link to fish    | fish_menu | 1       |
   * https://www.example.com | menu_link_content:<uuid> |
   * | ...   | ...    | ...          | ...             | ...       | ...     |
   * ...                     | ...                      |
   *
   * Only the 'id' field is required.
   *
   * @Given menu_links:
   */
  public function menuLinksCreate(TableNode $table) {
    $this->menuLinksDelete($table);
    foreach ($table->getHash() as $menu_hash) {
      $menu_hash['link']['uri'] = $menu_hash['link__uri'];
      unset($menu_hash['link__uri']);
      $menu = MenuLinkContent::create($menu_hash);
      $menu->save();
    }
  }

}
