<?php

declare(strict_types=1);

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Drupal\block\Entity\Block;

/**
 * Provides Behat step definitions for managing blocks programmatically.
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
   * @code
   * @When I create a block of type :label with:
   * | label         | [TEST] Welcome Message      |
   * | display_label | 1                           |
   * | region        | <region>                    |
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
   * Clean all created blocks after scenario run.
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
   * Find a block in default theme with a specified label and configure.
   *
   * @code
   * @When I configure the block with the label :label with:
   *  | label         | [TEST] Welcome Message      |
   *  | display_label | 1                           |
   *  | region        | <region>                    |
   *  | status        | 1                           |
   * @endcode
   */
  public function blockConfigureBlock(string $description, TableNode $fields): void {
    $block = $this->blockLoadBlockByLabel($description);

    $this->blockConfigureBlockInstance($block, $fields);

    $block->save();
  }

  /**
   * Configure block instance.
   *
   * @param \Drupal\block\Entity\Block $block
   *   Block to be configured.
   * @param \Behat\Gherkin\Node\TableNode $fields
   *   Provide fields to configure.
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
   * @param string $label
   *   Label identifying the block.
   * @param string $condition
   *   The type of visibility condition.
   * @param \Behat\Gherkin\Node\TableNode $fields
   *   Field data table.
   *
   * @When I configure the visibility condition :condition for the block with label :label
   */
  public function blockConfigureBlockVisibility(string $label, string $condition, TableNode $fields): void {
    $block = $this->blockLoadBlockByLabel($label);
    $configuration = $fields->getRowsHash();
    $configuration['id'] = $condition;
    $block->setVisibilityConfig($condition, $configuration);
    $block->save();
  }

  /**
   * Removes a visibility condition from the block.
   *
   * @param string $label
   *   Label identifying the block.
   * @param string $condition
   *   The type of visibility condition.
   *
   * @When I remove the visibility condition :condition from the block with label :label
   */
  public function blockRemoveBlockVisibility(string $label, string $condition): void {
    $this->blockConfigureBlockVisibility($label, $condition, new TableNode([]));
  }

  /**
   * Disables a block specified with a label.
   *
   * @param string $label
   *   Label used to identify a block.
   *
   * @When I disable the block with label :label
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function blockDisableBlock(string $label): void {
    $block = $this->blockLoadBlockByLabel($label);
    $block->disable();
    $block->save();
  }

  /**
   * Enables a block specified with a label.
   *
   * @param string $label
   *   Label used to identify a block.
   *
   * @When I enable the block with label :label
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function blockEnableBlock(string $label): void {
    $block = $this->blockLoadBlockByLabel($label);
    $block->enable();
    $block->save();
  }

  /**
   * Asserts that a block exists.
   *
   * @param string $label
   *   Label used to identify a block.
   *
   * @Then block with label :label should exist
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
   * Asserts that a block exists in a specified region.
   *
   * @param string $label
   *   Label used to identify a block.
   * @param string $region
   *   Region to check for the block in.
   *
   * @When block with label :label should exist in the region :region
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
   * Asserts that a block does not exist in a specified region.
   *
   * @param string $label
   *   Label used to identify a block.
   * @param string $region
   *   Region to check for the block in.
   *
   * @When block with label :label should not exist in the region :region
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
   * Asserts that the block has a visibility condition.
   *
   * @param string $label
   *   Label identifying the block.
   * @param string $condition
   *   The type of visibility condition.
   *
   * @Then the block with label :label should have the visibility condition :condition
   */
  public function blockAssertBlockHasCondition(string $label, string $condition): void {
    $block = $this->blockLoadBlockByLabel($label);
    $conditions = $block->getVisibilityConditions();

    if (!$conditions->has($condition)) {
      throw new \Exception(sprintf('Block "%s" does not have condition "%s"', $label, $condition));
    }
  }

  /**
   * Assert that the block should not have a visibility condition.
   *
   * @param string $label
   *   Label identifying the block.
   * @param string $condition
   *   The type of visibility condition.
   *
   * @Then the block with label :label should not have the visibility condition :condition
   */
  public function blockAssertBlockShouldNotHaveCondition(string $label, string $condition): void {
    $block = $this->blockLoadBlockByLabel($label);
    $conditions = $block->getVisibilityConditions();

    if ($conditions->has($condition)) {
      throw new \Exception(sprintf('Block "%s" should not have condition "%s"', $label, $condition));
    }
  }

  /**
   * Assert that the block with specified label is disabled.
   *
   * @param string $label
   *   Label to identify block with.
   *
   * @Then the block with label :label is disabled
   */
  public function blockAssertBlockIsDisabled(string $label): void {
    $block = $this->blockLoadBlockByLabel($label);
    if ($block->status()) {
      throw new \Exception(sprintf('Block "%s" is not disabled and should be.', $label));
    }
  }

  /**
   * Assert that the block with specified label is enabled.
   *
   * @param string $label
   *   Label to identify block with.
   *
   * @Then the block with label :label is enabled
   */
  public function blockAssertBlockIsNotDisabled(string $label): void {
    $block = $this->blockLoadBlockByLabel($label);
    if (!$block->status()) {
      throw new \Exception(sprintf('Block "%s" is disabled but should not be.', $label));
    }
  }

  /**
   * Loads a block by its description/admin label.
   *
   * @param string $label
   *   Label to find the block by.
   *
   * @return \Drupal\block\Entity\Block
   *   Loaded block.
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
