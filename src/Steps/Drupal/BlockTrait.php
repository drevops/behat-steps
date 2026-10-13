<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Exception\ExpectationException;
use Behat\Step\Given;
use Behat\Step\Then;
use DrevOps\BehatSteps\Backend\Drupal\Capability\BlockCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\ConfigCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\ConfigEntityCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\CoreCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\ModuleCapabilityInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;
use DrevOps\BehatSteps\Extension\Prerequisite\Prerequisite;
use DrevOps\BehatSteps\Steps\Helper\Drupal\EntityLifecycleTrait;
use DrevOps\BehatSteps\Steps\Helper\Drupal\QueryTrait;

/**
 * Manage Drupal blocks.
 *
 * - Create and configure blocks with custom visibility conditions.
 * - Place blocks in regions and assert their configured region.
 * - Created blocks are automatically removed at the end of the scenario.
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\WebRawContext
 */
trait BlockTrait {

  use EntityLifecycleTrait;
  use QueryTrait;

  /**
   * Create a block instance.
   *
   * @code
   * Given the instance of the block "My block" with the following configuration exists:
   *   | label         | My block |
   *   | label_display | 1        |
   *   | region        | content  |
   *   | status        | 1        |
   * @endcode
   *
   * @actor:system
   */
  #[Given('the instance of the block :admin_label with the following configuration exists:')]
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
   *
   * @actor:system
   */
  #[Given('the block :label has the following configuration:')]
  public function blockConfigure(string $label, TableNode $fields): void {
    $this->blockApplyConfiguration($this->blockGetByLabel($label), $fields->getRowsHash());
  }

  /**
   * Remove every block in the default theme with the label.
   *
   * @param string $label
   *   The label of the block.
   *
   * @code
   *   Given the block "My block" does not exist
   * @endcode
   *
   * @actor:system
   */
  #[Given('the block :label does not exist')]
  public function blockDelete(string $label): void {
    $backend = $this->backendFor(BlockCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    while ($block = $this->blockFindByLabel($label)) {
      $backend->deleteBlock($block);
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
   * @actor:system
   */
  #[Given('the block :label is enabled')]
  public function blockEnable(string $label): void {
    $this->blockApplyConfiguration($this->blockGetByLabel($label), ['status' => TRUE]);
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
   * @actor:system
   */
  #[Given('the block :label is disabled')]
  public function blockDisable(string $label): void {
    $this->blockApplyConfiguration($this->blockGetByLabel($label), ['status' => FALSE]);
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
   *
   * @actor:system
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
   *
   * @actor:system
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
   * @actor:system
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
   * @actor:system
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
   * @actor:system
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

    $actual_region = $this->blockGetRegion($block);

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
   * @actor:system
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

    $actual_region = $this->blockGetRegion($block);

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
   * @return \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface
   *   The stub of the block, labelled with the admin label and carrying its
   *   'id'.
   *
   * @throws \RuntimeException
   *   When no block plugin has the admin label.
   */
  public function blockCreate(string $admin_label): EntityStubInterface {
    $backend = $this->backendFor(BlockCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    $plugin_id = $backend->findBlockPluginId($admin_label);

    if ($plugin_id === NULL) {
      throw new \RuntimeException(sprintf('Could not create block with admin label "%s".', $admin_label));
    }

    // The label lookups match the label setting, so the block takes the admin
    // label until a configuration replaces it.
    $stub = new EntityStub('block', NULL, [
      'plugin' => $plugin_id,
      'theme' => $this->backendFor(ConfigCapabilityInterface::class)->configGet('system.theme', 'default'),
      'settings' => ['label' => $admin_label],
    ]);

    $created = $backend->placeBlock($stub);

    $this->entityLifecycleRegister($created, 'id');

    return $created;
  }

  /**
   * Apply a configuration to a block and save it.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $block
   *   The block stub, carrying its 'id'.
   * @param array<string, mixed> $configuration
   *   The "label", "label_display", "region" and "status" values to set. Any
   *   other key is ignored.
   *
   * @throws \RuntimeException
   *   When the region is not a string.
   */
  public function blockApplyConfiguration(EntityStubInterface $block, array $configuration): void {
    $values = ['id' => $this->queryGetEntityId($block, 'id')];

    if (array_key_exists('label', $configuration) || array_key_exists('label_display', $configuration)) {
      $settings = $this->blockGetStoredConfig($block, 'settings');

      if (array_key_exists('label', $configuration)) {
        $settings['label'] = $configuration['label'];
      }

      if (array_key_exists('label_display', $configuration)) {
        $settings['label_display'] = filter_var($configuration['label_display'], FILTER_VALIDATE_BOOLEAN);
      }

      $values['settings'] = $settings;
    }

    if (array_key_exists('region', $configuration)) {
      if (!is_string($configuration['region'])) {
        throw new \RuntimeException('Expected region as string.');
      }

      $values['region'] = $configuration['region'];
    }

    if (array_key_exists('status', $configuration)) {
      $values['status'] = filter_var($configuration['status'], FILTER_VALIDATE_BOOLEAN);
    }

    $this->backendFor(ConfigEntityCapabilityInterface::class)->updateConfigEntity(new EntityStub('block', NULL, $values));
  }

  /**
   * Set a visibility condition on a block and save it.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $block
   *   The block stub, carrying its 'id'.
   * @param string $condition
   *   The condition plugin ID.
   * @param array<string, mixed> $configuration
   *   The condition configuration. An empty configuration leaves the
   *   condition with its defaults.
   */
  public function blockSetVisibilityCondition(EntityStubInterface $block, string $condition, array $configuration): void {
    $configuration['id'] = $condition;

    $visibility = $this->blockGetStoredConfig($block, 'visibility');
    $visibility[$condition] = $configuration;

    $values = ['id' => $this->queryGetEntityId($block, 'id'), 'visibility' => $visibility];

    $this->backendFor(ConfigEntityCapabilityInterface::class)->updateConfigEntity(new EntityStub('block', NULL, $values));
  }

  /**
   * Remove a visibility condition from a block and save it.
   *
   * Nothing is removed from a block that does not carry the condition.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $block
   *   The block stub, carrying its 'id'.
   * @param string $condition
   *   The condition plugin ID.
   *
   * @throws \RuntimeException
   *   When no condition plugin has the ID.
   */
  public function blockUnsetVisibilityCondition(EntityStubInterface $block, string $condition): void {
    if (!$this->backendFor(BlockCapabilityInterface::class)->hasConditionPlugin($condition)) {
      throw new \RuntimeException(sprintf('The condition "%s" does not exist.', $condition));
    }

    $visibility = $this->blockGetStoredConfig($block, 'visibility');

    if (!array_key_exists($condition, $visibility)) {
      return;
    }

    unset($visibility[$condition]);

    $values = ['id' => $this->queryGetEntityId($block, 'id'), 'visibility' => $visibility];

    $this->backendFor(ConfigEntityCapabilityInterface::class)->updateConfigEntity(new EntityStub('block', NULL, $values));
  }

  /**
   * Get the region a block is placed in.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $block
   *   The block stub, carrying its 'id'.
   *
   * @return string
   *   The region, or an empty string for a block placed in none.
   */
  protected function blockGetRegion(EntityStubInterface $block): string {
    $region = $this->backendFor(ConfigCapabilityInterface::class)->configGet('block.block.' . $this->queryGetEntityId($block, 'id'), 'region');

    return is_string($region) ? $region : '';
  }

  /**
   * Get an array a block stores under a key of its configuration.
   *
   * The stored value is read rather than the overridden one, so saving the
   * block writes no override into its configuration.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $block
   *   The block stub, carrying its 'id'.
   * @param string $key
   *   The configuration key, such as 'settings' or 'visibility'.
   *
   * @return array<string, mixed>
   *   The stored array, or an empty array when the key holds none.
   */
  protected function blockGetStoredConfig(EntityStubInterface $block, string $key): array {
    $value = $this->backendFor(ConfigCapabilityInterface::class)->configGetOriginal('block.block.' . $this->queryGetEntityId($block, 'id'), $key);

    return is_array($value) ? $value : [];
  }

  /**
   * Return the block carrying a label.
   *
   * @param string $label
   *   The visible label of the block to find.
   *
   * @return \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface
   *   The stub of the block, carrying its 'id'.
   *
   * @throws \RuntimeException
   *   When no block carries that label.
   */
  public function blockGetByLabel(string $label): EntityStubInterface {
    $block = $this->blockFindByLabel($label);

    if (!$block instanceof EntityStubInterface) {
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
   * @return \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface|null
   *   The stub of the block, carrying its 'id', or NULL when no block carries
   *   that label.
   */
  public function blockFindByLabel(string $label): ?EntityStubInterface {
    $config = $this->backendFor(ConfigCapabilityInterface::class);

    $this->assertPrerequisites(__TRAIT__);

    $conditions = ['theme' => $config->configGet('system.theme', 'default'), 'settings.label' => $label];

    return $this->entityLifecycleFindNewest($this->queryEntityStubs('block', 'id', $conditions), 'id');
  }

  /**
   * Declares the prerequisites this trait asserts.
   *
   * @return array<int, \DrevOps\BehatSteps\Extension\Prerequisite\Prerequisite>
   *   The prerequisites this trait declares.
   */
  protected function blockPrerequisites(): array {
    return [
      Prerequisite::capability(CoreCapabilityInterface::class),
      Prerequisite::check(static fn(ModuleCapabilityInterface $backend): bool => $backend->moduleIsEnabled('block'), 'the core "block" module is enabled'),
    ];
  }

}
