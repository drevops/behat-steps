<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Gherkin\Node\TableNode;
use Behat\Step\Given;
use DrevOps\BehatSteps\Backend\Capability\ContentCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\ConfigEntityCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\MenuLinkCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\ModuleCapabilityInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;
use DrevOps\BehatSteps\Extension\Prerequisite\Prerequisite;
use DrevOps\BehatSteps\Steps\Helper\Drupal\EntityLifecycleTrait;
use DrevOps\BehatSteps\Steps\Helper\Drupal\QueryTrait;

/**
 * Manage Drupal menus and menu links.
 *
 * - Create and remove menus by label.
 * - Create and remove menu links, including parent-child hierarchies.
 * - Created menus and menu links are automatically removed at the end of the scenario.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\WebRawContext
 */
trait MenuTrait {

  use EntityLifecycleTrait;
  use QueryTrait;

  /**
   * Remove every menu with a label.
   *
   * @param string $menu_name
   *   The label of the menus to remove.
   *
   * @code
   *   Given the menu "Test Menu" does not exist
   * @endcode
   *
   * @actor:system
   */
  #[Given('the menu :menu_name does not exist')]
  public function menuDelete(string $menu_name): void {
    $backend = $this->backendFor(ContentCapabilityInterface::class);

    foreach ($this->queryEntityIds('menu', ['label' => $menu_name]) as $id) {
      $backend->deleteEntity(new EntityStub('menu', NULL, ['id' => $id]));
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
   *
   * @actor:system
   */
  #[Given('the following menus exist:')]
  public function menuCreateMultiple(TableNode $table): void {
    $this->backendFor(ConfigEntityCapabilityInterface::class);

    foreach ($table->getHash() as $menu_hash) {
      $this->menuCreate($menu_hash);
    }
  }

  /**
   * Remove every menu link with each of the titles.
   *
   * @code
   * Given the following menu links do not exist in the menu "Main navigation":
   *   | About Us     |
   *   | Contact      |
   * @endcode
   *
   * @actor:system
   */
  #[Given('the following menu links do not exist in the menu :menu_name:')]
  public function menuDeleteLinkMultiple(string $menu_name, TableNode $table): void {
    $this->backendFor(ContentCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    foreach ($table->getColumn(0) as $title) {
      $this->menuDeleteLink($menu_name, ['title' => $title]);
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
   *
   * @actor:system
   */
  #[Given('the following menu links exist in the menu :menu_name:')]
  public function menuCreateLinkMultiple(string $menu_name, TableNode $table): void {
    $this->backendFor(ContentCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    $menu = $this->menuFindByLabel($menu_name);

    if (!$menu instanceof EntityStubInterface) {
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
   *   The menu values. A menu without an "id" takes an ID derived from its
   *   "label".
   *
   * @return \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface
   *   The stub of the menu, carrying its 'id'.
   */
  public function menuCreate(array $values): EntityStubInterface {
    if (empty($values['id'])) {
      $menu_id = strtolower((string) $values['label']);
      $menu_id = preg_replace('/[^a-z0-9_]+/', '_', $menu_id);
      $menu_id = preg_replace('/_+/', '_', (string) $menu_id);
      $values['id'] = $menu_id;
    }

    $created = $this->backendFor(ConfigEntityCapabilityInterface::class)->createConfigEntity(new EntityStub('menu', NULL, $values));

    $this->entityLifecycleRegister($created, 'id');

    return $created;
  }

  /**
   * Create a link in a menu.
   *
   * The link is removed after the scenario.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $menu
   *   The menu stub, carrying its 'id'.
   * @param array<string, string> $values
   *   The link values. A "uri" value becomes the link URI, and a "parent"
   *   value is the title of a link in the same menu.
   *
   * @return \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface
   *   The stub of the menu link, carrying its 'id'.
   */
  public function menuCreateLink(EntityStubInterface $menu, array $values): EntityStubInterface {
    $menu_id = $this->queryGetEntityId($menu, 'id');
    $values['menu_name'] = $menu_id;

    if (isset($values['uri'])) {
      $values['link'] = $values['uri'];
      unset($values['uri']);
    }

    $parent = $values['parent'] ?? NULL;
    unset($values['parent']);

    $parent_link = !empty($parent) && is_string($parent) ? $this->menuFindLinkInMenu($parent, $menu_id) : NULL;

    if ($parent_link instanceof EntityStubInterface) {
      $values['parent'] = $this->backendFor(MenuLinkCapabilityInterface::class)->getMenuLinkPluginId($parent_link);
    }

    $created = $this->backendFor(ContentCapabilityInterface::class)->createEntity(new EntityStub('menu_link_content', NULL, $values));

    $this->entityLifecycleRegister($created, 'id');

    return $created;
  }

  /**
   * Delete the links of a menu that match conditions.
   *
   * @param string $menu_name
   *   The label of the menu.
   * @param array<string, mixed> $conditions
   *   Conditions keyed by field names.
   */
  public function menuDeleteLink(string $menu_name, array $conditions): void {
    $menu = $this->menuFindByLabel($menu_name);

    if (!$menu instanceof EntityStubInterface) {
      return;
    }

    $backend = $this->backendFor(ContentCapabilityInterface::class);

    foreach ($this->queryEntityIds('menu_link_content', ['menu_name' => $this->queryGetEntityId($menu, 'id')] + $conditions) as $id) {
      $backend->deleteEntity(new EntityStub('menu_link_content', NULL, ['id' => $id]));
    }
  }

  /**
   * Load multiple menus with specified conditions.
   *
   * @param array<string, mixed> $conditions
   *   Conditions keyed by property names.
   *
   * @return array<int|string, \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface>
   *   A stub per matching menu carrying its 'id', keyed by ID, or an empty
   *   array when none match.
   */
  public function menuLoadMultiple(array $conditions = []): array {
    return $this->queryEntityStubs('menu', 'id', $conditions);
  }

  /**
   * Find a menu by its label.
   *
   * When several menus carry the label, the menu the scenario created last is
   * returned. Otherwise, the one with the last machine name in natural order
   * is returned.
   *
   * @param string $label
   *   The label of the menu.
   *
   * @return \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface|null
   *   The stub of the menu, carrying its 'id', or NULL if not found.
   */
  public function menuFindByLabel(string $label): ?EntityStubInterface {
    return $this->entityLifecycleFindNewest($this->menuLoadMultiple(['label' => $label]), 'id');
  }

  /**
   * Find a menu link by title and menu label.
   *
   * When several links in the menu carry the title, the newest is returned.
   *
   * @param string $title
   *   The title of the menu link.
   * @param string $menu_name
   *   The label of the menu.
   *
   * @return \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface|null
   *   The stub of the menu link, carrying its 'id', or NULL if not found.
   */
  public function menuFindLinkByTitle(string $title, string $menu_name): ?EntityStubInterface {
    $menu = $this->menuFindByLabel($menu_name);

    return $menu instanceof EntityStubInterface ? $this->menuFindLinkInMenu($title, $this->queryGetEntityId($menu, 'id')) : NULL;
  }

  /**
   * Find the newest link with a title in a menu.
   *
   * @param string $title
   *   The title of the menu link.
   * @param string $menu_id
   *   The machine name of the menu.
   *
   * @return \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface|null
   *   The stub of the menu link, carrying its 'id', or NULL if not found.
   */
  protected function menuFindLinkInMenu(string $title, string $menu_id): ?EntityStubInterface {
    $id = $this->queryFindNewestEntityId('menu_link_content', ['menu_name' => $menu_id, 'title' => $title]);

    return $id === NULL ? NULL : new EntityStub('menu_link_content', NULL, ['id' => $id]);
  }

  /**
   * Declares the prerequisites this trait asserts.
   *
   * @return array<int, \DrevOps\BehatSteps\Extension\Prerequisite\Prerequisite>
   *   The prerequisites this trait declares.
   */
  protected function menuPrerequisites(): array {
    return [
      Prerequisite::capability(ContentCapabilityInterface::class),
      Prerequisite::check(static fn(ModuleCapabilityInterface $backend): bool => $backend->moduleIsEnabled('menu_link_content'), 'the core "menu_link_content" module is enabled, for the menu link steps'),
    ];
  }

}
