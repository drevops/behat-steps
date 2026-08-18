<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Drupal;

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Exception\ExpectationException;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Drupal\DrupalExtension\Hook\Attribute\BeforeNodeCreate;
use Drupal\DrupalExtension\Hook\Scope\BeforeNodeCreateScope;
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
 * - Set path aliases and assert the published state of content.
 *
 * Steps that match content by title resolve to the most recently created node
 * when several nodes of the same type share that title.
 *
 * The path alias step requires the core `path` module to be enabled. When the
 * contrib `pathauto` module is enabled, automatic alias generation is switched
 * off for the content so that the provided alias is preserved.
 */
trait ContentTrait {

  use HelperTrait;

  /**
   * Expand fixture file paths for file/image fields on nodes.
   *
   * Rewrites bare fixture filenames (e.g. 'document.pdf') on 'file' and
   * 'image' field types to absolute paths under the Mink 'files_path'.
   * drupal-driver's FileHandler can then read and upload them during node
   * creation.
   *
   * Without this, scenarios with file fields on nodes have to pre-create
   * managed files explicitly via FileTrait.
   *
   * Backed by 'HelperTrait::helperExpandEntityFieldsFixtures()'.
   */
  #[BeforeNodeCreate]
  public function contentBeforeNodeCreate(BeforeNodeCreateScope $scope): void {
    $this->helperExpandEntityFieldsFixtures('node', $scope->getStub());
  }

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

      $storage = \Drupal::entityTypeManager()->getStorage('node');
      $entities = $storage->loadMultiple($nids);
      $storage->delete($entities);
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
   * Change moderation state of a content with the specified title.
   *
   * @code
   * When I change the moderation state of the "article" content with the title "Test article" to the "published" state
   * @endcode
   */
  #[When('I change the moderation state of the :content_type content with the title :title to the :new_state state')]
  public function contentChangeModerationStateWithTitle(string $content_type, string $title, string $new_state): void {
    $node = $this->contentLoadNodeByTitle($content_type, $title);

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
    $node = $this->contentLoadNodeByTitle($content_type, $title);

    $handler = \Drupal::entityTypeManager()->getAccessControlHandler('node');

    // @codeCoverageIgnoreStart
    if (!$handler instanceof NodeAccessControlHandlerInterface) {
      throw new \RuntimeException('The node access control handler does not support acquiring grants.');
    }

    // @codeCoverageIgnoreEnd
    $grants = $handler->acquireGrants($node);
    \Drupal::service('node.grant_storage')->write($node, $grants);
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
   * Set the path alias of a content with the specified title.
   *
   * The alias replaces any existing alias for the content. A missing leading
   * slash is added automatically, so both "about-us" and "/about-us" are
   * accepted.
   *
   * @code
   * When I set the path alias of the "article" content with the title "Test article" to "/my-test-article"
   * @endcode
   */
  #[When('I set the path alias of the :content_type content with the title :title to :alias')]
  public function contentSetPathAliasWithTitle(string $content_type, string $title, string $alias): void {
    $this->contentAssertPathModuleEnabled();

    $alias = trim($alias);

    if ($alias === '') {
      throw new \RuntimeException(sprintf('Path alias for "%s" content with the title "%s" cannot be empty.', $content_type, $title));
    }

    $node = $this->contentLoadNodeByTitle($content_type, $title);

    // The current value carries the 'pid' of the existing alias, so the save
    // updates that alias rather than adding a second one.
    $path_value = (array) ($node->get('path')->getValue()[0] ?? []);
    $path_value['alias'] = '/' . ltrim($alias, '/');

    // 0 is 'PathautoState::SKIP', so pathauto does not regenerate the alias
    // on save. The property exists only on 'PathautoItem', so setting it
    // without the module throws.
    if (\Drupal::moduleHandler()->moduleExists('pathauto')) {
      $path_value['pathauto'] = 0;
    }

    $node->set('path', $path_value);
    $node->save();
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
   * Assert content with specified type and title is published.
   *
   * @code
   * Then "page" content with the title "Test page" should be published
   * @endcode
   */
  #[Then(':content_type content with the title :title should be published')]
  public function contentAssertPublishedWithTitle(string $content_type, string $title): void {
    $node = $this->contentLoadNodeByTitle($content_type, $title);

    if (!$node->isPublished()) {
      throw new ExpectationException(sprintf('"%s" content with the title "%s" should be published, but it is not (nid: %s).', $content_type, $title, $node->id()), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert content with specified type and title is not published.
   *
   * @code
   * Then "page" content with the title "Test page" should not be published
   * @endcode
   */
  #[Then(':content_type content with the title :title should not be published')]
  public function contentAssertNotPublishedWithTitle(string $content_type, string $title): void {
    $node = $this->contentLoadNodeByTitle($content_type, $title);

    if ($node->isPublished()) {
      throw new ExpectationException(sprintf('"%s" content with the title "%s" should not be published, but it is (nid: %s).', $content_type, $title, $node->id()), $this->getSession()->getDriver());
    }
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
    $nid = $this->contentResolveNidByTitle($content_type, $title);
    $path = $this->locatePath('/node/' . $nid . $action_subpath);

    $this->getSession()->visit($path);
  }

  /**
   * Resolve the ID of the node with the specified type and title.
   *
   * When several nodes of the same type share the title, the most recently
   * created one is resolved.
   *
   * @param string $content_type
   *   The content type.
   * @param string $title
   *   The title of the content.
   *
   * @return int
   *   The node ID.
   */
  protected function contentResolveNidByTitle(string $content_type, string $title): int {
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

    return (int) end($nids);
  }

  /**
   * Load the node with the specified type and title.
   *
   * @param string $content_type
   *   The content type.
   * @param string $title
   *   The title of the content.
   *
   * @return \Drupal\node\NodeInterface
   *   The node.
   */
  protected function contentLoadNodeByTitle(string $content_type, string $title): NodeInterface {
    $node = Node::load($this->contentResolveNidByTitle($content_type, $title));

    // @codeCoverageIgnoreStart
    if (!$node instanceof NodeInterface) {
      throw new \RuntimeException(sprintf('Unable to find "%s" content with title "%s".', $content_type, $title));
    }

    // @codeCoverageIgnoreEnd
    return $node;
  }

  /**
   * Throw when the `path` module is not enabled.
   */
  protected function contentAssertPathModuleEnabled(): void {
    // @codeCoverageIgnoreStart
    if (!\Drupal::moduleHandler()->moduleExists('path')) {
      throw new \RuntimeException('The "path" module is not enabled. Enable it to manage content path aliases.');
    }
    // @codeCoverageIgnoreEnd
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
