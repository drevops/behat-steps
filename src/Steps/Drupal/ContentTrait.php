<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Exception\ExpectationException;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use DrevOps\BehatSteps\Backend\Capability\ContentCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\ConfigCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\ModuleCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\NodeCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\PathAliasCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\WorkflowCapabilityInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;
use DrevOps\BehatSteps\Extension\Drupal\Hook\Attribute\BeforeNodeCreate;
use DrevOps\BehatSteps\Extension\Drupal\Hook\Scope\BeforeNodeCreateScope;
use DrevOps\BehatSteps\Extension\Prerequisite\Prerequisite;
use DrevOps\BehatSteps\Steps\Helper\Drupal\EntityLifecycleTrait;
use DrevOps\BehatSteps\Steps\Helper\Drupal\FixtureFileTrait;
use DrevOps\BehatSteps\Steps\Helper\Drupal\QueryTrait;
use DrevOps\BehatSteps\Steps\Helper\Web\TableTransposeTrait;

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
 * @phpstan-require-extends \DrevOps\BehatSteps\WebRawContext
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
   *
   * @actor:system
   */
  #[Given('the content type :content_type does not exist')]
  public function contentDeleteType(string $content_type): void {
    $this->backendFor(ContentCapabilityInterface::class)->deleteEntity(new EntityStub('node_type', NULL, ['type' => $content_type]));
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
   *
   * @actor:system
   */
  #[Given('the following :content_type content does not exist:')]
  public function contentDeleteMultiple(string $content_type, TableNode $table): void {
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
   *   Given the following page content with fields exists:
   *     | title  | [TEST] Page 1        | [TEST] Page 2        |
   *     | body   | First page content   | Second page content  |
   *     | status | 1                    | 1                    |
   * @endcode
   *
   * @actor:system
   */
  #[Given('the following :content_type content with fields exists:')]
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
   *   Given the following page content exists:
   *     | title         | status |
   *     | [TEST] Page 1 | 1      |
   *     | [TEST] Page 2 | 0      |
   * @endcode
   *
   * @actor:system
   */
  #[Given('the following :content_type content exists:')]
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
   *
   * @actor:person
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
   *
   * @actor:person
   */
  #[When('I visit the :content_type content edit page with the title :title')]
  public function contentVisitEditPageWithTitle(string $content_type, string $title): void {
    $this->contentVisitActionPageWithTitle($content_type, $title, 'edit-form');
  }

  /**
   * Visit a delete page of a type with a specified title.
   *
   * @code
   * When I visit the "article" content delete page with the title "Test article"
   * @endcode
   *
   * @actor:person
   */
  #[When('I visit the :content_type content delete page with the title :title')]
  public function contentVisitDeletePageWithTitle(string $content_type, string $title): void {
    $this->contentVisitActionPageWithTitle($content_type, $title, 'delete-form');
  }

  /**
   * Visit a scheduled transitions page of a type with a specified title.
   *
   * @code
   * When I visit the "article" content scheduled transitions page with the title "Test article"
   * @endcode
   *
   * @actor:person
   */
  #[When('I visit the :content_type content scheduled transitions page with the title :title')]
  public function contentVisitScheduledTransitionsPageWithTitle(string $content_type, string $title): void {
    $this->contentVisitActionPageWithTitle($content_type, $title, 'scheduled_transitions');
  }

  /**
   * Visit a revisions page of a type with a specified title.
   *
   * @code
   * When I visit the "article" content revisions page with the title "Test article"
   * @endcode
   *
   * @actor:person
   */
  #[When('I visit the :content_type content revisions page with the title :title')]
  public function contentVisitRevisionsPageWithTitle(string $content_type, string $title): void {
    $this->contentVisitActionPageWithTitle($content_type, $title, 'version-history');
  }

  /**
   * Change moderation state of a content with the specified title.
   *
   * @code
   * When the moderation state of the "article" content with the title "Test article" changes to the state "published"
   * @endcode
   *
   * @actor:system
   */
  #[When('the moderation state of the :content_type content with the title :title changes to the state :new_state')]
  public function contentChangeModerationStateWithTitle(string $content_type, string $title, string $new_state): void {
    $this->contentSetModerationState($this->contentGetNodeByTitle($content_type, $title), $new_state);
  }

  /**
   * Rebuild node access grants for a content with the specified title.
   *
   * @code
   * When the access grants for the "article" content with the title "My article" are rebuilt
   * @endcode
   *
   * @actor:system
   */
  #[When('the access grants for the :content_type content with the title :title are rebuilt')]
  public function contentRebuildAccessGrantsWithTitle(string $content_type, string $title): void {
    $this->contentRebuildAccessGrants($this->contentGetNodeByTitle($content_type, $title));
  }

  /**
   * Rebuild node access grants for all content.
   *
   * @code
   * When the access grants for all content are rebuilt
   * @endcode
   *
   * @actor:system
   */
  #[When('the access grants for all content are rebuilt')]
  public function contentRebuildAccessGrantsAll(): void {
    $this->backendFor(NodeCapabilityInterface::class)->rebuildNodeAccess();
  }

  /**
   * Set the path alias of a content with the specified title.
   *
   * The alias replaces any existing alias for the content. A missing leading
   * slash is added automatically, so both "about-us" and "/about-us" are
   * accepted.
   *
   * @code
   * When the path alias of the "article" content with the title "Test article" is set to "/my-test-article"
   * @endcode
   *
   * @actor:system
   */
  #[When('the path alias of the :content_type content with the title :title is set to :alias')]
  public function contentSetPathAliasWithTitle(string $content_type, string $title, string $alias): void {
    $this->backendFor(PathAliasCapabilityInterface::class);

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
   *
   * @actor:system
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
   *
   * @actor:system
   */
  #[Then('the :content_type content with the title :title should be published')]
  public function contentAssertPublishedWithTitle(string $content_type, string $title): void {
    $nid = $this->contentGetNidByTitle($content_type, $title);

    if (!$this->contentIsPublished($nid)) {
      throw new ExpectationException(sprintf('The "%s" content with the title "%s" should be published, but it is not (nid: %s).', $content_type, $title, $nid), $this->getSession()->getDriver());
    }
  }

  /**
   * Assert content with specified type and title is not published.
   *
   * @code
   * Then the "page" content with the title "Test page" should not be published
   * @endcode
   *
   * @actor:system
   */
  #[Then('the :content_type content with the title :title should not be published')]
  public function contentAssertNotPublishedWithTitle(string $content_type, string $title): void {
    $nid = $this->contentGetNidByTitle($content_type, $title);

    if ($this->contentIsPublished($nid)) {
      throw new ExpectationException(sprintf('The "%s" content with the title "%s" should not be published, but it is (nid: %s).', $content_type, $title, $nid), $this->getSession()->getDriver());
    }
  }

  /**
   * Visit a page of the content with a specified title.
   *
   * @param string $content_type
   *   The content type.
   * @param string $title
   *   The title of the content.
   * @param string $rel
   *   The link relationship of the page, such as 'edit-form', or 'canonical'
   *   for the content page.
   */
  public function contentVisitActionPageWithTitle(string $content_type, string $title, string $rel = 'canonical'): void {
    $node = $this->contentGetNodeByTitle($content_type, $title);

    $this->visitPath($this->backendFor(ContentCapabilityInterface::class)->getEntityUrl($node, $rel));
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
    if (!$this->backendFor(ConfigCapabilityInterface::class)->configExists('node.type.' . $content_type)) {
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
   * When several nodes of the same type share the title, the most recently
   * created one is returned.
   *
   * @param string $content_type
   *   The content type.
   * @param string $title
   *   The title of the content.
   *
   * @return \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface
   *   The stub of the node, carrying its 'nid'.
   *
   * @throws \RuntimeException
   *   When the content type does not exist or no node of it has the title.
   */
  public function contentGetNodeByTitle(string $content_type, string $title): EntityStubInterface {
    return new EntityStub('node', $content_type, ['nid' => $this->contentGetNidByTitle($content_type, $title)]);
  }

  /**
   * Check whether the default translation of a node is published.
   *
   * @param int $nid
   *   The node ID.
   *
   * @return bool
   *   TRUE when the node exists and is published, FALSE otherwise.
   */
  protected function contentIsPublished(int $nid): bool {
    return $this->queryEntityIds('node', ['nid' => $nid, 'status' => 1, 'default_langcode' => 1]) !== [];
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
    $backend = $this->backendFor(NodeCapabilityInterface::class);

    foreach ($this->queryNodeIds($content_type, $conditions) as $nid) {
      $backend->deleteNode(new EntityStub('node', $content_type, ['nid' => $nid]));
    }
  }

  /**
   * Set the moderation state of a node and save it.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $node
   *   The node stub, carrying its 'nid'.
   * @param string $state
   *   The moderation state.
   *
   * @throws \RuntimeException
   *   When no workflow for the content type of the node defines the state.
   */
  public function contentSetModerationState(EntityStubInterface $node, string $state): void {
    $content_type = $this->contentGetType($node);

    if (!$this->backendFor(WorkflowCapabilityInterface::class)->hasWorkflowState('node', $content_type, $state)) {
      throw new \RuntimeException(sprintf('State "%s" is not defined in the workflow for "%s" content type.', $state, $content_type));
    }

    $values = ['nid' => $this->queryGetEntityId($node, 'nid'), 'moderation_state' => $state];

    $this->backendFor(ContentCapabilityInterface::class)->updateEntity(new EntityStub('node', $content_type, $values));
  }

  /**
   * Get the content type of a node.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $node
   *   The node stub, carrying its 'nid'.
   *
   * @return string
   *   The bundle of the stub, or the type of the node when the stub carries
   *   no bundle.
   *
   * @throws \RuntimeException
   *   When the stub carries no bundle and the node does not exist.
   */
  protected function contentGetType(EntityStubInterface $node): string {
    $bundle = $node->getBundle();

    if ($bundle !== NULL && $bundle !== '') {
      return $bundle;
    }

    return $this->backendFor(ContentCapabilityInterface::class)->getEntityBundle($node);
  }

  /**
   * Rebuild the access grants of a node.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $node
   *   The node stub, carrying its 'nid'.
   *
   * @throws \RuntimeException
   *   When the node does not exist.
   */
  public function contentRebuildAccessGrants(EntityStubInterface $node): void {
    $this->backendFor(NodeCapabilityInterface::class)->rebuildNodeAccess((int) $this->queryGetEntityId($node, 'nid'));
  }

  /**
   * Set the path alias of a node, replacing any existing alias.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $node
   *   The node stub, carrying its 'nid'.
   * @param string $alias
   *   The alias, with or without its leading slash.
   */
  public function contentSetPathAlias(EntityStubInterface $node, string $alias): void {
    $this->backendFor(PathAliasCapabilityInterface::class)->setEntityPathAlias($node, $alias);
  }

  /**
   * Declares the prerequisites this trait asserts.
   *
   * @return array<int, \DrevOps\BehatSteps\Extension\Prerequisite\Prerequisite>
   *   The prerequisites this trait declares.
   */
  protected function contentPrerequisites(): array {
    return [
      Prerequisite::capability(PathAliasCapabilityInterface::class),
      Prerequisite::check(static fn(ModuleCapabilityInterface $backend): bool => $backend->moduleIsEnabled('path'), 'the core "path" module is enabled, for the path alias step'),
    ];
  }

}
