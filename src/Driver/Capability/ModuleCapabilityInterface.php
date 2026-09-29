<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Driver\Capability;

/**
 * Capability: install and uninstall modules.
 */
interface ModuleCapabilityInterface {

  /**
   * Installs a module.
   *
   * @param string $module_name
   *   The module machine name.
   */
  public function moduleInstall(string $module_name): void;

  /**
   * Uninstalls a module.
   *
   * @param string $module_name
   *   The module machine name.
   */
  public function moduleUninstall(string $module_name): void;

  /**
   * Whether a module is installed and enabled.
   *
   * @param string $module_name
   *   The module machine name.
   */
  public function moduleIsEnabled(string $module_name): bool;

  /**
   * Whether a module's code is present, installed or not.
   *
   * Distinguishes a module that is merely disabled from one the site cannot
   * install because its code is absent.
   *
   * @param string $module_name
   *   The module machine name.
   */
  public function moduleIsPresent(string $module_name): bool;

}
