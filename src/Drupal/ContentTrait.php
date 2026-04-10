<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Drupal;

use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Exception\ExpectationException;
use DrevOps\BehatSteps\HelperTrait;
use Drupal\node\Entity\Node;
use Drupal\node\NodeAccessControlHandlerInterface;
use Drupal\node\NodeInterface;
use Drupal\workflows\Entity\Workflow;

/**
 * Manage Drupal content with workflow and moderation support.
 *
 * - Create, find, and manipulate nodes with structured field data.
 * - Navigate to node pages by title and manage editorial workflows.
 * - Support content moderation transitions and scheduled publishing.
 */
trait ContentTrait {

  use HelperTrait;

  /**
   * Delete content type.
   *
   * @code
   * Given the content type "article" does not exist
   * @endcode
   */
  #[Given('the content type :content_type does not exist')]
  public function contentRemoveContentType(string $content_type): void {
    $content_type_entity = \Drupal::entityTypeManager()->getStorage('node_type')->load($content_type);

    if ($content_type_entity) {
      $content_type_entity->delete();
    }
  }

  /**
   * Remove content defined by provided properties.
   *
   * @code
   * Given the following "article" content does not exist:
   *   | title                |
   *   | Test article         |
   *   | Another test article |
   * @endcode
   */
  #[Given('the following :content_type content does not exist:')]
  public function contentDelete(string $content_type, TableNode $table): void {
    foreach ($table->getHash() as $node_hash) {
      $nids = $this->contentLoadMultiple($content_type, $node_hash);

      $controller = \Drupal::entityTypeManager()->getStorage('node');
      $entities = $controller->loadMultiple($nids);
      $controller->delete($entities);
    }
  }

  /**
   * Create content with vertical field format.
   *
   * Supports both single and multiple entity creation using vertical table
   * format where fields are listed in rows instead of columns.
   *
   * @param string $type
   *   The content type machine name.
   * @param \Behat\Gherkin\Node\TableNode $table
   *   Vertical format table with field names in first column.
   *
   * @code
   *   Given the following page content with fields:
   *     | title  | [TEST] Page 1        | [TEST] Page 2        |
   *     | body   | First page content   | Second page content  |
   *     | status | 1                    | 1                    |
   * @endcode
   */
  #[Given('the following :type content with fields:')]
  public function contentCreateWithFields(string $type, TableNode $table): void {
    $entities = $this->helperTransposeVerticalTable($table);
    $horizontal_table = $this->helperBuildHorizontalTable($entities);
    $this->createNodes($type, $horizontal_table);
  }

  /**
   * Visit a page of a type with a specified title.
   *
   * @code
   * When I visit the "article" content page with the title "Test article"
   * @endcode
   */
  #[When('I visit the :content_type content page with the title :title')]
  public function contentVisitViewWithTitle(string $content_type, string $title): void {
    $this->contentVisitActionPageWithTitle($content_type, $title);
  }

  /**
   * Visit an edit page of a type with a specified title.
   *
   * @code
   * When I visit the "article" content edit page with the title "Test article"
   * @endcode
   */
  #[When('I visit the :content_type content edit page with the title :title')]
  public function contentVisitEditPageWithTitle(string $content_type, string $title): void {
    $this->contentVisitActionPageWithTitle($content_type, $title, '/edit');
  }

  /**
   * Visit a delete page of a type with a specified title.
   *
   * @code
   * When I visit the "article" content delete page with the title "Test article"
   * @endcode
   */
  #[When('I visit the :content_type content delete page with the title :title')]
  public function contentVisitDeletePageWithTitle(string $content_type, string $title): void {
    $this->contentVisitActionPageWithTitle($content_type, $title, '/delete');
  }

  /**
   * Visit a scheduled transitions page of a type with a specified title.
   *
   * @code
   * When I visit the "article" content scheduled transitions page with the title "Test article"
   * @endcode
   */
  #[When('I visit the :content_type content scheduled transitions page with the title :title')]
  public function contentVisitScheduledTransitionsPageWithTitle(string $content_type, string $title): void {
    $this->contentVisitActionPageWithTitle($content_type, $title, '/scheduled-transitions');
  }

  /**
   * Visit a revisions page of a type with a specified title.
   *
   * @code
   * When I visit the "article" content revisions page with the title "Test article"
   * @endcode
   */
  #[When('I visit the :content_type content revisions page with the title :title')]
  public function contentVisitRevisionsPageWithTitle(string $content_type, string $title): void {
    $this->contentVisitActionPageWithTitle($content_type, $title, '/revisions');
  }

  /**
   * Visit the action page of the content with a specified title.
   *
   * @param string $content_type
   *   The content type.
   * @param string $title
   *   The title of the content.
   * @param string $action_subpath
   *   The operation to perform.
   */
  protected function contentVisitActionPageWithTitle(string $content_type, string $title, string $action_subpath = ''): void {
    $content_type_entity = \Drupal::entityTypeManager()->getStorage('node_type')->load($content_type);

    if (!$content_type_entity) {
      throw new \RuntimeException(sprintf('Content type "%s" does not exist.', $content_type));
    }

    $nids = $this->contentLoadMultiple($content_type, [
      'title' => $title,
    ]);

    if (empty($nids)) {
      throw new \RuntimeException(sprintf('Unable to find "%s" content with title "%s".', $content_type, $title));
    }

    ksort($nids);

    $nid = end($nids);
    $path = $this->locatePath('/node/' . $nid . $action_subpath);

    $this->getSession()->visit($path);
  }

  /**
   * Change moderation state of a content with the specified title.
   *
   * @code
   * When I change the moderation state of the "article" content with the title "Test article" to the "published" state
   * @endcode
   */
  #[When('I change the moderation state of the :content_type content with the title :title to the :new_state state')]
  public function contentChangeModerationStateWithTitle(string $content_type, string $title, string $new_state): void {
    $content_type_entity = \Drupal::entityTypeManager()->getStorage('node_type')->load($content_type);

    if (!$content_type_entity) {
      throw new \RuntimeException(sprintf('Content type "%s" does not exist.', $content_type));
    }

    $nids = $this->contentLoadMultiple($content_type, [
      'title' => $title,
    ]);

    if (empty($nids)) {
      throw new \RuntimeException(sprintf('Unable to find "%s" content with title "%s".', $content_type, $title));
    }

    ksort($nids);

    $nid = end($nids);
    $node = Node::load($nid);

    // @codeCoverageIgnoreStart
    if (!$node instanceof NodeInterface) {
      throw new \RuntimeException(sprintf('Unable to find "%s" content with title "%s".', $content_type, $title));
    }
    // @codeCoverageIgnoreEnd
    $state_is_valid = FALSE;
    $workflows = Workflow::loadMultiple();
    foreach ($workflows as $workflow) {
      $workflow_type_settings = $workflow->get('type_settings');
      if (in_array($content_type, $workflow_type_settings['entity_types']['node']) && isset($workflow_type_settings['states'][$new_state])) {
        $state_is_valid = TRUE;
        break;
      }
    }

    if (!$state_is_valid) {
      throw new \RuntimeException(sprintf('State "%s" is not defined in the workflow for "%s" content type.', $new_state, $content_type));
    }

    $node->set('moderation_state', $new_state);
    $node->save();
  }

  /**
   * Rebuild node access grants for a content with the specified title.
   *
   * Loads the node by content type and exact title match, then acquires
   * grants for it via the node access control handler. Useful after test
   * fixtures are created to ensure access grants are populated for
   * modules relying on the node access system.
   *
   * @code
   * When I rebuild the access grants for the "article" content with the title "My article"
   * @endcode
   */
  #[When('I rebuild the access grants for the :content_type content with the title :title')]
  public function contentRebuildAccessGrantsByTitle(string $content_type, string $title): void {
    $nids = $this->contentLoadMultiple($content_type, [
      'title' => $title,
    ]);

    if (empty($nids)) {
      throw new \RuntimeException(sprintf('Unable to find "%s" content with title "%s".', $content_type, $title));
    }

    ksort($nids);

    $nid = end($nids);
    $node = Node::load($nid);

    // @codeCoverageIgnoreStart
    if (!$node instanceof NodeInterface) {
      throw new \RuntimeException(sprintf('Unable to find "%s" content with title "%s".', $content_type, $title));
    }
    // @codeCoverageIgnoreEnd
    $handler = \Drupal::entityTypeManager()->getAccessControlHandler('node');
    assert($handler instanceof NodeAccessControlHandlerInterface);
    $handler->acquireGrants($node);
  }

  /**
   * Rebuild node access grants for all content.
   *
   * Triggers a non-batched rebuild of node access grants for every node
   * in the system. Useful after enabling or reconfiguring a node access
   * module during a scenario.
   *
   * @code
   * When I rebuild the access grants for all content
   * @endcode
   */
  #[When('I rebuild the access grants for all content')]
  public function contentRebuildAccessGrantsAll(): void {
    node_access_rebuild(FALSE);
  }

  /**
   * Assert content with specified type and title does not exist.
   *
   * @code
   * Then "page" content with the title "Test page" should not exist
   * Then "article" content with the title "Test article" should not exist
   * @endcode
   */
  #[Then(':content_type content with the title :title should not exist')]
  public function contentAssertNotExistsWithTitle(string $content_type, string $title): void {
    $nids = $this->contentLoadMultiple($content_type, ['title' => $title]);

    if (!empty($nids)) {
      throw new ExpectationException(sprintf('"%s" content with the title "%s" should not exist, but it does (nid: %s).', $content_type, $title, implode(', ', $nids)), $this->getSession()->getDriver());
    }
  }

  /**
   * Load multiple nodes with specified type and conditions.
   *
   * @param string $type
   *   The node type.
   * @param array<string, mixed> $conditions
   *   Conditions keyed by field names.
   *
   * @return array<int, string>
   *   Array of node ids.
   */
  protected function contentLoadMultiple(string $type, array $conditions = []): array {
    $query = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('type', $type);

    foreach ($conditions as $k => $v) {
      $and = $query->andConditionGroup();
      $and->condition($k, $v);
      $query->condition($and);
    }

    return $query->execute();
  }

}
