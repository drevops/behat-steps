<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Drupal;

use DrevOps\BehatSteps\Backend\BackendInterface;
use DrevOps\BehatSteps\Backend\Capability\CacheCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\CronCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\RequestEnvironmentCapabilityInterface;
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
use DrevOps\BehatSteps\Backend\Drupal\Drush\CommandsInterface;

/**
 * Contract for the Drush-based backend.
 *
 * Interacts with the site by running Drush as a subprocess. Supports the
 * subset of operations that Drush services natively through its built-in
 * commands, and declares only the capabilities it serves in full.
 *
 * The backend runs each command and forwards every other capability call to
 * the commands of the Drush major it detects.
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
  RequestEnvironmentCapabilityInterface,
  RoleCapabilityInterface,
  SearchIndexCapabilityInterface,
  StateCapabilityInterface,
  UserCapabilityInterface {

  /**
   * Returns the major version of the Drush binary.
   *
   * @throws \DrevOps\BehatSteps\Backend\Exception\BootstrapException
   *   When the major is below the lowest the backend supports.
   * @throws \RuntimeException
   *   When Drush does not print its version.
   */
  public function getDrushVersion(): int;

  /**
   * Returns the commands the backend delegates to, building them on first use.
   *
   * @throws \DrevOps\BehatSteps\Backend\Exception\BootstrapException
   *   When the commands cannot be built for the detected major.
   */
  public function getCommands(): CommandsInterface;

  /**
   * Replaces the commands the backend delegates to.
   *
   * A class implementing 'CommandsInterface' and built on no commands of this
   * package is accepted as it is. Otherwise, the nearest commands of this
   * package it is or extends have to be the ones this package ships for the
   * detected major.
   *
   * @param \DrevOps\BehatSteps\Backend\Drupal\Drush\CommandsInterface $commands
   *   The commands the backend should delegate to.
   *
   * @throws \DrevOps\BehatSteps\Backend\Exception\BootstrapException
   *   When the nearest commands of this package the commands are or extend
   *   are the ones for another major.
   */
  public function setCommands(CommandsInterface $commands): void;

}
