<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Exception\ExpectationException;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use DrevOps\BehatSteps\Backend\Capability\CoreCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\ModuleCapabilityInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;
use DrevOps\BehatSteps\Behat\Hook\Attribute\BeforeNodeCreate;
use DrevOps\BehatSteps\Behat\Hook\Scope\BeforeNodeCreateScope;
use DrevOps\BehatSteps\Behat\Prerequisite\Prerequisite;
use DrevOps\BehatSteps\Helper\Drupal\EntityLifecycleTrait;
use DrevOps\BehatSteps\Helper\Drupal\FixtureFileTrait;
use DrevOps\BehatSteps\Helper\Drupal\QueryTrait;
use DrevOps\BehatSteps\Helper\Web\TableTransposeTrait;
use Drupal\node\Entity\Node;
use Drupal\node\NodeAccessControlHandlerInterface;
use Drupal\node\NodeAccessRebuild;
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
 * Steps that visit, modify or assert the published state of content by title
 * resolve to the most recently created node when several nodes of the same
 * type share that title. Steps that delete content or assert it does not
 * exist match every such node.
 *
 * When the contrib `pathauto` module is enabled, the path alias step switches
 * automatic alias generation off for the content, so that the provided alias
 * is preserved.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
 */
trait ContentTrait {

  use EntityLifecycleTrait;
  use FixtureFileTrait;
  use QueryTrait;
  use TableTransposeTrait;

  /**
   * Expand fixture file paths for file/image fields on nodes.
   */
  #[BeforeNodeCreate]
  public function contentBeforeNodeCreate(BeforeNodeCreateScope $scope): void {
    $this->fixtureFileExpandEntityFields('node', $scope->getStub());
  }

  /**
   * Delete content type.
   *
   * @code
   * Given the content type "article" does not exist
   * @endcode
   */
  #[Given('the content type :content_type does not exist')]
  public function contentDeleteType(string $content_type): void {
    $this->backendFor(CoreCapabilityInterface::class);

    $content_type_entity = \Drupal::entityTypeManager()->getStorage('node_type')->load($content_type);

    if ($content_type_entity) {
      $content_type_entity->delete();
    }
  }

  /**
   * Remove content defined by provided properties.
   *
   * @code
   * Given the following "article" content do not exist:
   *   | title                |
   *   | Test article         |
   *   | Another test article |
   * @endcode
   */
  #[Given('the following :content_type content do not exist:')]
  public function contentDeleteMultiple(string $content_type, TableNode $table): void {
    $this->backendFor(CoreCapabilityInterface::class);

    foreach ($table->getHash() as $node_hash) {
      $this->contentDelete($content_type, $node_hash);
    }
  }

  /**
   * Create content with vertical field format.
   *
   * Supports both single and multiple entity creation using vertical table
   * format where fields are listed in rows instead of columns.
   *
   * @param string $content_type
   *   The content type machine name.
   * @param \Behat\Gherkin\Node\TableNode $table
   *   Vertical format table with field names in first column.
   *
   * @code
   *   Given the following page content with fields exist:
   *     | title  | [TEST] Page 1        | [TEST] Page 2        |
   *     | body   | First page content   | Second page content  |
   *     | status | 1                    | 1                    |
   * @endcode
   */
  #[Given('the following :content_type content with fields exist:')]
  public function contentCreateMultipleWithFields(string $content_type, TableNode $table): void {
    foreach ($this->tableTransposeVertical($table) as $values) {
      $this->contentCreate($content_type, $values);
    }
  }

  /**
   * Create content of a type from a table of field values.
   *
   * Each row becomes 1 node; each column is a base property or a field.
   *
   * @code
   *   Given the following page content exist:
   *     | title         | status |
   *     | [TEST] Page 1 | 1      |
   *     | [TEST] Page 2 | 0      |
   * @endcode
   */
  #[Given('the following :content_type content exist:')]
  public function contentCreateMultiple(string $content_type, TableNode $table): void {
    foreach ($table->getHash() as $values) {
      $this->contentCreate($content_type, $values);
    }
  }

  /**
   * Visit a page of a type with a specified title.
   *
   * @code
   * When I visit the "article" content page with the title "Test article"
   * @endcode
   */
  #[When('I visit the :content_type content page with the title :title')]
  public function contentVisitPageWithTitle(string $content_type, string $title): void {
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
   * When I change the moderation state of the "article" content with the title "Test article" to the state "published"
   * @endcode
   */
  #[When('I change the moderation state of the :content_type content with the title :title to the state :new_state')]
  public function contentChangeModerationStateWithTitle(string $content_type, string $title, string $new_state): void {
    $this->backendFor(CoreCapabilityInterface::class);

    $this->contentSetModerationState($this->contentGetNodeByTitle($content_type, $title), $new_state);
  }

  /**
   * Rebuild node access grants for a content with the specified title.
   *
   * @code
   * When I rebuild the access grants for the "article" content with the title "My article"
   * @endcode
   */
  #[When('I rebuild the access grants for the :content_type content with the title :title')]
  public function contentRebuildAccessGrantsWithTitle(string $content_type, string $title): void {
    $this->backendFor(CoreCapabilityInterface::class);

    $this->contentRebuildAccessGrants($this->contentGetNodeByTitle($content_type, $title));
  }

  /**
   * Rebuild node access grants for all content.
   *
   * @code
   * When I rebuild the access grants for all content
   * @endcode
   */
  #[When('I rebuild the access grants for all content')]
  public function contentRebuildAccessGrantsAll(): void {
    $this->backendFor(CoreCapabilityInterface::class);

    \Drupal::service(NodeAccessRebuild::class)->rebuild(FALSE);
  }

  /**
   * Set the path alias of a content with the specified title.
   *
   * The alias replaces any existing alias for the content. A missing leading
   * slash is added automatically, so both "about-us" and "/about-us" are
   * accepted.
   *
   * @code
   * When I set the path alias of the "article" content with the title "Test article" to the alias "/my-test-article"
   * @endcode
   */
  #[When('I set the path alias of the :content_type content with the title :title to the alias :alias')]
  public function contentSetPathAliasWithTitle(string $content_type, string $title, string $alias): void {
    $this->backendFor(CoreCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    if (trim($alias) === '') {
      throw new \RuntimeException(sprintf('Path alias for "%s" content with the title "%s" cannot be empty.', $content_type, $title));
    }

    $this->contentSetPathAlias($this->contentGetNodeByTitle($content_type, $title), $alias);
  }

  /**
   * Assert content with specified type and title does not exist.
   *
   * @code
   * Then the "page" content with the title "Test page" should not exist
   * Then the "article" content with the title "Test article" should not exist
   * @endcode
   */
  #[Then('the :content_type content with the title :title should not exist')]
  public function contentAssertNotExistsWithTitle(string $content_type, string $title): void {
    $nids = $this->queryNodeIds($content_type, ['title' => $title]);

    if (!empty($nids)) {
      throw new ExpectationException(sprintf('The "%s" content with the title "%s" should not exist, but it does (nid: %s).', $content_type, $title, implode(', ', $nids)), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert content with specified type and title is published.
   *
   * @code
   * Then the "page" content with the title "Test page" should be published
   * @endcode
   */
  #[Then('the :content_type content with the title :title should be published')]
  public function contentAssertPublishedWithTitle(string $content_type, string $title): void {
    $node = $this->contentGetNodeByTitle($content_type, $title);

    if (!$node->isPublished()) {
      throw new ExpectationException(sprintf('The "%s" content with the title "%s" should be published, but it is not (nid: %s).', $content_type, $title, $node->id()), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert content with specified type and title is not published.
   *
   * @code
   * Then the "page" content with the title "Test page" should not be published
   * @endcode
   */
  #[Then('the :content_type content with the title :title should not be published')]
  public function contentAssertNotPublishedWithTitle(string $content_type, string $title): void {
    $node = $this->contentGetNodeByTitle($content_type, $title);

    if ($node->isPublished()) {
      throw new ExpectationException(sprintf('The "%s" content with the title "%s" should not be published, but it is (nid: %s).', $content_type, $title, $node->id()), $this->getSession()->getDriver());
    }
  }

  /**
   * Visit the action page of the content with a specified title.
   *
   * @param string $content_type
   *   The content type.
   * @param string $title
   *   The title of the content.
   * @param string|null $action_subpath
   *   The operation to perform, such as '/edit', or NULL for the content page.
   */
  public function contentVisitActionPageWithTitle(string $content_type, string $title, ?string $action_subpath = NULL): void {
    $nid = $this->contentGetNidByTitle($content_type, $title);
    $path = $this->locatePath('/node/' . $nid . ($action_subpath ?? ''));

    $this->getSession()->visit($path);
  }

  /**
   * Return the ID of the node with the specified type and title.
   *
   * When several nodes of the same type share the title, the most recently
   * created one is returned.
   *
   * @param string $content_type
   *   The content type.
   * @param string $title
   *   The title of the content.
   *
   * @return int
   *   The node ID.
   *
   * @throws \RuntimeException
   *   When the content type does not exist or no node of it has the title.
   */
  public function contentGetNidByTitle(string $content_type, string $title): int {
    $this->backendFor(CoreCapabilityInterface::class);

    $content_type_entity = \Drupal::entityTypeManager()->getStorage('node_type')->load($content_type);

    if (!$content_type_entity) {
      throw new \RuntimeException(sprintf('The content type "%s" does not exist.', $content_type));
    }

    $nid = $this->queryFindNewestEntityId('node', ['title' => $title], $content_type);

    if ($nid === NULL) {
      throw new \RuntimeException(sprintf('Unable to find "%s" content with title "%s".', $content_type, $title));
    }

    return (int) $nid;
  }

  /**
   * Return the node with the specified type and title.
   *
   * @param string $content_type
   *   The content type.
   * @param string $title
   *   The title of the content.
   *
   * @return \Drupal\node\NodeInterface
   *   The node.
   *
   * @throws \RuntimeException
   *   When the content type does not exist or no node of it has the title.
   */
  public function contentGetNodeByTitle(string $content_type, string $title): NodeInterface {
    $this->backendFor(CoreCapabilityInterface::class);

    $node = Node::load($this->contentGetNidByTitle($content_type, $title));

    // @codeCoverageIgnoreStart
    if (!$node instanceof NodeInterface) {
      throw new \RuntimeException(sprintf('Unable to find "%s" content with title "%s".', $content_type, $title));
    }

    // @codeCoverageIgnoreEnd
    return $node;
  }

  /**
   * Create a node of a content type.
   *
   * The node is removed after the scenario.
   *
   * @param string $content_type
   *   The content type.
   * @param array<string, mixed> $values
   *   The base properties and field values, keyed by name.
   *
   * @return \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface
   *   The stub of the created node.
   */
  public function contentCreate(string $content_type, array $values): EntityStubInterface {
    return $this->entityLifecycleCreateNode(new EntityStub('node', $content_type, $values));
  }

  /**
   * Delete the nodes of a content type that match conditions.
   *
   * @param string $content_type
   *   The content type.
   * @param array<string, mixed> $conditions
   *   Conditions keyed by field names.
   */
  public function contentDelete(string $content_type, array $conditions): void {
    $nids = $this->queryNodeIds($content_type, $conditions);

    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $storage->delete($storage->loadMultiple($nids));
  }

  /**
   * Set the moderation state of a node and save it.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   * @param string $state
   *   The moderation state.
   *
   * @throws \RuntimeException
   *   When no workflow for the content type of the node defines the state.
   */
  public function contentSetModerationState(NodeInterface $node, string $state): void {
    $content_type = $node->bundle();

    $state_is_valid = FALSE;
    $workflows = Workflow::loadMultiple();

    foreach ($workflows as $workflow) {
      $workflow_type_settings = $workflow->get('type_settings');

      if (in_array($content_type, $workflow_type_settings['entity_types']['node'], TRUE) && isset($workflow_type_settings['states'][$state])) {
        $state_is_valid = TRUE;
        break;
      }
    }

    if (!$state_is_valid) {
      throw new \RuntimeException(sprintf('State "%s" is not defined in the workflow for "%s" content type.', $state, $content_type));
    }

    $node->set('moderation_state', $state);
    $node->save();
  }

  /**
   * Rebuild the access grants of a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   */
  public function contentRebuildAccessGrants(NodeInterface $node): void {
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
   * Set the path alias of a node, replacing any existing alias.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   * @param string $alias
   *   The alias, with or without its leading slash.
   */
  public function contentSetPathAlias(NodeInterface $node, string $alias): void {
    // The current value carries the 'pid' of the existing alias, so the save
    // updates that alias rather than adding a second one.
    $path_value = (array) ($node->get('path')->getValue()[0] ?? []);
    $path_value['alias'] = '/' . ltrim(trim($alias), '/');

    // 0 is 'PathautoState::SKIP', so pathauto does not regenerate the alias
    // on save.
    if ($this->anyBackendFor(ModuleCapabilityInterface::class)->moduleIsEnabled('pathauto')) {
      $path_value['pathauto'] = 0;
    }

    $node->set('path', $path_value);
    $node->save();
  }

  /**
   * Declares the prerequisites this trait asserts.
   *
   * @return array<int, \DrevOps\BehatSteps\Behat\Prerequisite\Prerequisite>
   *   The prerequisites this trait declares.
   */
  protected function contentPrerequisites(): array {
    return [
      Prerequisite::capability(CoreCapabilityInterface::class),
      Prerequisite::check(static fn(ModuleCapabilityInterface $backend): bool => $backend->moduleIsEnabled('path'), 'the core "path" module is enabled, for the path alias step'),
    ];
  }

}
