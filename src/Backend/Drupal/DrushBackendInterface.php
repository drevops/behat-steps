<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Drupal;

use DrevOps\BehatSteps\Backend\BackendInterface;
use DrevOps\BehatSteps\Backend\Capability\CacheCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\CronCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\RoleCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\UserCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\BatchCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\ConfigCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\DrushCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\LogCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\ModuleCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\QueueCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\SearchIndexCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\StateCapabilityInterface;

/**
 * Contract for the Drush-based backend.
 *
 * Interacts with the site by running Drush as a subprocess. Supports the
 * subset of operations that Drush services natively through its built-in
 * commands, and declares only the capabilities it serves in full.
 */
interface DrushBackendInterface extends
  BackendInterface,
  BatchCapabilityInterface,
  CacheCapabilityInterface,
  ConfigCapabilityInterface,
  CronCapabilityInterface,
  DrushCapabilityInterface,
  LogCapabilityInterface,
  ModuleCapabilityInterface,
  QueueCapabilityInterface,
  RoleCapabilityInterface,
  SearchIndexCapabilityInterface,
  StateCapabilityInterface,
  UserCapabilityInterface {}
