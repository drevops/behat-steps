<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Exception\ExpectationException;
use Behat\Step\Given;
use Behat\Step\Then;
use DrevOps\BehatSteps\Backend\Capability\CoreCapabilityInterface;
use DrevOps\BehatSteps\Helper\Drupal\EntityLifecycleTrait;
use Drupal\block\Entity\Block;

/**
 * Manage Drupal blocks.
 *
 * - Create and configure blocks with custom visibility conditions.
 * - Place blocks in regions and assert their configured region.
 * - Created blocks are automatically removed at the end of the scenario.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
 */
trait BlockTrait {

  use EntityLifecycleTrait;

  /**
   * Create a block instance.
   *
   * @code
   * Given the instance of the block "My block" exists with the following configuration:
   *   | label         | My block |
   *   | label_display | 1        |
   *   | region        | content  |
   *   | status        | 1        |
   * @endcode
   */
  #[Given('the instance of the block :admin_label exists with the following configuration:')]
  public function blockCreateInstance(string $admin_label, TableNode $fields): void {
    $block = $this->blockCreate($admin_label);

    $this->blockApplyConfiguration($block, $fields->getRowsHash());
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
   *     | label_display | 1       |
   *     | region        | content |
   *     | status        | 1       |
   * @endcode
   */
  #[Given('the block :label has the following configuration:')]
  public function blockConfigure(string $label, TableNode $fields): void {
    $this->blockApplyConfiguration($this->blockGetByLabel($label), $fields->getRowsHash());
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
   */
  #[Given('the block :label does not exist')]
  public function blockDelete(string $label): void {
    while ($block = $this->blockFindByLabel($label)) {
      $block->delete();
    }
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
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   When the block cannot be saved.
   */
  #[Given('the block :label is enabled')]
  public function blockEnable(string $label): void {
    $block = $this->blockGetByLabel($label);

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
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   When the block cannot be saved.
   */
  #[Given('the block :label is disabled')]
  public function blockDisable(string $label): void {
    $block = $this->blockGetByLabel($label);

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
   *   Given the block "My block" has the condition "request_path" with the following configuration:
   *     | pages  | /node/1\r\n/about |
   *     | negate | 0                 |
   * @endcode
   */
  #[Given('the block :label has the condition :condition with the following configuration:')]
  public function blockConfigureVisibilityCondition(string $label, string $condition, TableNode $fields): void {
    $this->blockSetVisibilityCondition($this->blockGetByLabel($label), $condition, $fields->getRowsHash());
  }

  /**
   * Remove a visibility condition from the specified block.
   *
   * A block that does not carry the condition is left unchanged.
   *
   * @param string $label
   *   Label identifying the block.
   * @param string $condition
   *   The type of visibility condition to remove.
   *
   * @code
   *   Given the block "My block" has the condition "request_path" removed
   * @endcode
   */
  #[Given('the block :label has the condition :condition removed')]
  public function blockRemoveVisibilityCondition(string $label, string $condition): void {
    $this->blockUnsetVisibilityCondition($this->blockGetByLabel($label), $condition);
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
   * @throws \Behat\Mink\Exception\ExpectationException
   *   When no block with the specified label is found.
   */
  #[Then('the block :label should exist')]
  public function blockAssertExists(string $label): void {
    $block = $this->blockFindByLabel($label);

    if (empty($block)) {
      throw new ExpectationException(sprintf('The block "%s" does not exist.', $label), $this->getSession()->getDriver());
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
   * @throws \Behat\Mink\Exception\ExpectationException
   *   When block with the specified label is found.
   */
  #[Then('the block :label should not exist')]
  public function blockAssertNotExists(string $label): void {
    $block = $this->blockFindByLabel($label);

    if (!empty($block)) {
      throw new ExpectationException(sprintf('The block "%s" exists, but it should not.', $label), $this->getSession()->getDriver());
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
   *   Then the block "My block" in the region "content" should exist
   * @endcode
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   When no block with the specified label is found in the given region.
   */
  #[Then('the block :label in the region :region should exist')]
  public function blockAssertExistsInRegion(string $label, string $region): void {
    $block = $this->blockFindByLabel($label);

    if ($block === NULL) {
      throw new ExpectationException(sprintf('The block "%s" does not exist.', $label), $this->getSession()->getDriver());
    }

    $actual_region = $block->getRegion();

    if ($actual_region !== $region) {
      throw new ExpectationException(sprintf('The block "%s" is in the region "%s", but it should be in the region "%s".', $label, $actual_region, $region), $this->getSession()->getDriver());
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
   *   Then the block "My block" in the region "content" should not exist
   * @endcode
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   When block with the specified label is found in the given region.
   */
  #[Then('the block :label in the region :region should not exist')]
  public function blockAssertNotExistsInRegion(string $label, string $region): void {
    $block = $this->blockFindByLabel($label);

    if ($block === NULL) {
      throw new ExpectationException(sprintf('The block "%s" does not exist.', $label), $this->getSession()->getDriver());
    }

    $actual_region = $block->getRegion();

    if ($actual_region === $region) {
      throw new ExpectationException(sprintf('The block "%s" is in the region "%s", but it should not be.', $label, $region), $this->getSession()->getDriver());
    }
  }

  /**
   * Create a block in the default theme from the plugin with an admin label.
   *
   * The block is removed after the scenario.
   *
   * @param string $admin_label
   *   The admin label of the block plugin.
   *
   * @return \Drupal\block\Entity\Block
   *   The block, labelled with the admin label.
   *
   * @throws \RuntimeException
   *   When no block plugin has the admin label.
   */
  public function blockCreate(string $admin_label): Block {
    $this->backendFor(CoreCapabilityInterface::class);

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

        // The label lookups match the label setting, so the block takes the
        // admin label until a configuration replaces it.
        $settings = $block->get('settings');
        $settings['label'] = $admin_label;
        $block->set('settings', $settings);

        $block->save();

        break;
      }
    }

    if (!$block instanceof Block) {
      throw new \RuntimeException(sprintf('Could not create block with admin label "%s".', $admin_label));
    }

    $this->entityLifecycleRegister($block);

    return $block;
  }

  /**
   * Apply a configuration to a block and save it.
   *
   * @param \Drupal\block\Entity\Block $block
   *   The block.
   * @param array<string, mixed> $configuration
   *   The "label", "label_display", "region" and "status" values to set. Any
   *   other key is ignored.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   When the block cannot be saved.
   */
  public function blockApplyConfiguration(Block $block, array $configuration): void {
    $settings = $block->get('settings');

    foreach ($configuration as $field => $value) {
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
          // @codeCoverageIgnoreStart
          else {
            throw new \RuntimeException('Expected region as string.');
          }

          // @codeCoverageIgnoreEnd
          break;

        case 'status':
          $block->setStatus(filter_var($value, FILTER_VALIDATE_BOOLEAN));
          break;
      }
    }

    $block->save();
  }

  /**
   * Set a visibility condition on a block and save it.
   *
   * @param \Drupal\block\Entity\Block $block
   *   The block.
   * @param string $condition
   *   The condition plugin ID.
   * @param array<string, mixed> $configuration
   *   The condition configuration. An empty configuration leaves the
   *   condition with its defaults.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   When the block cannot be saved.
   */
  public function blockSetVisibilityCondition(Block $block, string $condition, array $configuration): void {
    $configuration['id'] = $condition;
    $block->setVisibilityConfig($condition, $configuration);

    $block->save();
  }

  /**
   * Remove a visibility condition from a block and save it.
   *
   * Nothing is removed from a block that does not carry the condition.
   *
   * @param \Drupal\block\Entity\Block $block
   *   The block.
   * @param string $condition
   *   The condition plugin ID.
   *
   * @throws \RuntimeException
   *   When no condition plugin has the ID.
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   When the block cannot be saved.
   */
  public function blockUnsetVisibilityCondition(Block $block, string $condition): void {
    if (!\Drupal::service('plugin.manager.condition')->hasDefinition($condition)) {
      throw new \RuntimeException(sprintf('The condition "%s" does not exist.', $condition));
    }

    $block->getVisibilityConditions()->removeInstanceId($condition);

    $block->save();
  }

  /**
   * Return the block carrying a label.
   *
   * @param string $label
   *   The visible label of the block to find.
   *
   * @return \Drupal\block\Entity\Block
   *   The loaded block entity.
   *
   * @throws \RuntimeException
   *   When no block carries that label.
   */
  public function blockGetByLabel(string $label): Block {
    $block = $this->blockFindByLabel($label);

    if (!$block instanceof Block) {
      throw new \RuntimeException(sprintf('The block "%s" does not exist.', $label));
    }

    return $block;
  }

  /**
   * Find a block by its label.
   *
   * When several blocks carry the label, the block the scenario placed last
   * is returned, and otherwise the one with the last machine name in natural
   * order.
   *
   * @param string $label
   *   The visible label of the block to find.
   *
   * @return \Drupal\block\Entity\Block|null
   *   The loaded block entity, or NULL when no block carries that label.
   */
  public function blockFindByLabel(string $label): ?Block {
    $this->backendFor(CoreCapabilityInterface::class);

    $default_theme = \Drupal::config('system.theme')->get('default');

    $blocks = \Drupal::entityTypeManager()
      ->getStorage('block')->loadByProperties([
        'theme' => $default_theme,
        'settings.label' => $label,
      ]);

    return $this->entityLifecycleFindNewest($blocks);
  }

}
