<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Drupal\Drush;

use DrevOps\BehatSteps\Backend\Capability\CacheCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\CreationAliasCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\CronCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\RoleCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\UserCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\ConfigCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\LogCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\ModuleCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\QueueCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\SearchIndexCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\StateCapabilityInterface;
use DrevOps\BehatSteps\Backend\Experimental\ExperimentalAwareInterface;

/**
 * Contract for the Drush commands of 1 Drush major.
 *
 * The Drush backend picks the commands for the major it detects and forwards
 * every capability call to them. The commands run Drush through the backend,
 * so the process, its global options and its environment stay in 1 place.
 */
interface CommandsInterface extends
  CacheCapabilityInterface,
  ConfigCapabilityInterface,
  CreationAliasCapabilityInterface,
  CronCapabilityInterface,
  ExperimentalAwareInterface,
  LogCapabilityInterface,
  ModuleCapabilityInterface,
  QueueCapabilityInterface,
  RoleCapabilityInterface,
  SearchIndexCapabilityInterface,
  StateCapabilityInterface,
  UserCapabilityInterface {}
