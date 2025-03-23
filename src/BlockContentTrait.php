<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Drupal\block_content\Entity\BlockContent;
use Behat\Gherkin\Node\TableNode;
use Drupal\block_content\BlockContentTypeInterface;

/**
 * Provides Behat step definitions for managing custom block content entities.
 *
 * This trait enables programmatic management of custom block_content
 * entities in Drupal, including creation, validation, and editing operations.
 * These reusable content blocks can be placed in regions using the BlockTrait.
 */
trait BlockContentTrait {

  /**
   * Array of created block_content entities.
   *
   * @var array<int,\Drupal\block_content\Entity\BlockContent>
   */
  protected static $blockContentEntities = [];

  /**
   * Cleans up all custom block content entities created during the scenario.
   *
   * This method automatically runs after each scenario to ensure clean test
   * state.
   * Add the tag @behat-steps-skip:blockContentCleanAll to your scenario to
   * prevent automatic cleanup of block content entities.
   *
   * @AfterScenario
   */
  public function blockContentCleanAll(AfterScenarioScope $scope): void {
    // Allow to skip this by adding a tag.
    if ($scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__)) {
      return;
    }

    foreach (static::$blockContentEntities as $block_content) {
      $block_content->delete();
    }
    static::$blockContentEntities = [];
  }

  /**
   * Verifies that a custom block type exists.
   *
   * @code
   * Given "search" block_content type exists
   * @endcode
   *
   * @Given :type block_content type exists
   */
  public function blockContentAssertTypeExist(string $type): void {
    $block_content_type = \Drupal::entityTypeManager()->getStorage('block_content_type')->load($type);

    if (!$block_content_type instanceof BlockContentTypeInterface) {
      throw new \Exception(sprintf('"%s" block_content_type does not exist', $type));
    }
  }

  /**
   * Removes custom blocks of a specified type with the given descriptions.
   *
   * Deletes all custom blocks of the specified type that match any of the
   * descriptions (titles) provided in the table.
   *
   * @code
   * Given no "basic" block_content:
   * | [TEST] Footer Block  |
   * | [TEST] Contact Form  |
   * @endcode
   *
   * @Given no :type block_content:
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   When the entity cannot be deleted.
   */
  public function blockContentDelete(string $type, TableNode $contentBlockTable): void {
    foreach ($contentBlockTable->getColumn(0) as $description) {
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
   * Navigates to the edit page for a specified custom block.
   *
   * Finds a custom block by its type and description (admin title) and
   * navigates to its edit page. Throws an exception if no matching block
   * is found.
   *
   * @code
   * When I edit "basic" block_content_type with description "[TEST] Footer Block"
   * @endcode
   *
   * @When I edit :type block_content_type with description :description
   */
  public function blockContentEditBlockContentWithDescription(string $type, string $description): void {
    $block_ids = $this->blockContentLoadMultiple($type, [
      'info' => $description,
    ]);

    if (empty($block_ids)) {
      throw new \RuntimeException(sprintf('Unable to find %s block content with description "%s"', $type, $description));
    }

    ksort($block_ids);
    $block_id = end($block_ids);

    $path = $this->locatePath('/admin/content/block/' . $block_id);
    $this->getSession()->visit($path);
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
  protected function blockContentLoadMultiple(string $type, array $conditions = []): array {
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

  /**
   * Creates custom blocks of the specified type with the given field values.
   *
   * This step creates new custom block (block_content) entities with
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
   *   The custom block type machine name.
   * @param \Behat\Gherkin\Node\TableNode $block_content_table
   *   Table containing field values for each block to create.
   *
   * @Given :type block_content:
   *
   * @code
   *   Given "basic" block_content:
   *   | info                  | status | body                   | created           |
   *   | [TEST] Footer Contact | 1      | Call us at 555-1234    | 2023-01-17 8:00am |
   *   | [TEST] Copyright      | 1      | © 2023 Example Company | 2023-01-18 9:00am |
   * @endcode
   */
  public function blockContentCreate(string $type, TableNode $block_content_table): void {
    foreach ($block_content_table->getHash() as $blockContentHash) {
      $this->createBlockContent($type, $blockContentHash);
    }
  }

  /**
   * Creates a block content entity with the specified type and field values.
   *
   * This internal helper method creates and saves a single block content
   * entity.
   * Created entities are stored in the static $blockContentEntities array for
   * automatic cleanup after the scenario.
   *
   * @param string $type
   *   The machine name of the block content type.
   * @param array<string> $values
   *   Associative array of field values for the block content entity.
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
   *
   * @SuppressWarnings(PHPMD.StaticAccess)
   */
  protected function createBlockContent(string $type, array $values): BlockContent {
    $block_content = (object) $values;
    $block_content->type = $type;
    $this->parseEntityFields('block_content', $block_content);
    $block_content = (array) $block_content;
    /** @var \Drupal\block_content\Entity\BlockContent $block_content */
    $block_content = BlockContent::create($block_content);
    $block_content->save();
    static::$blockContentEntities[] = $block_content;

    return $block_content;
  }

}
