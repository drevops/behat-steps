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

    $database = Database::getConnection();

    foreach ($order_table->getColumn(0) as $weight => $title) {
      $node = $this->draggableviewsFindNode($content_type, ['title' => $title]);

      if (empty($node)) {
        throw new \RuntimeException(sprintf('Unable to find the node "%s".', $title));
      }

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
   * Find a node using provided conditions.
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
    $nids = $this->queryNodeIds($type, $conditions);

    if (empty($nids)) {
      return NULL;
    }

    $nid = current($nids);

    return Node::load($nid);
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
