<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Drupal;

use Drupal\Core\Entity\EntityStorageException;
use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Drupal\block\Entity\Block;

/**
 * Manage Drupal blocks.
 *
 * - Create and configure blocks with custom visibility conditions.
 * - Place blocks in regions and verify their rendering in the page.
 * - Automatically clean up created blocks after scenario completion.
 *
 * Skip processing with tag: `@behat-steps-skip:blockAfterScenario`
 */
trait BlockTrait {

  /**
   * Array of created block instances.
   *
   * @var array<int, \Drupal\block\Entity\Block>
   */
  protected static array $blockInstances = [];

  /**
   * Clean up all blocks created during the scenario.
   *
   * This method automatically runs after each scenario to ensure clean
   * test state.
   * Add the tag @behat-steps-skip:blockAfterScenario to your scenario to
   * prevent automatic cleanup of blocks.
   *
   * @AfterScenario
   */
  public function blockAfterScenario(AfterScenarioScope $scope): void {
    if ($scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__)) {
      return;
    }

    foreach (static::$blockInstances as $key => $block) {
      try {
        $block->delete();
      }
      catch (EntityStorageException) {
        // Ignore “already deleted” errors to keep teardown resilient.
      }
      unset(static::$blockInstances[$key]);
    }
  }

  /**
   * Create a block instance.
   *
   * @code
   * Given the instance of "My block" block exists with the following configuration:
   *  | label         | My block |
   *  | label_display | 1        |
   *  | region        | content  |
   *  | status        | 1        |
   * @endcode
   *
   * @Given the instance of :admin_label block exists with the following configuration:
   */
  public function blockCreateInstance(string $admin_label, TableNode $fields): void {
    $block = NULL;

    /** @var \Drupal\Core\Block\BlockManagerInterface $block_manager */
    $block_manager = \Drupal::service('plugin.manager.block');
    $definitions = $block_manager->getDefinitions();
    foreach ($definitions as $plugin_id => $definition) {
      if ((string) $definition['admin_label'] === $admin_label) {
        $default_theme = \Drupal::config('system.theme')->get('default');
        $block = \Drupal::entityTypeManager()->getStorage('block')->create([
          'plugin' => $plugin_id,
          'theme' => $default_theme,
        ]);

        $suggestion = $block->getPlugin()->getMachineNameSuggestion();
        $block_id = \Drupal::service('block.repository')->getUniqueMachineName($suggestion, $block->getTheme());

        $block->set('id', $block_id);

        // Set temporary label to pass to the block configuration step.
        $settings = $block->get('settings');
        $settings['label'] = $admin_label;
        $block->set('settings', $settings);

        $block->save();

        break;
      }
    }

    if (!$block instanceof Block) {
      throw new \Exception(sprintf('Could not create block with admin label "%s"', $admin_label));
    }

    $this->blockConfigure($admin_label, $fields);

    static::$blockInstances[] = $block;
  }

  /**
   * Configure an existing block identified by label.
   *
   * @param string $label
   *   The label of the block.
   * @param \Behat\Gherkin\Node\TableNode $fields
   *   Configuration for the block.
   *
   * @code
   *   Given the block "My block" has the following configuration:
   *   | label_display | 1       |
   *   | region        | content |
   *   | status        | 1       |
   * @endcode
   *
   * @Given the block :label has the following configuration:
   */
  public function blockConfigure(string $label, TableNode $fields): void {
    $this->blockAssertExists($label);

    $block = $this->blockLoadByLabel($label);

    $settings = $block->get('settings');
    foreach ($fields->getRowsHash() as $field => $value) {
      switch ($field) {
        case 'label':
          $settings['label'] = $value;
          $block->set('settings', $settings);
          break;

        case 'label_display':
          $settings['label_display'] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
          $block->set('settings', $settings);
          break;

        case 'region':
          if (is_string($value)) {
            $block->setRegion($value);
          }
          else {
            throw new \InvalidArgumentException('Expected region as string.');
          }
          break;

        case 'status':
          $block->setStatus(filter_var($value, FILTER_VALIDATE_BOOLEAN));
          break;
      }
    }

    $block->save();
  }

  /**
   * Remove a block specified by label.
   *
   * @param string $label
   *   The label of the block.
   *
   * @code
   *   Given the block "My block" does not exist
   * @endcode
   *
   * @Given the block :label does not exist
   */
  public function blockRemove(string $label): void {
    while ($block = $this->blockLoadByLabel($label)) {
      $block->delete();
    }

    static::$blockInstances = array_filter(
      static::$blockInstances,
      fn(Block $b): bool => $b->get('settings')['label'] !== $label
    );
  }

  /**
   * Enable a block specified by label.
   *
   * @param string $label
   *   The label of the block.
   *
   * @code
   *   Given the block "My block" is enabled
   * @endcode
   *
   * @Given the block :label is enabled
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   When the block cannot be saved.
   */
  public function blockEnable(string $label): void {
    $this->blockAssertExists($label);
    $block = $this->blockLoadByLabel($label);

    $block->enable();

    $block->save();
  }

  /**
   * Disable a block specified by label.
   *
   * @param string $label
   *   The label of the block.
   *
   * @code
   *   Given the block "My block" is disabled
   * @endcode
   *
   * @Given the block :label is disabled
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   When the block cannot be saved.
   */
  public function blockDisable(string $label): void {
    $this->blockAssertExists($label);
    $block = $this->blockLoadByLabel($label);

    $block->disable();

    $block->save();
  }

  /**
   * Set a visibility condition for a block.
   *
   * @param string $label
   *   Label identifying the block.
   * @param string $condition
   *   The type of visibility condition.
   * @param \Behat\Gherkin\Node\TableNode $fields
   *   Configuration for the visibility condition.
   *
   * @code
   *   Given the block "My block" has the following "request_path" condition configuration:
   *   | pages  | /node/1\r\n/about |
   *   | negate | 0                 |
   * @endcode
   *
   * @Given the block :label has the following :condition condition configuration:
   */
  public function blockConfigureVisibilityCondition(string $label, string $condition, TableNode $fields): void {
    $this->blockAssertExists($label);
    $block = $this->blockLoadByLabel($label);

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
   * @param string $label
   *   Label identifying the block.
   * @param string $condition
   *   The type of visibility condition to remove.
   *
   * @code
   *   Given the block "My block" has the "request_path" condition removed
   * @endcode
   *
   * @Given the block :label has the :condition condition removed
   */
  public function blockRemoveVisibilityCondition(string $label, string $condition): void {
    $this->blockConfigureVisibilityCondition($label, $condition, new TableNode([]));
  }

  /**
   * Assert that a block with the specified label exists.
   *
   * @param string $label
   *   The label of the block.
   *
   * @code
   *   Then the block "My block" should exist
   * @endcode
   *
   * @Then the block :label should exist
   *
   * @throws \Exception
   *   When no block with the specified label is found.
   */
  public function blockAssertExists(string $label): void {
    $block = $this->blockLoadByLabel($label);

    if (empty($block)) {
      throw new \Exception(sprintf('The block "%s" does not exist.', $label));
    }
  }

  /**
   * Assert that a block with the specified label does not exist.
   *
   * @param string $label
   *   The label of the block.
   *
   * @code
   *   Then the block "My block" should not exist
   * @endcode
   *
   * @Then the block :label should not exist
   *
   * @throws \Exception
   *   When block with the specified label is found.
   */
  public function blockAssertNotExists(string $label): void {
    $block = $this->blockLoadByLabel($label);

    if (!empty($block)) {
      throw new \Exception(sprintf('The block "%s" exists but should not.', $label));
    }
  }

  /**
   * Assert that a block with the specified label exists in a region.
   *
   * @param string $label
   *   The label of the block.
   * @param string $region
   *   The region to check for the block
   *
   * @code
   *   Then the block "My block" should exist in the "content" region
   * @endcode
   *
   * @Then the block :label should exist in the :region region
   *
   * @throws \Exception
   *   When no block with the specified label is found in the given region.
   */
  public function blockAssertExistsInRegion(string $label, string $region): void {
    $this->blockAssertExists($label);
    $block = $this->blockLoadByLabel($label);

    $actual_region = $block->getRegion();

    if ($actual_region !== $region) {
      throw new \Exception(sprintf('Block "%s" is in region "%s" but should be in "%s"', $label, $actual_region, $region));
    }
  }

  /**
   * Assert that a block with the specified label does not exist in a region.
   *
   * @param string $label
   *   The label of the block.
   * @param string $region
   *   The region to check for the block
   *
   * @code
   *   Then the block "My block" should not exist in the "content" region
   * @endcode
   *
   * @Then the block :label should not exist in the :region region
   *
   * @throws \Exception
   *   When block with the specified label is found in the given region.
   */
  public function blockAssertNotExistsInRegion(string $label, string $region): void {
    $this->blockAssertExists($label);
    $block = $this->blockLoadByLabel($label);

    $actual_region = $block->getRegion();

    if ($actual_region === $region) {
      throw new \Exception(sprintf('Block "%s" is in region "%s" but should not be', $label, $region));
    }
  }

  /**
   * Load a block by its label.
   *
   * @param string $label
   *   The visible label of the block to find.
   *
   * @return \Drupal\block\Entity\Block|null
   *   The loaded block entity or NULL if not found.
   *
   * @throws \Exception
   *   When no block with the specified label is found.
   */
  private function blockLoadByLabel(string $label): ?Block {
    $default_theme = \Drupal::config('system.theme')->get('default');

    $blocks = \Drupal::entityTypeManager()
      ->getStorage('block')->loadByProperties([
        'theme' => $default_theme,
        'settings.label' => $label,
      ]);

    krsort($blocks);

    return empty($blocks) ? NULL : end($blocks);
  }

}
