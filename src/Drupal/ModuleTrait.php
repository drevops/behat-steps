<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Drupal;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;

/**
 * Enable and disable Drupal modules with automatic state restoration.
 *
 * Supports automatic module management via scenario tags.
 *
 * Skip processing with tags: `@behat-steps-skip:moduleBeforeScenario` and
 * `@behat-steps-skip:moduleAfterScenario`.
 *
 * Special tags:
 * - `@module:module_name` - enable module for scenario
 * - `@module:!module_name` - disable module for scenario
 */
trait ModuleTrait {

  /**
   * Stores original module states for restoration.
   *
   * @var array<string, bool>
   */
  protected array $moduleOriginalStates = [];

  /**
   * Enable/disable modules before scenario based on tags.
   *
   * @BeforeScenario
   */
  public function moduleBeforeScenario(BeforeScenarioScope $scope): void {
    if ($scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__)) {
      return;
    }

    $tags = $scope->getScenario()->getTags();
    foreach ($tags as $tag) {
      if (str_starts_with($tag, 'module:')) {
        $module_spec = substr($tag, 7);
        $should_disable = str_starts_with($module_spec, '!');
        $module_name = $should_disable ? substr($module_spec, 1) : $module_spec;

        // Store original state.
        $this->moduleStoreOriginalState($module_name);

        // Enable or disable module as specified.
        if ($should_disable) {
          if ($this->moduleIsEnabled($module_name)) {
            $this->moduleDisable($module_name);
          }
        }
        elseif (!$this->moduleIsEnabled($module_name)) {
          $this->moduleEnable($module_name);
        }
      }
    }
  }

  /**
   * Restore module states after scenario.
   *
   * @AfterScenario
   */
  public function moduleAfterScenario(AfterScenarioScope $scope): void {
    if ($scope->getScenario()->hasTag('behat-steps-skip:' . __FUNCTION__)) {
      return;
    }

    foreach ($this->moduleOriginalStates as $module_name => $original_state) {
      $current_state = $this->moduleIsEnabled($module_name);

      if ($original_state !== $current_state) {
        if ($original_state) {
          $this->moduleEnable($module_name);
        }
        else {
          $this->moduleDisable($module_name);
        }
      }
    }

    // Clear state tracking.
    $this->moduleOriginalStates = [];
  }

  /**
   * Enable a module.
   *
   * @code
   * Given the "ctools" module is enabled
   * @endcode
   *
   * @Given the :module module is enabled
   */
  public function moduleEnsureEnabled(string $module): void {
    $this->moduleStoreOriginalState($module);
    if (!$this->moduleIsEnabled($module)) {
      $this->moduleEnable($module);
    }
  }

  /**
   * Disable a module.
   *
   * @code
   * Given the "shield" module is disabled
   * @endcode
   *
   * @Given the :module module is disabled
   */
  public function moduleEnsureDisabled(string $module): void {
    $this->moduleStoreOriginalState($module);
    if ($this->moduleIsEnabled($module)) {
      $this->moduleDisable($module);
    }
  }

  /**
   * Enable multiple modules.
   *
   * @code
   * Given the following modules are enabled:
   *   | ctools |
   *   | views  |
   * @endcode
   *
   * @Given the following modules are enabled:
   */
  public function moduleEnsureEnabledMultiple(TableNode $modules_table): void {
    foreach ($modules_table->getColumn(0) as $module) {
      $this->moduleStoreOriginalState($module);
      if (!$this->moduleIsEnabled($module)) {
        $this->moduleEnable($module);
      }
    }
  }

  /**
   * Disable multiple modules.
   *
   * @code
   * Given the following modules are disabled:
   *   | shield           |
   *   | stage_file_proxy |
   * @endcode
   *
   * @Given the following modules are disabled:
   */
  public function moduleEnsureDisabledMultiple(TableNode $modules_table): void {
    foreach ($modules_table->getColumn(0) as $module) {
      $this->moduleStoreOriginalState($module);
      if ($this->moduleIsEnabled($module)) {
        $this->moduleDisable($module);
      }
    }
  }

  /**
   * Assert that a module is enabled.
   *
   * @code
   * Then the "ctools" module should be enabled
   * @endcode
   *
   * @Then the :module module should be enabled
   */
  public function moduleAssertEnabled(string $module): void {
    if (!$this->moduleIsEnabled($module)) {
      throw new \Exception(sprintf('The module "%s" is not enabled, but it should be.', $module));
    }
  }

  /**
   * Assert that a module is disabled.
   *
   * @code
   * Then the "shield" module should be disabled
   * @endcode
   *
   * @Then the :module module should be disabled
   */
  public function moduleAssertDisabled(string $module): void {
    if ($this->moduleIsEnabled($module)) {
      throw new \Exception(sprintf('The module "%s" is enabled, but it should not be.', $module));
    }
  }

  /**
   * Assert that multiple modules are enabled.
   *
   * @code
   * Then the following modules should be enabled:
   *   | ctools |
   *   | views  |
   * @endcode
   *
   * @Then the following modules should be enabled:
   */
  public function moduleAssertEnabledMultiple(TableNode $modules_table): void {
    foreach ($modules_table->getColumn(0) as $module) {
      if (!$this->moduleIsEnabled($module)) {
        throw new \Exception(sprintf('The module "%s" is not enabled, but it should be.', $module));
      }
    }
  }

  /**
   * Assert that multiple modules are disabled.
   *
   * @code
   * Then the following modules should be disabled:
   *   | shield           |
   *   | stage_file_proxy |
   * @endcode
   *
   * @Then the following modules should be disabled:
   */
  public function moduleAssertDisabledMultiple(TableNode $modules_table): void {
    foreach ($modules_table->getColumn(0) as $module) {
      if ($this->moduleIsEnabled($module)) {
        throw new \Exception(sprintf('The module "%s" is enabled, but it should not be.', $module));
      }
    }
  }

  /**
   * Check if a module is enabled.
   *
   * @param string $module
   *   The module machine name.
   *
   * @return bool
   *   TRUE if the module is enabled, FALSE otherwise.
   */
  protected function moduleIsEnabled(string $module): bool {
    return \Drupal::moduleHandler()->moduleExists($module);
  }

  /**
   * Enable a module.
   *
   * @param string $module
   *   The module machine name.
   */
  protected function moduleEnable(string $module): void {
    if ($this->moduleIsEnabled($module)) {
      return;
    }

    if (!$this->moduleIsPresent($module)) {
      throw new \RuntimeException(sprintf('Cannot enable module "%s": module is not installed.', $module));
    }

    try {
      \Drupal::service('module_installer')->install([$module]);
      drupal_flush_all_caches();
    }
    catch (\Exception $e) {
      throw new \RuntimeException(sprintf('Failed to enable module "%s": %s', $module, $e->getMessage()), $e->getCode(), $e);
    }
  }

  /**
   * Disable a module.
   *
   * @param string $module
   *   The module machine name.
   */
  protected function moduleDisable(string $module): void {
    if (!$this->moduleIsEnabled($module)) {
      return;
    }

    try {
      \Drupal::service('module_installer')->uninstall([$module]);
      drupal_flush_all_caches();
    }
    catch (\Exception $e) {
      throw new \RuntimeException(sprintf('Failed to disable module "%s": %s', $module, $e->getMessage()), $e->getCode(), $e);
    }
  }

  /**
   * Check if a module's code is present.
   *
   * @param string $module
   *   The module machine name.
   *
   * @return bool
   *   TRUE if the module's code is present, FALSE otherwise.
   */
  protected function moduleIsPresent(string $module): bool {
    $module_list = \Drupal::service('extension.list.module')->getList();
    return isset($module_list[$module]);
  }

  /**
   * Store original module state if not already stored.
   *
   * @param string $module
   *   The module name.
   */
  protected function moduleStoreOriginalState(string $module): void {
    if (!array_key_exists($module, $this->moduleOriginalStates)) {
      $this->moduleOriginalStates[$module] = $this->moduleIsEnabled($module);
    }
  }

}
