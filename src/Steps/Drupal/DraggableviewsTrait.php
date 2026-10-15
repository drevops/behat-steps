<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Gherkin\Node\TableNode;
use Behat\Step\When;
use DrevOps\BehatSteps\Backend\Drupal\Capability\DraggableviewsCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\ModuleCapabilityInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;
use DrevOps\BehatSteps\Extension\Prerequisite\Prerequisite;
use DrevOps\BehatSteps\Steps\Helper\Drupal\QueryTrait;

/**
 * Order items in the Drupal Draggable Views.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\WebRawContext
 */
trait DraggableviewsTrait {

  use QueryTrait;

  /**
   * Save the order of the Draggable Views items.
   *
   * @code
   * When the draggable views items of the view "draggableviews_demo" and the display "page_1" for the "article" content are saved in the following order:
   *   | First Article  |
   *   | Second Article |
   *   | Third Article  |
   * @endcode
   *
   * @actor:system
   */
  #[When('the draggable views items of the view :view_id and the display :view_display_id for the :content_type content are saved in the following order:')]
  public function draggableviewsSaveBundleOrder(string $view_id, string $view_display_id, string $content_type, TableNode $order_table): void {
    $this->backendFor(DraggableviewsCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    $this->draggableviewsSetOrder($view_id, $view_display_id, $this->draggableviewsGetNodesByTitle($content_type, $order_table->getColumn(0)));
  }

  /**
   * Find a node using provided conditions.
   *
   * When several nodes match, the newest is returned.
   *
   * @param string $type
   *   The node type.
   * @param array<string, string> $conditions
   *   The conditions to search for.
   *
   * @return \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface|null
   *   The stub of the node, carrying its 'nid', or NULL when none matches.
   */
  public function draggableviewsFindNode(string $type, array $conditions): ?EntityStubInterface {
    $nid = $this->queryFindNewestEntityId('node', $conditions, $type);

    return $nid === NULL ? NULL : new EntityStub('node', $type, ['nid' => $nid]);
  }

  /**
   * Get nodes of a type by title, keyed like the titles.
   *
   * @param string $type
   *   The node type.
   * @param array<int, string> $titles
   *   The node titles.
   *
   * @return array<int, \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface>
   *   The stubs of the nodes, each carrying its 'nid', keyed like the titles.
   *
   * @throws \RuntimeException
   *   When no node of the type has 1 of the titles.
   */
  public function draggableviewsGetNodesByTitle(string $type, array $titles): array {
    $nodes = [];

    foreach ($titles as $key => $title) {
      $node = $this->draggableviewsFindNode($type, ['title' => $title]);

      if ($node === NULL) {
        throw new \RuntimeException(sprintf('Unable to find the node "%s".', $title));
      }

      $nodes[$key] = $node;
    }

    return $nodes;
  }

  /**
   * Set the order of nodes in a Draggable Views display.
   *
   * @param string $view_id
   *   The view ID.
   * @param string $view_display_id
   *   The view display ID.
   * @param array<int, \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface> $nodes
   *   The stubs of the nodes, each carrying its 'nid', keyed by weight.
   */
  public function draggableviewsSetOrder(string $view_id, string $view_display_id, array $nodes): void {
    $entity_ids = [];

    foreach ($nodes as $weight => $node) {
      $entity_ids[$weight] = $this->queryGetEntityId($node, 'nid');
    }

    $this->backendFor(DraggableviewsCapabilityInterface::class)->draggableviewsSetOrder($view_id, $view_display_id, $entity_ids);
  }

  /**
   * Declares the prerequisites this trait asserts.
   *
   * @return array<int, \DrevOps\BehatSteps\Extension\Prerequisite\Prerequisite>
   *   The prerequisites this trait declares.
   */
  protected function draggableviewsPrerequisites(): array {
    return [
      Prerequisite::capability(DraggableviewsCapabilityInterface::class),
      Prerequisite::check(static fn(ModuleCapabilityInterface $backend): bool => $backend->moduleIsEnabled('draggableviews'), 'the "draggableviews" module from the "drupal/draggableviews" package is enabled'),
    ];
  }

}
