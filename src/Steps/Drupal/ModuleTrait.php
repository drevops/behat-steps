<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Steps\Drupal;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Behat\Hook\AfterScenario;
use Behat\Hook\BeforeScenario;
use Behat\Step\Given;
use Behat\Step\Then;
use DrevOps\BehatSteps\Backend\Capability\CacheCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\ModuleCapabilityInterface;
use DrevOps\BehatSteps\Behat\Config\Option;
use DrevOps\BehatSteps\Behat\Tag;
use DrevOps\BehatSteps\Exception\AssertionException;

/**
 * Enable and disable Drupal modules with automatic state restoration.
 *
 * Supports automatic module management via scenario and feature tags. A
 * scenario tag overrides a feature tag naming the same module.
 *
 * Skip processing with tag: `@behat-steps-skip:ModuleTrait`.
 *
 * Special tags:
 * - `@module:module_name` - enable module for scenario
 * - `@module:!module_name` - disable module for scenario
 *
 * @phpstan-require-extends \DrevOps\BehatSteps\Behat\Context\WebRawContext
 */
trait ModuleTrait {

  /**
   * The tag that enables the module it names, or disables it after a '!'.
   */
  protected const MODULE_TAG = 'module';

  /**
   * Stores original module states for restoration.
   *
   * @var array<string, bool>
   */
  protected array $moduleOriginalStates = [];

  /**
   * Enable/disable modules before scenario based on tags.
   */
  #[BeforeScenario]
  public function moduleBeforeScenario(BeforeScenarioScope $scope): void {
    if ($this->skipTag(__TRAIT__, $scope)) {
      return;
    }

    foreach (Tag::valueStates($scope, self::MODULE_TAG) as $module_name => $should_enable) {
      $this->moduleStoreOriginalState($module_name);

      if ($should_enable) {
        if (!$this->moduleIsEnabled($module_name)) {
          $this->moduleEnable($module_name);
        }
      }
      elseif ($this->moduleIsEnabled($module_name)) {
        $this->moduleDisable($module_name);
      }
    }
  }

  /**
   * Restore module states after scenario.
   */
  #[AfterScenario]
  public function moduleAfterScenario(AfterScenarioScope $scope): void {
    if ($this->skipTag(__TRAIT__, $scope)) {
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

    $this->moduleOriginalStates = [];
  }

  /**
   * Enable a module.
   *
   * @code
   * Given the module "ctools" is enabled
   * @endcode
   */
  #[Given('the module :module is enabled')]
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
   * Given the module "shield" is disabled
   * @endcode
   */
  #[Given('the module :module is disabled')]
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
   */
  #[Given('the following modules are enabled:')]
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
   */
  #[Given('the following modules are disabled:')]
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
   * Then the module "ctools" should be enabled
   * @endcode
   */
  #[Then('the module :module should be enabled')]
  public function moduleAssertEnabled(string $module): void {
    if (!$this->moduleIsEnabled($module)) {
      throw new AssertionException(sprintf('The module "%s" is not enabled, but it should be.', $module));
    }
  }

  /**
   * Assert that a module is disabled.
   *
   * @code
   * Then the module "shield" should be disabled
   * @endcode
   */
  #[Then('the module :module should be disabled')]
  public function moduleAssertDisabled(string $module): void {
    if ($this->moduleIsEnabled($module)) {
      throw new AssertionException(sprintf('The module "%s" is enabled, but it should not be.', $module));
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
   */
  #[Then('the following modules should be enabled:')]
  public function moduleAssertEnabledMultiple(TableNode $modules_table): void {
    foreach ($modules_table->getColumn(0) as $module) {
      if (!$this->moduleIsEnabled($module)) {
        throw new AssertionException(sprintf('The module "%s" is not enabled, but it should be.', $module));
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
   */
  #[Then('the following modules should be disabled:')]
  public function moduleAssertDisabledMultiple(TableNode $modules_table): void {
    foreach ($modules_table->getColumn(0) as $module) {
      if ($this->moduleIsEnabled($module)) {
        throw new AssertionException(sprintf('The module "%s" is enabled, but it should not be.', $module));
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
  public function moduleIsEnabled(string $module): bool {
    return $this->backendFor(ModuleCapabilityInterface::class)->moduleIsEnabled($module);
  }

  /**
   * Enable a module.
   *
   * @param string $module
   *   The module machine name.
   */
  public function moduleEnable(string $module): void {
    // @codeCoverageIgnoreStart
    if ($this->moduleIsEnabled($module)) {
      return;
    }
    // @codeCoverageIgnoreEnd
    if (!$this->moduleIsPresent($module)) {
      throw new \RuntimeException(sprintf('Cannot enable module "%s": module is not installed.', $module));
    }

    // @codeCoverageIgnoreStart
    try {
      $this->backendFor(ModuleCapabilityInterface::class)->moduleInstall($module);
      // An install leaves the running container holding the pre-install
      // service and route definitions.
      $this->backendFor(CacheCapabilityInterface::class)->cacheClear();
    }
    catch (\Exception $e) {
      throw new \RuntimeException(sprintf('Failed to enable module "%s": %s.', $module, $e->getMessage()), $e->getCode(), $e);
    }
    // @codeCoverageIgnoreEnd
  }

  /**
   * Disable a module.
   *
   * @param string $module
   *   The module machine name.
   */
  public function moduleDisable(string $module): void {
    // @codeCoverageIgnoreStart
    if (!$this->moduleIsEnabled($module)) {
      return;
    }
    // @codeCoverageIgnoreEnd
    // @codeCoverageIgnoreStart
    try {
      $this->backendFor(ModuleCapabilityInterface::class)->moduleUninstall($module);
      // An uninstall leaves the running container holding the pre-uninstall
      // service and route definitions.
      $this->backendFor(CacheCapabilityInterface::class)->cacheClear();
    }
    catch (\Exception $e) {
      throw new \RuntimeException(sprintf('Failed to disable module "%s": %s.', $module, $e->getMessage()), $e->getCode(), $e);
    }
    // @codeCoverageIgnoreEnd
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
  public function moduleIsPresent(string $module): bool {
    return $this->backendFor(ModuleCapabilityInterface::class)->moduleIsPresent($module);
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

  /**
   * Declares the options this trait reads.
   *
   * @return array<int, \DrevOps\BehatSteps\Behat\Config\Option>
   *   The options this trait declares.
   */
  protected function moduleConfigSchema(): array {
    return [
      new Option('enabled', default: TRUE, description: 'Apply the `@module:` tags of a scenario and restore the original module states afterwards.'),
    ];
  }

}
