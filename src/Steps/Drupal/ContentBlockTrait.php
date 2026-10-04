<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Exception\ExpectationException;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use DrevOps\BehatSteps\Backend\Capability\CoreCapabilityInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Helper\Drupal\EntityLifecycleTrait;
use DrevOps\BehatSteps\Helper\Drupal\QueryTrait;
use DrevOps\BehatSteps\Helper\Web\TableTransposeTrait;
use Drupal\block_content\BlockContentTypeInterface;
use Drupal\block_content\Entity\BlockContent;

/**
 * Manage Drupal content blocks.
 *
 * - Define reusable custom block content with structured field data.
 * - Create and verify block_content entities by type and description, and
 *   visit their edit pages.
 * - Created entities are automatically removed at the end of the scenario.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
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
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   When the entity cannot be deleted.
   */
  #[Given('the following :content_block_type content blocks do not exist:')]
  public function contentBlockDelete(string $content_block_type, TableNode $content_block_table): void {
    $this->backendFor(CoreCapabilityInterface::class);

    foreach ($content_block_table->getColumn(0) as $description) {
      $content_blocks = $this->contentBlockLoadMultiple($content_block_type, [
        'info' => $description,
      ]);

      foreach ($content_blocks as $content_block) {
        $content_block->delete();
      }
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
   */
  #[Given('the following :content_block_type content blocks exist:')]
  public function contentBlockCreate(string $content_block_type, TableNode $content_block_table): void {
    foreach ($content_block_table->getHash() as $hash) {
      $this->contentBlockCreateSingle($content_block_type, $hash);
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
   */
  #[Given('the following :content_block_type content blocks with fields exist:')]
  public function contentBlockCreateWithFields(string $content_block_type, TableNode $table): void {
    $entities = $this->tableTransposeVertical($table);

    foreach ($entities as $entity_data) {
      $this->contentBlockCreateSingle($content_block_type, $entity_data);
    }
  }

  /**
   * Visit the edit page of the content block with the specified description.
   *
   * @code
   * When I visit the "basic" content block edit page with the description "[TEST] Footer Block"
   * @endcode
   */
  #[When('I visit the :content_block_type content block edit page with the description :description')]
  public function contentBlockVisitEditPageWithDescription(string $content_block_type, string $description): void {
    $content_blocks = $this->contentBlockLoadMultiple($content_block_type, [
      'info' => $description,
    ]);

    if (empty($content_blocks)) {
      throw new \RuntimeException(sprintf('Unable to find "%s" content block with the description "%s".', $content_block_type, $description));
    }

    ksort($content_blocks);
    $block_id = end($content_blocks)->id();

    $path = $this->locatePath('/admin/content/block/' . $block_id);
    $this->getSession()->visit($path);
  }

  /**
   * Assert that a content block type exists.
   *
   * @code
   * Then the content block type "Search" should exist
   * @endcode
   */
  #[Then('the content block type :content_block_type should exist')]
  public function contentBlockAssertTypeExists(string $content_block_type): void {
    $this->backendFor(CoreCapabilityInterface::class);

    $block_content_type = \Drupal::entityTypeManager()->getStorage('block_content_type')->load($content_block_type);

    if (!$block_content_type instanceof BlockContentTypeInterface) {
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
   * @return \Drupal\block_content\Entity\BlockContent
   *   The created block content entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   When the entity cannot be saved.
   */
  public function contentBlockCreateSingle(string $content_block_type, array $values): BlockContent {
    $this->backendFor(CoreCapabilityInterface::class);

    $values['type'] = $content_block_type;
    $stub = new EntityStub('block_content', $content_block_type, $values);
    $this->entityLifecycleParseFields($stub);

    /** @var \Drupal\block_content\Entity\BlockContent $entity */
    $entity = BlockContent::create($stub->getValues());
    $entity->save();

    $this->entityLifecycleRegister($entity);

    return $entity;
  }

  /**
   * Load multiple content blocks with specified type and conditions.
   *
   * @param string $content_block_type
   *   The block content type.
   * @param array<string, string> $conditions
   *   Conditions keyed by field names.
   *
   * @return array<int, \Drupal\block_content\BlockContentInterface>
   *   The matching content blocks keyed by ID, or an empty array when none
   *   match.
   */
  public function contentBlockLoadMultiple(string $content_block_type, array $conditions = []): array {
    $ids = $this->queryEntityIds('block_content', $conditions, $content_block_type);

    return $ids ? BlockContent::loadMultiple($ids) : [];
  }

}
