<?php

declare(strict_types=1);

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Drupal\block_content\Entity\BlockContent;
use Behat\Gherkin\Node\TableNode;
use Drupal\block_content\BlockContentTypeInterface;

/**
 * Provides Behat step definitions for managing block_content entities.
 */
trait BlockContentTrait {

  /**
   * Array of created block_content entities.
   *
   * @var array<int,\Drupal\block_content\Entity\BlockContent>
   */
  protected static $blockContentEntities = [];

  /**
   * Clean all block_content instances after scenario run.
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
   * Assert that a vocabulary exist.
   *
   * @code
   * Given block_content_type "civictheme_search" with description "Search" exists
   * @endcode
   *
   * @Given block_content_type :type with description :description exists
   */
  public function contentBlockAssertTypeExist(string $description, string $type): void {
    $block_content_type = \Drupal::entityTypeManager()->getStorage('block_content_type')->load($type);

    if (!$block_content_type instanceof BlockContentTypeInterface) {
      throw new \Exception(sprintf('"%s" block_content_type does not exist', $type));
    }

    if ($block_content_type->get('info') !== $description) {
      throw new \Exception(sprintf('"%s" block_content_type name is not "%s"', $type, $description));
    }
  }

  /**
   * Assert that a block_content exist by name.
   *
   * @code
   * Given block_content "Search" of block_content_type "civictheme_search" exists
   * @endcode
   *
   * @Given block_content :name of block_content_type :type exists
   */
  public function contentBlockAssertTermExistsByName(string $name, string $type): void {
    $block_content_type = \Drupal::entityTypeManager()->getStorage('block_content_type')->load($type);

    if ($block_content_type instanceof BlockContentTypeInterface) {
      throw new \Exception(sprintf('"%s" block_content_type does not exist', $type));
    }

    $found = \Drupal::entityTypeManager()
      ->getStorage('block_content')
      ->loadByProperties([
        'info' => $name,
        'type' => $block_content_type,
      ]);

    if (count($found) == 0) {
      throw new \Exception(sprintf('Block content "%s" of type "%s" does not exist', $name, $type));
    }
  }

  /**
   * Remove content blocks from a specified content_block_type.
   *
   * @code
   * Given no "Fruits" content blocks:
   * | Apple |
   * | Pear  |
   * @endcode
   *
   * @Given no :type block_content:
   */
  public function contentBlockDeleteTerms(string $type, TableNode $contentBlockTable): void {
    foreach ($contentBlockTable->getColumn(0) as $description) {
      $content_blocks = \Drupal::entityTypeManager()->getStorage('block_content')->loadByProperties([
        'description' => $description,
        'type' => $type,
      ]);

      foreach ($content_blocks as $content_block) {
        /** @var \Drupal\block_content\Entity\BlockContent $content_block */
        $content_block->delete();
      }
    }
  }

  /**
   * Visit specified block_content_type block_content edit page.
   *
   * @When I edit :type block_content_type with description :info
   */
  public function blockContentEditBlockContentPageWithName(string $type, string $info): void {
    $block_ids = $this->contentBlockLoadMultiple($type, [
      'info' => $info,
    ]);

    if (empty($block_ids)) {
      throw new \RuntimeException(sprintf('Unable to find %s term "%s"', $type, $info));
    }

    ksort($block_ids);
    $block_id = end($block_ids);

    $path = $this->locatePath('/admin/content/block/' . $block_id . '/edit');
    print $path;

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

  /**
   * Creates block_content of a given type with field data.
   *
   * @param string $type
   *   Content block_content type.
   * @param \Behat\Gherkin\Node\TableNode $block_content_table
   *   Field data for a block_content.
   *
   * @Given :type block_content:
   *
   * @code
   *   Given "help" block_content:
   *  | title    | status | created           |
   *  | My title | 1      | 2014-10-17 8:00am |
   * @endcode
   */
  public function createBlockContents(string $type, TableNode $block_content_table): void {
    foreach ($block_content_table->getHash() as $blockContentHash) {
      $this->createBlockContent($type, $blockContentHash);
    }
  }

  /**
   * Create a block content entity and place if region set.
   *
   * @param string $type
   *   Block content type.
   * @param array<string> $values
   *   Values for block content entity.
   *
   * @return \Drupal\block_content\Entity\BlockContent
   *   Created block content entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
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
