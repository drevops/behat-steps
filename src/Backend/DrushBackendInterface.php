<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend;

use DrevOps\BehatSteps\Backend\Capability\BatchCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\CacheCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\ConfigCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\CronCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\DrushCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\ModuleCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\RoleCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\StateCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\UserCapabilityInterface;

/**
 * Contract for the Drush-based backend.
 *
 * Interacts with the site by shelling out to Drush. Supports the subset of
 * operations that Drush services natively through its built-in commands.
 */
interface DrushBackendInterface extends
  BackendInterface,
  BatchCapabilityInterface,
  CacheCapabilityInterface,
  ConfigCapabilityInterface,
  CronCapabilityInterface,
  DrushCapabilityInterface,
  ModuleCapabilityInterface,
  RoleCapabilityInterface,
  StateCapabilityInterface,
  UserCapabilityInterface {}
