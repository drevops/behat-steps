<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Drupal;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Drupal\block_content\BlockContentTypeInterface;
use Drupal\block_content\Entity\BlockContent;
use Drupal\Core\Entity\EntityStorageException;

/**
 * Manage Drupal content blocks.
 *
 * - Define reusable custom block content with structured field data.
 * - Create, edit, and verify block_content entities by type and description.
 * - Automatically clean up created entities after scenario completion.
 *
 * Skip processing with tag: `@behat-steps-skip:contentBlockAfterScenario`
 */
trait ContentBlockTrait {

  /**
   * Array of created block_content entities.
   *
   * @var array<int,\Drupal\block_content\Entity\BlockContent>
   */
  protected static $contentBlockEntities = [];

  /**
   * Clean up all content block entities created during the scenario.
   *
   * @AfterScenario
   */
  public function contentBlockAfterScenario(AfterScenarioScope $scope): void {
    if ($scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__)) {
      return;
    }

    foreach (static::$contentBlockEntities as $key => $block_content) {
      try {
        $block_content->delete();
      }
      catch (EntityStorageException) {
        // Ignore “already deleted” errors to keep teardown resilient.
      }
      unset(static::$contentBlockEntities[$key]);
    }
  }

  /**
   * Assert that a content block type exists.
   *
   * @code
   * Then the content block type "Search" should exist
   * @endcode
   *
   * @Then the content block type :type should exist
   */
  public function contentBlockAssertTypeExist(string $type): void {
    $block_content_type = \Drupal::entityTypeManager()->getStorage('block_content_type')->load($type);

    if (!$block_content_type instanceof BlockContentTypeInterface) {
      throw new \Exception(sprintf('"%s" content block type does not exist', $type));
    }
  }

  /**
   * Remove content blocks of a specified type with the given descriptions.
   *
   * Delete all content blocks of the specified type that match any of the
   * descriptions (titles) provided in the table.
   *
   * @code
   * Given the following "basic" content blocks do not exist:
   * | [TEST] Footer Block  |
   * | [TEST] Contact Form  |
   * @endcode
   *
   * @Given the following :type content blocks do not exist:
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   When the entity cannot be deleted.
   */
  public function contentBlockDelete(string $type, TableNode $content_block_table): void {
    foreach ($content_block_table->getColumn(0) as $description) {
      $content_blocks = \Drupal::entityTypeManager()->getStorage('block_content')->loadByProperties([
        'info' => $description,
        'type' => $type,
      ]);

      foreach ($content_blocks as $content_block) {
        /** @var \Drupal\block_content\Entity\BlockContent $content_block */
        $content_block->delete();
      }
    }
  }

  /**
   * Navigate to the edit page for a specified content block.
   *
   * Find a content block by its type and description (admin title) and
   * navigate to its edit page. Throws an exception if no matching block
   * is found.
   *
   * @code
   * When I edit the "basic" content block with the description "[TEST] Footer Block"
   * @endcode
   *
   * @When I edit the :type content block with the description :description
   */
  public function contentBlockEditBlockContentWithDescription(string $type, string $description): void {
    $block_ids = $this->contentBlockLoadMultiple($type, [
      'info' => $description,
    ]);

    if (empty($block_ids)) {
      throw new \RuntimeException(sprintf('Unable to find "%s" content block with the description "%s"', $type, $description));
    }

    ksort($block_ids);
    $block_id = end($block_ids);

    $path = $this->locatePath('/admin/content/block/' . $block_id);
    $this->getSession()->visit($path);
  }

  /**
   * Create content blocks of the specified type with the given field values.
   *
   * This step creates new content block (block_content) entities with
   * the specified field values.
   * Each row in the table creates a separate block entity of the given type.
   *
   * Required fields:
   * - info (or title): The block's admin title/label
   *
   * Common optional fields:
   * - status: Published status (1 for published, 0 for unpublished)
   * - created: Creation timestamp (format: YYYY-MM-DD H:MMam/pm)
   * - body: Block content (for blocks with a body field)
   *
   * @param string $type
   *   The content block type machine name.
   * @param \Behat\Gherkin\Node\TableNode $content_block_table
   *   Table containing field values for each block to create.
   *
   * @Given the following :type content blocks exist:
   *
   * @code
   *   Given the following "basic" content blocks exist:
   *   | info                  | status | body                   | created           |
   *   | [TEST] Footer Contact | 1      | Call us at 555-1234    | 2023-01-17 8:00am |
   *   | [TEST] Copyright      | 1      | © 2023 Example Company | 2023-01-18 9:00am |
   * @endcode
   */
  public function contentBlockCreate(string $type, TableNode $content_block_table): void {
    foreach ($content_block_table->getHash() as $hash) {
      $this->contentBlockCreateSingle($type, $hash);
    }
  }

  /**
   * Create a block content entity with the specified type and field values.
   *
   * This internal helper method creates and saves a single content block
   * entity.
   * Created entities are stored in the static $blockContentEntities array for
   * automatic cleanup after the scenario.
   *
   * @param string $type
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
  protected function contentBlockCreateSingle(string $type, array $values): BlockContent {
    $values = (object) $values;
    $values->type = $type;
    $this->parseEntityFields('block_content', $values);
    $values = (array) $values;

    /** @var \Drupal\block_content\Entity\BlockContent $entity */
    $entity = BlockContent::create($values);
    $entity->save();

    static::$contentBlockEntities[] = $entity;

    return $entity;
  }

  /**
   * Load multiple content blocks with specified type and conditions.
   *
   * @param string $type
   *   The block content type.
   * @param array<string,string> $conditions
   *   Conditions keyed by field names.
   *
   * @return array<int, string>
   *   Array of block content ids.
   */
  protected function contentBlockLoadMultiple(string $type, array $conditions = []): array {
    $query = \Drupal::entityQuery('block_content')
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
