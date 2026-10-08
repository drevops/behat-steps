<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Gherkin\Node\TableNode;
use Behat\Step\When;
use DrevOps\BehatSteps\Backend\Capability\CoreCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\ModuleCapabilityInterface;
use DrevOps\BehatSteps\Behat\Prerequisite\Prerequisite;
use DrevOps\BehatSteps\Helper\Drupal\QueryTrait;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Database\Database;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Order items in the Drupal Draggable Views.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
 */
trait DraggableviewsTrait {

  use QueryTrait;

  /**
   * Save the order of the Draggable Views items.
   *
   * @code
   * When I save the draggable views items of the view "draggableviews_demo" and the display "page_1" for the "article" content in the following order:
   *   | First Article  |
   *   | Second Article |
   *   | Third Article  |
   * @endcode
   */
  #[When('I save the draggable views items of the view :view_id and the display :view_display_id for the :content_type content in the following order:')]
  public function draggableviewsSaveBundleOrder(string $view_id, string $view_display_id, string $content_type, TableNode $order_table): void {
    $this->backendFor(CoreCapabilityInterface::class);

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
   * @return \Drupal\node\NodeInterface|null
   *   The found node or NULL.
   */
  public function draggableviewsFindNode(string $type, array $conditions): ?NodeInterface {
    $nid = $this->queryFindNewestEntityId('node', $conditions, $type);

    return $nid === NULL ? NULL : Node::load($nid);
  }

  /**
   * Get nodes of a type by title, keyed like the titles.
   *
   * @param string $type
   *   The node type.
   * @param array<int, string> $titles
   *   The node titles.
   *
   * @return array<int, \Drupal\node\NodeInterface>
   *   The nodes, keyed like the titles.
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
   * @param array<int, \Drupal\node\NodeInterface> $nodes
   *   The nodes, keyed by weight.
   */
  public function draggableviewsSetOrder(string $view_id, string $view_display_id, array $nodes): void {
    $database = Database::getConnection();

    foreach ($nodes as $weight => $node) {
      $entity_id = $node->id();

      // The delete and insert mirror draggableviews_views_submit().
      $database->delete('draggableviews_structure')
        ->condition('view_name', $view_id)
        ->condition('view_display', $view_display_id)
        ->condition('entity_id', $entity_id)
        ->execute();

      $record = [
        'view_name' => $view_id,
        'view_display' => $view_display_id,
        'args' => '[]',
        'entity_id' => $entity_id,
        'weight' => $weight,
      ];

      $database->insert('draggableviews_structure')->fields($record)->execute();
    }

    // Invalidate the entity list cache so other views also reflect the
    // change.
    $list_cache_tags = \Drupal::entityTypeManager()->getDefinition('node')->getListCacheTags();
    Cache::invalidateTags($list_cache_tags);
  }

  /**
   * Declares the prerequisites this trait asserts.
   *
   * @return array<int, \DrevOps\BehatSteps\Behat\Prerequisite\Prerequisite>
   *   The prerequisites this trait declares.
   */
  protected function draggableviewsPrerequisites(): array {
    return [
      Prerequisite::capability(CoreCapabilityInterface::class),
      Prerequisite::check(static fn(ModuleCapabilityInterface $backend): bool => $backend->moduleIsEnabled('draggableviews'), 'the "draggableviews" module from the "drupal/draggableviews" package is enabled'),
    ];
  }

}
