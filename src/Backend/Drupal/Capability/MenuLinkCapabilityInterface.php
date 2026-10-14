<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Drupal\Capability;

use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;

/**
 * Capability: read the plugin id a menu link is known by.
 */
interface MenuLinkCapabilityInterface {

  /**
   * Returns the plugin id of a menu link.
   *
   * Another link names it as its parent by this id.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   A 'menu_link_content' stub carrying its id, or one returned from a
   *   previous create.
   *
   * @return string
   *   The plugin id, such as 'menu_link_content:<uuid>'.
   *
   * @throws \RuntimeException
   *   When the stub carries no id, or the id names no menu link.
   */
  public function getMenuLinkPluginId(EntityStubInterface $stub): string;

}
