<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Driver;

use DrevOps\BehatSteps\Driver\Capability\BatchCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\CacheCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\ConfigCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\CronCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\ModuleCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\RoleCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\UserCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\WatchdogCapabilityInterface;

/**
 * Contract for the Drush-based driver.
 *
 * Interacts with the site by shelling out to Drush. Supports the subset of
 * operations that Drush services natively through its built-in commands.
 */
interface DrushDriverInterface extends
  DriverInterface,
  BatchCapabilityInterface,
  CacheCapabilityInterface,
  ConfigCapabilityInterface,
  CronCapabilityInterface,
  ModuleCapabilityInterface,
  RoleCapabilityInterface,
  UserCapabilityInterface,
  WatchdogCapabilityInterface {

}
