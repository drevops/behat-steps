<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Drupal\block\Entity\Block;

/**
 * Provides Behat step definitions for creating, configuring, and testing Drupal blocks.
 *
 * This trait enables programmatic management of blocks in the Drupal system,
 * including configuration, placement in regions, visibility settings, and assertions
 * about block state. All operations are performed in the site's default theme.
 */
trait BlockTrait {

  /**
   * Array of created block instances.
   *
   * @var array<int, \Drupal\block\Entity\Block>
   */
  protected static array $blockInstances = [];

  /**
   * Creates, configures and places a block in the default theme region.
   *
   * The :label parameter must match an existing block's admin label.
   * The following table fields are supported:
   * - label: The visible block title
   * - label_display: Whether to display the block title (1 for yes, 0 for no)
   * - region: The theme region to place the block in (e.g., sidebar_first, content, header)
   * - status: Block enabled status (1 for enabled, 0 for disabled)
   *
   * @code
   * @When I create a block of type :label with:
   * | label         | [TEST] Welcome Message      |
   * | label_display | 1                           |
   * | region        | sidebar_first               |
   * | status        | 1                           |
   * @endcode
   *
   * @SuppressWarnings(PHPMD.StaticAccess)
   */
  public function blockPlaceBlockInRegion(string $label, TableNode $fields): void {
    /** @var \Drupal\Core\Block\BlockManagerInterface $block_manager */
    $block_manager = \Drupal::service('plugin.manager.block');
    $definitions = $block_manager->getDefinitions();
    $default_theme = \Drupal::config('system.theme')->get('default');
    $block = NULL;
    foreach ($definitions as $plugin_id => $definition) {
      if ((string) $definition['admin_label'] === $label) {
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
      throw new \Exception(sprintf('Could not create block of type "%s"', $label));
    }
    $this->blockConfigureBlockInstance($block, $fields);
    static::$blockInstances[] = $block;
  }

  /**
   * Cleans up all blocks created during the scenario.
   *
   * This method automatically runs after each scenario to ensure clean test state.
   * Add the tag @behat-steps-skip:blockInstanceCleanAll to your scenario to prevent
   * automatic cleanup of blocks.
   *
   * @AfterScenario
   */
  public function blockInstanceCleanAll(AfterScenarioScope $scope): void {
    // Allow to skip this by adding a tag.
    if ($scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__)) {
      return;
    }

    foreach (static::$blockInstances as $block) {
      $block->delete();
    }
    static::$blockInstances = [];
  }

  /**
   * Finds and configures an existing block identified by its label.
   *
   * This step finds a block in the default theme by its label and updates its 
   * configuration with the provided values.
   *
   * Supported configuration fields:
   * - label: The visible block title
   * - label_display: Whether to display the block title (1 for yes, 0 for no)
   * - region: The theme region to place the block in (e.g., sidebar_first, content)
   * - status: Block enabled status (1 for enabled, 0 for disabled)
   *
   * @code
   * @When I configure the block with the label :label with:
   *  | label         | [TEST] Updated Message      |
   *  | label_display | 1                           |
   *  | region        | sidebar_second              |
   *  | status        | 1                           |
   * @endcode
   */
  public function blockConfigureBlock(string $description, TableNode $fields): void {
    $block = $this->blockLoadBlockByLabel($description);

    $this->blockConfigureBlockInstance($block, $fields);

    $block->save();
  }

  /**
   * Configures a block instance with the specified settings.
   *
   * Applies the configuration values from the table to the block instance and saves
   * the changes. Supported configuration fields include:
   * - label: The visible block title
   * - label_display: Whether to display the block title (bool, 1 or 0)
   * - region: The theme region to place the block in
   * - status: Block enabled status (bool, 1 or 0)
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
   * Sets a visibility condition for a block.
   *
   * Configures when a block should be displayed based on specific conditions.
   * Common condition types include:
   * - request_path: Control visibility based on the current path
   * - user_role: Control visibility based on user role
   * - language: Control visibility based on the interface language
   *
   * @param string $label
   *   Label identifying the block.
   * @param string $condition
   *   The type of visibility condition (e.g., 'request_path', 'user_role', 'language').
   * @param \Behat\Gherkin\Node\TableNode $fields
   *   Configuration for the visibility condition.
   *
   * @code
   * When I configure a visibility condition "request_path" for the block with label "[TEST] Block"
   * | pages | /node/1\r\n/about |
   * | negate | 0 |
   * @endcode
   *
   * @When I configure a visibility condition :condition for the block with label :label
   */
  public function blockConfigureBlockVisibility(string $label, string $condition, TableNode $fields): void {
    $block = $this->blockLoadBlockByLabel($label);
    $configuration = $fields->getRowsHash();
    $configuration['id'] = $condition;
    $block->setVisibilityConfig($condition, $configuration);
    $block->save();
  }

  /**
   * Removes a visibility condition from the specified block.
   *
   * This step removes any existing visibility restrictions of the specified type
   * from the block, making it visible regardless of that condition.
   *
   * @param string $label
   *   Label identifying the block.
   * @param string $condition
   *   The type of visibility condition to remove (e.g., 'request_path', 'user_role').
   *
   * @code
   * When I remove the visibility condition "request_path" from the block with label "[TEST] Block"
   * @endcode
   *
   * @When I remove the visibility condition :condition from the block with label :label
   */
  public function blockRemoveBlockVisibility(string $label, string $condition): void {
    $this->blockConfigureBlockVisibility($label, $condition, new TableNode([]));
  }

  /**
   * Disables a block specified by its label.
   *
   * Makes the block inactive so it will not be displayed on the site.
   * This is equivalent to unchecking the "Enabled" checkbox in the block UI.
   *
   * @param string $label
   *   Label used to identify the block.
   *
   * @code
   * When I disable the block with label "[TEST] Sidebar Block"
   * @endcode
   *
   * @When I disable the block with label :label
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
   * Enables a block specified by its label.
   *
   * Makes the block active so it will be displayed on the site.
   * This is equivalent to checking the "Enabled" checkbox in the block UI.
   *
   * @param string $label
   *   Label used to identify the block.
   *
   * @code
   * When I enable the block with label "[TEST] Sidebar Block"
   * @endcode
   *
   * @When I enable the block with label :label
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
   * Verifies that a block with the specified label exists in the default theme.
   *
   * This assertion checks for the existence of a block with the given label,
   * regardless of which region it's placed in or whether it's enabled.
   *
   * @param string $label
   *   The label (title) of the block to find.
   *
   * @code
   * Then block with label "[TEST] Footer Block" should exist
   * @endcode
   *
   * @Then block with label :label should exist
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
      throw new \Exception(sprintf('Block with title "%s" was not found', $label));
    }
  }

  /**
   * Verifies that a block with the specified label exists in a specific region.
   *
   * This assertion checks that a block with the given label has been placed
   * in the specified region of the current theme.
   *
   * @param string $label
   *   The label (title) of the block to find.
   * @param string $region
   *   The region to check for the block (e.g., 'sidebar_first', 'content', 'header').
   *
   * @code
   * Then block with label "[TEST] User Menu" should exist in the region "sidebar_first"
   * @endcode
   *
   * @When block with label :label should exist in the region :region
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
   * Verifies that a block with the specified label does not exist in a specific region.
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
   * Then block with label "[TEST] User Menu" should not exist in the region "content"
   * @endcode
   *
   * @When block with label :label should not exist in the region :region
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
   * Verifies that a block has a specific visibility condition configured.
   *
   * This checks that a block has at least one visibility condition of the
   * specified type configured, such as path restrictions, role restrictions,
   * or language restrictions.
   *
   * @param string $label
   *   Label identifying the block.
   * @param string $condition
   *   The type of visibility condition to check for (e.g., 'request_path', 'user_role').
   *
   * @code
   * Then the block with label "[TEST] Admin Block" should have the visibility condition "user_role"
   * @endcode
   *
   * @Then the block with label :label should have the visibility condition :condition
   *
   * @throws \Exception
   *   When the block does not have the specified visibility condition.
   */
  public function blockAssertBlockHasCondition(string $label, string $condition): void {
    $block = $this->blockLoadBlockByLabel($label);
    $conditions = $block->getVisibilityConditions();

    if (!$conditions->has($condition)) {
      throw new \Exception(sprintf('Block "%s" does not have condition "%s"', $label, $condition));
    }
  }

  /**
   * Verifies that a block does not have a specific visibility condition configured.
   *
   * This checks that a block does not have any visibility conditions of the
   * specified type, meaning it is not restricted by that condition type.
   *
   * @param string $label
   *   Label identifying the block.
   * @param string $condition
   *   The type of visibility condition to check for (e.g., 'request_path', 'user_role').
   *
   * @code
   * Then the block with label "[TEST] Public Block" should not have the visibility condition "user_role"
   * @endcode
   *
   * @Then the block with label :label should not have the visibility condition :condition
   *
   * @throws \Exception
   *   When the block has the specified visibility condition when it should not.
   */
  public function blockAssertBlockShouldNotHaveCondition(string $label, string $condition): void {
    $block = $this->blockLoadBlockByLabel($label);
    $conditions = $block->getVisibilityConditions();

    if ($conditions->has($condition)) {
      throw new \Exception(sprintf('Block "%s" should not have condition "%s"', $label, $condition));
    }
  }

  /**
   * Verifies that a block with the specified label is disabled (inactive).
   *
   * This assertion checks that a block exists but is not enabled for display
   * on the site. This is equivalent to verifying that the "Enabled" checkbox
   * in the block UI is unchecked.
   *
   * @param string $label
   *   Label to identify the block.
   *
   * @code
   * Then the block with label "[TEST] Maintenance Block" is disabled
   * @endcode
   *
   * @Then the block with label :label is disabled
   *
   * @throws \Exception
   *   When the block is enabled when it should be disabled.
   */
  public function blockAssertBlockIsDisabled(string $label): void {
    $block = $this->blockLoadBlockByLabel($label);
    if ($block->status()) {
      throw new \Exception(sprintf('Block "%s" is not disabled and should be.', $label));
    }
  }

  /**
   * Verifies that a block with the specified label is enabled (active).
   *
   * This assertion checks that a block exists and is enabled for display
   * on the site. This is equivalent to verifying that the "Enabled" checkbox
   * in the block UI is checked.
   *
   * @param string $label
   *   Label to identify the block.
   *
   * @code
   * Then the block with label "[TEST] Navigation Block" is enabled
   * @endcode
   *
   * @Then the block with label :label is enabled
   *
   * @throws \Exception
   *   When the block is disabled when it should be enabled.
   */
  public function blockAssertBlockIsNotDisabled(string $label): void {
    $block = $this->blockLoadBlockByLabel($label);
    if (!$block->status()) {
      throw new \Exception(sprintf('Block "%s" is disabled but should not be.', $label));
    }
  }

  /**
   * Loads a block by its visible label.
   *
   * Searches for a block in the default theme with the specified visible label.
   * This is the label that typically shows up to users on the front end of the site
   * (if label display is enabled).
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
