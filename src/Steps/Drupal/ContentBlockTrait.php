<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Exception\ExpectationException;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use DrevOps\BehatSteps\Backend\Capability\ContentCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\BlockCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\ConfigCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\ModuleCapabilityInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;
use DrevOps\BehatSteps\Extension\Prerequisite\Prerequisite;
use DrevOps\BehatSteps\Steps\Helper\Drupal\EntityLifecycleTrait;
use DrevOps\BehatSteps\Steps\Helper\Drupal\QueryTrait;
use DrevOps\BehatSteps\Steps\Helper\Web\TableTransposeTrait;

/**
 * Manage Drupal content blocks.
 *
 * - Define reusable custom block content with structured field data.
 * - Create and verify block_content entities by type and description, and
 *   visit their edit pages.
 * - Created entities are automatically removed at the end of the scenario.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\WebRawContext
 */
trait ContentBlockTrait {

  use EntityLifecycleTrait;
  use QueryTrait;
  use TableTransposeTrait;

  /**
   * Remove content blocks of a specified type with the given descriptions.
   *
   * @code
   * Given the following "basic" content blocks do not exist:
   *   | [TEST] Footer Block  |
   *   | [TEST] Contact Form  |
   * @endcode
   *
   * @actor:system
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   When the entity cannot be deleted.
   */
  #[Given('the following :content_block_type content blocks do not exist:')]
  public function contentBlockDeleteMultiple(string $content_block_type, TableNode $content_block_table): void {
    $this->backendFor(BlockCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    foreach ($content_block_table->getColumn(0) as $description) {
      $this->contentBlockDelete($content_block_type, ['info' => $description]);
    }
  }

  /**
   * Create content blocks of the specified type with the given field values.
   *
   * Each row in the table creates a separate block entity of the given type.
   *
   * Required fields:
   * - info: The block's admin title/label
   *
   * Common optional fields:
   * - status: Published status (1 for published, 0 for unpublished)
   * - body: Block content (for blocks with a body field)
   *
   * @param string $content_block_type
   *   The content block type machine name.
   * @param \Behat\Gherkin\Node\TableNode $content_block_table
   *   Table containing field values for each block to create.
   *
   * @code
   *   Given the following "basic" content blocks exist:
   *     | info                  | status | body                   |
   *     | [TEST] Footer Contact | 1      | Call us at 555-1234    |
   *     | [TEST] Copyright      | 1      | © 2023 Example Company |
   * @endcode
   *
   * @actor:system
   */
  #[Given('the following :content_block_type content blocks exist:')]
  public function contentBlockCreateMultiple(string $content_block_type, TableNode $content_block_table): void {
    foreach ($content_block_table->getHash() as $hash) {
      $this->contentBlockCreate($content_block_type, $hash);
    }
  }

  /**
   * Create content blocks with vertical field format.
   *
   * Supports both single and multiple entity creation using vertical table
   * format where fields are listed in rows instead of columns.
   *
   * @param string $content_block_type
   *   The content block type machine name.
   * @param \Behat\Gherkin\Node\TableNode $table
   *   Vertical format table with field names in first column.
   *
   * @code
   *   Given the following basic content blocks with fields exist:
   *     | info   | [TEST] Block 1        | [TEST] Block 2        |
   *     | body   | First block content   | Second block content  |
   *     | status | 1                     | 1                     |
   * @endcode
   *
   * @actor:system
   */
  #[Given('the following :content_block_type content blocks with fields exist:')]
  public function contentBlockCreateMultipleWithFields(string $content_block_type, TableNode $table): void {
    $entities = $this->tableTransposeVertical($table);

    foreach ($entities as $entity_data) {
      $this->contentBlockCreate($content_block_type, $entity_data);
    }
  }

  /**
   * Visit the edit page of the content block with the specified description.
   *
   * @code
   * When I visit the "basic" content block edit page with the description "[TEST] Footer Block"
   * @endcode
   *
   * @actor:person
   */
  #[When('I visit the :content_block_type content block edit page with the description :description')]
  public function contentBlockVisitEditPageWithDescription(string $content_block_type, string $description): void {
    $content_block = $this->contentBlockGetByDescription($content_block_type, $description);

    $this->visitPath($this->backendFor(ContentCapabilityInterface::class)->getEntityUrl($content_block, 'edit-form'));
  }

  /**
   * Assert that a content block type exists.
   *
   * @code
   * Then the content block type "Search" should exist
   * @endcode
   *
   * @actor:system
   */
  #[Then('the content block type :content_block_type should exist')]
  public function contentBlockAssertTypeExists(string $content_block_type): void {
    $config = $this->backendFor(ConfigCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    if (!$config->configExists('block_content.type.' . $content_block_type)) {
      throw new ExpectationException(sprintf('The content block type "%s" does not exist.', $content_block_type), $this->getSession()->getDriver());
    }
  }

  /**
   * Create a block content entity with the specified type and field values.
   *
   * Created entities are registered with the shared entity registry so that
   * they are removed at the end of the scenario.
   *
   * @param string $content_block_type
   *   The machine name of the block content type.
   * @param array<string> $values
   *   Associative array of field values for the content block entity.
   *   Common fields include:
   *   - info: The admin title/label (required)
   *   - body: The body field value (optional)
   *   - status: Published status (optional, 1 = published, 0 = unpublished)
   *
   * @return \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface
   *   The stub of the created content block, carrying its 'id'.
   */
  public function contentBlockCreate(string $content_block_type, array $values): EntityStubInterface {
    $backend = $this->backendFor(BlockCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    $values['type'] = $content_block_type;
    $stub = new EntityStub('block_content', $content_block_type, $values);
    $this->entityLifecycleParseFields($stub);

    $created = $backend->createBlockContent($stub);

    $this->entityLifecycleRegister($created, 'id');

    return $created;
  }

  /**
   * Load multiple content blocks with specified type and conditions.
   *
   * @param string $content_block_type
   *   The block content type.
   * @param array<string, string> $conditions
   *   Conditions keyed by field names.
   *
   * @return array<int|string, \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface>
   *   A stub per matching content block carrying its 'id', keyed by ID, or
   *   an empty array when none match.
   */
  public function contentBlockLoadMultiple(string $content_block_type, array $conditions = []): array {
    $this->assertPrerequisites(__TRAIT__);

    return $this->queryEntityStubs('block_content', 'id', $conditions, $content_block_type);
  }

  /**
   * Get the newest content block with a description.
   *
   * @param string $content_block_type
   *   The block content type.
   * @param string $description
   *   The block description.
   *
   * @return \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface
   *   The stub of the content block created last, carrying its 'id'.
   *
   * @throws \RuntimeException
   *   When no content block of the type has the description.
   */
  public function contentBlockGetByDescription(string $content_block_type, string $description): EntityStubInterface {
    $this->assertPrerequisites(__TRAIT__);

    $id = $this->queryFindNewestEntityId('block_content', ['info' => $description], $content_block_type);

    if ($id === NULL) {
      throw new \RuntimeException(sprintf('Unable to find "%s" content block with the description "%s".', $content_block_type, $description));
    }

    return new EntityStub('block_content', $content_block_type, ['id' => $id]);
  }

  /**
   * Delete the content blocks of a type that match conditions.
   *
   * @param string $content_block_type
   *   The block content type.
   * @param array<string, string> $conditions
   *   Conditions keyed by field names.
   */
  public function contentBlockDelete(string $content_block_type, array $conditions): void {
    $backend = $this->backendFor(BlockCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    foreach ($this->queryEntityIds('block_content', $conditions, $content_block_type) as $id) {
      $backend->deleteBlockContent(new EntityStub('block_content', $content_block_type, ['id' => $id]));
    }
  }

  /**
   * Declares the prerequisites this trait asserts.
   *
   * @return array<int, \DrevOps\BehatSteps\Extension\Prerequisite\Prerequisite>
   *   The prerequisites this trait declares.
   */
  protected function contentBlockPrerequisites(): array {
    return [
      Prerequisite::capability(BlockCapabilityInterface::class),
      Prerequisite::check(static fn(ModuleCapabilityInterface $backend): bool => $backend->moduleIsEnabled('block_content'), 'the core "block_content" module is enabled'),
    ];
  }

}
