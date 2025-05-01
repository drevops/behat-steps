<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Drupal\block\Entity\Block;

/**
 * Creates, configures, and tests blocks.
 *
 * This trait enables programmatic management of blocks in the Drupal system,
 * including configuration, placement in regions, visibility settings, and
 * assertions about block state.
 */
trait BlockTrait {

  /**
   * Array of created block instances.
   *
   * @var array<int, \Drupal\block\Entity\Block>
   */
  protected static array $blockInstances = [];

  /**
   * Create, configure and place a block in the default theme region.
   *
   * The :label parameter must match an existing block's admin label.
   * The following table fields are supported:
   * - label: The visible block title
   * - label_display: Whether to display the block title (1 for yes, 0 for no)
   * - region: The theme region to place the block in (e.g. content, header)
   * - status: Block enabled status (1 for enabled, 0 for disabled).
   *
   * @code
   * @When I create a block of type :type with:
   * | label         | [TEST] Welcome Message      |
   * | label_display | 1                           |
   * | region        | sidebar_first               |
   * | status        | 1                           |
   * @endcode
   */
  public function blockPlaceBlockInRegion(string $type, TableNode $fields): void {
    /** @var \Drupal\Core\Block\BlockManagerInterface $block_manager */
    $block_manager = \Drupal::service('plugin.manager.block');
    $definitions = $block_manager->getDefinitions();
    $default_theme = \Drupal::config('system.theme')->get('default');
    $block = NULL;
    foreach ($definitions as $plugin_id => $definition) {
      if ((string) $definition['admin_label'] === $type) {
        $block = \Drupal::entityTypeManager()->getStorage('block')->create([
          'plugin' => $plugin_id,
          'theme' => $default_theme,
        ]);
        $suggestion = $block->getPlugin()->getMachineNameSuggestion();
        $block_id = \Drupal::service('block.repository')->getUniqueMachineName($suggestion, $block->getTheme());
        $block->set('id', $block_id);
        break;
      }
    }
    if (!$block instanceof Block) {
      throw new \Exception(sprintf('Could not create block of type "%s"', $type));
    }
    $this->blockConfigureBlockInstance($block, $fields);
    static::$blockInstances[] = $block;
  }

  /**
   * Clean up all blocks created during the scenario.
   *
   * This method automatically runs after each scenario to ensure clean
   * test state.
   * Add the tag @behat-steps-skip:AfterScenario to your scenario to
   * prevent automatic cleanup of blocks.
   *
   * @AfterScenario
   */
  public function blockAfterScenario(AfterScenarioScope $scope): void {
    if ($scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__)) {
      return;
    }

    foreach (static::$blockInstances as $block) {
      $block->delete();
    }
    static::$blockInstances = [];
  }

  /**
   * Find and configure an existing block identified by its label.
   *
   * This step finds a block in the default theme by its label and updates its
   * configuration with the provided values.
   *
   * Common configuration fields:
   * - label: The visible block title
   * - label_display: Whether to display the block title (1 for yes, 0 for no)
   * - region: The theme region to place the block in (e.g., sicontent)
   * - status: Block enabled status (1 for enabled, 0 for disabled).
   *
   * @code
   * When I configure the block with the label :label with:
   *  | label         | [TEST] Updated Message      |
   *  | label_display | 1                           |
   *  | region        | sidebar_second              |
   *  | status        | 1                           |
   * @endcode
   *
   * @When I configure the block with the label :label with:
   */
  public function blockConfigureBlock(string $label, TableNode $fields): void {
    $block = $this->blockLoadBlockByLabel($label);

    $this->blockConfigureBlockInstance($block, $fields);

    $block->save();
  }

  /**
   * Configure a block instance with the specified settings.
   *
   * Apply the configuration values from the table to the block instance.
   * Common configuration fields include:
   * - label: The visible block title
   * - label_display: Whether to display the block title (bool, 1 or 0)
   * - region: The theme region to place the block in
   * - status: Block enabled status (bool, 1 or 0).
   *
   * @param \Drupal\block\Entity\Block $block
   *   Block entity to be configured.
   * @param \Behat\Gherkin\Node\TableNode $fields
   *   Table of configuration fields and values.
   */
  protected function blockConfigureBlockInstance(Block $block, TableNode $fields): void {
    foreach ($fields->getRowsHash() as $field => $value) {
      switch ($field) {
        case 'label':
          $settings = $block->get('settings');
          $settings['label'] = $value;
          $block->set('settings', $settings);
          break;

        case 'label_display':
          $settings = $block->get('settings');
          $settings['label_display'] = (bool) $value;
          $block->set('settings', $settings);
          break;

        case 'region':
          $block->setRegion($value);
          break;

        case 'status':
          $block->setStatus((bool) $value);
          break;
      }
    }
    $block->save();
  }

  /**
   * Set a visibility condition for a block.
   *
   * Configure when a block should be displayed based on specific conditions.
   * Common condition types include:
   * - request_path: Control visibility based on the current path
   * - user_role: Control visibility based on user role
   * - language: Control visibility based on the interface language.
   *
   * @param string $condition
   *   The type of visibility condition.
   * @param string $label
   *   Label identifying the block.
   * @param \Behat\Gherkin\Node\TableNode $fields
   *   Configuration for the visibility condition.
   *
   * @code
   *   When I configure the visibility condition "request_path" for the block "[TEST] Block" with:
   *   | pages | /node/1\r\n/about |
   *   | negate | 0 |
   * @endcode
   *
   * @When I configure the visibility condition :condition for the block :label with:
   */
  public function blockConfigureBlockVisibility(string $condition, string $label, TableNode $fields): void {
    $block = $this->blockLoadBlockByLabel($label);
    $configuration = $fields->getRowsHash();
    $configuration['id'] = $condition;
    $block->setVisibilityConfig($condition, $configuration);
    $block->save();
  }

  /**
   * Remove a visibility condition from the specified block.
   *
   * This step removes any existing visibility restrictions of the specified
   * type from the block.
   *
   * @param string $condition
   *   The type of visibility condition to remove.
   * @param string $label
   *   Label identifying the block.
   *
   * @code
   *   When I remove the visibility condition "request_path" from the block "[TEST] Block"
   * @endcode
   *
   * @When I remove the visibility condition :condition from the block :label
   */
  public function blockRemoveBlockVisibility(string $condition, string $label): void {
    $this->blockConfigureBlockVisibility($condition, $label, new TableNode([]));
  }

  /**
   * Disable a block specified by its label.
   *
   * Make the block inactive so it will not be displayed on the site.
   * This is equivalent to unchecking the "Enabled" checkbox in the block UI.
   *
   * @param string $label
   *   Label used to identify the block.
   *
   * @code
   *   When I disable the block "[TEST] Sidebar Block"
   * @endcode
   *
   * @When I disable the block :label
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   When the block cannot be saved.
   */
  public function blockDisableBlock(string $label): void {
    $block = $this->blockLoadBlockByLabel($label);
    $block->disable();
    $block->save();
  }

  /**
   * Enable a block specified by its label.
   *
   * Make the block active so it will be displayed on the site.
   * This is equivalent to checking the "Enabled" checkbox in the block UI.
   *
   * @param string $label
   *   Label used to identify the block.
   *
   * @code
   *   When I enable the block "[TEST] Sidebar Block"
   * @endcode
   *
   * @When I enable the block :label
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   When the block cannot be saved.
   */
  public function blockEnableBlock(string $label): void {
    $block = $this->blockLoadBlockByLabel($label);
    $block->enable();
    $block->save();
  }

  /**
   * Assert that a block with the specified label exists in the default theme.
   *
   * This assertion checks for the existence of a block with the given label,
   * regardless of which region it's placed in or whether it's enabled.
   *
   * @param string $label
   *   The label (title) of the block to find.
   *
   * @code
   *   Then the block "[TEST] Footer Block" should exist
   * @endcode
   *
   * @Then the block :label should exist
   *
   * @throws \Exception
   *   When no block with the specified label is found.
   */
  public function blockAssertBlockExists(string $label): void {
    $default_theme = \Drupal::config('system.theme')->get('default');
    $blocks = \Drupal::entityTypeManager()
      ->getStorage('block')
      ->loadByProperties([
        'settings.label' => $label,
        'theme' => $default_theme,
      ]);

    if (empty($blocks)) {
      throw new \Exception(sprintf('The block "%s" was not found', $label));
    }
  }

  /**
   * Assert that a block with the specified label exists in a specific region.
   *
   * This assertion checks that a block with the given label has been placed
   * in the specified region of the current theme.
   *
   * @param string $label
   *   The label (title) of the block to find.
   * @param string $region
   *   The region to check for the block
   *
   * @code
   *   Then the block "[TEST] User Menu" should exist in the "sidebar_first" region
   * @endcode
   *
   * @Then the block :label should exist in the :region region
   *
   * @throws \Exception
   *   When no block with the specified label is found in the given region.
   */
  public function blockAssertBlockExistsInRegion(string $label, string $region): void {
    $blocks = \Drupal::entityTypeManager()
      ->getStorage('block')
      ->loadByProperties([
        'settings.label' => $label,
        'region' => $region,
      ]);

    if (empty($blocks)) {
      throw new \Exception(sprintf('Block with title "%s" was not found in region "%s"', $label, $region));
    }
  }

  /**
   * Assert that a block does not exist in a specific region.
   *
   * This assertion checks that a block with the given label has not been placed
   * in the specified region of the current theme. This is useful for verifying
   * that a block has been moved or removed from a region.
   *
   * @param string $label
   *   The label (title) of the block to check.
   * @param string $region
   *   The region to check for the absence of the block.
   *
   * @code
   *   Then the block "[TEST] User Menu" should not exist in the "content" region
   * @endcode
   *
   * @Then the block :label should not exist in the :region region
   *
   * @throws \Exception
   *   When a block with the specified label is found in the given region.
   */
  public function blockAssertBlockDoesNotExistInRegion(string $label, string $region): void {
    $blocks = \Drupal::entityTypeManager()
      ->getStorage('block')
      ->loadByProperties([
        'settings.label' => $label,
        'region' => $region,
      ]);

    if (!empty($blocks)) {
      throw new \Exception(sprintf('Block with title "%s" was found in region "%s but should not have been."', $label, $region));
    }
  }

  /**
   * Assert that a block has a specific visibility condition configured.
   *
   * This checks that a block has at least one visibility condition of the
   * specified type configured, such as path restrictions, role restrictions,
   * or language restrictions.
   *
   * @param string $label
   *   Label identifying the block.
   * @param string $condition
   *   The type of visibility condition to check for.
   *
   * @code
   *   Then the block "[TEST] Admin Block" should have the visibility condition "user_role"
   * @endcode
   *
   * @Then the block :label should have the visibility condition :condition
   *
   * @throws \Exception
   *   When the block does not have the specified visibility condition.
   */
  public function blockAssertBlockHasCondition(string $label, string $condition): void {
    $block = $this->blockLoadBlockByLabel($label);
    $conditions = $block->getVisibilityConditions();

    if (!$conditions->has($condition)) {
      throw new \Exception(sprintf('The block "%s" does not have condition "%s"', $label, $condition));
    }
  }

  /**
   * Assert that a block does not have a specific visibility condition.
   *
   * This checks that a block does not have any visibility conditions of the
   * specified type, meaning it is not restricted by that condition type.
   *
   * @param string $label
   *   Label identifying the block.
   * @param string $condition
   *   The type of visibility condition to check for.
   *
   * @code
   *   Then the block "[TEST] Public Block" should not have the visibility condition "user_role"
   * @endcode
   *
   * @Then the block :label should not have the visibility condition :condition
   *
   * @throws \Exception
   *   When the block has the specified visibility condition when it should not.
   */
  public function blockAssertNoCondition(string $label, string $condition): void {
    $block = $this->blockLoadBlockByLabel($label);
    $conditions = $block->getVisibilityConditions();

    if ($conditions->has($condition)) {
      throw new \Exception(sprintf('The block "%s" should not have the visibility condition "%s"', $label, $condition));
    }
  }

  /**
   * Assert that a block with the specified label is disabled (inactive).
   *
   * This assertion checks that a block exists but is not enabled for display
   * on the site. This is equivalent to verifying that the "Enabled" checkbox
   * in the block UI is unchecked.
   *
   * @param string $label
   *   Label to identify the block.
   *
   * @code
   *   Then the block "[TEST] Maintenance Block" should be disabled
   * @endcode
   *
   * @Then the block :label should be disabled
   *
   * @throws \Exception
   *   When the block is enabled when it should be disabled.
   */
  public function blockAssertBlockIsDisabled(string $label): void {
    $block = $this->blockLoadBlockByLabel($label);
    if ($block->status()) {
      throw new \Exception(sprintf('The block "%s" should be disabled but is enabled.', $label));
    }
  }

  /**
   * Assert that a block with the specified label is enabled.
   *
   * @param string $label
   *   Label to identify the block.
   *
   * @code
   *   Then the block "[TEST] Navigation Block" should be enabled
   * @endcode
   *
   * @Then the block :label should be enabled
   *
   * @throws \Exception
   *   When the block is disabled when it should be enabled.
   */
  public function blockAssertBlockIsEnabled(string $label): void {
    $block = $this->blockLoadBlockByLabel($label);
    if (!$block->status()) {
      throw new \Exception(sprintf('The block "%s" should be enabled but is disabled.', $label));
    }
  }

  /**
   * Load a block by its label.
   *
   * Search for a block in the default theme with the block label.
   *
   * @param string $label
   *   The visible label of the block to find.
   *
   * @return \Drupal\block\Entity\Block
   *   The loaded block entity.
   *
   * @throws \Exception
   *   When no block with the specified label is found.
   */
  private function blockLoadBlockByLabel(string $label): Block {
    $default_theme = \Drupal::config('system.theme')->get('default');
    $blocks = \Drupal::entityTypeManager()
      ->getStorage('block')
      ->loadByProperties([
        'theme' => $default_theme,
        'settings.label' => $label,
      ]);

    if (empty($blocks)) {
      throw new \Exception(sprintf('Block with label "%s" was not found', $label));
    }

    return reset($blocks);
  }

}
