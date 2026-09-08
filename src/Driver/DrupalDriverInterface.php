<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Driver;

use DrevOps\BehatSteps\Driver\Capability\AuthenticationCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\BatchCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\BlockCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\CacheCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\ConfigCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\ContentCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\CronCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\LanguageCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\MailCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\ModuleCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\RoleCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\UserCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\WatchdogCapabilityInterface;
use DrevOps\BehatSteps\Driver\Core\CoreInterface;

/**
 * Contract for the full-featured Drupal driver.
 *
 * Bootstraps Drupal in-process and supports every capability.
 */
interface DrupalDriverInterface extends
  DriverInterface,
  SubDriverFinderInterface,
  AuthenticationCapabilityInterface,
  BatchCapabilityInterface,
  BlockCapabilityInterface,
  CacheCapabilityInterface,
  ConfigCapabilityInterface,
  ContentCapabilityInterface,
  CronCapabilityInterface,
  LanguageCapabilityInterface,
  MailCapabilityInterface,
  ModuleCapabilityInterface,
  RoleCapabilityInterface,
  UserCapabilityInterface,
  WatchdogCapabilityInterface {

  /**
   * Return current core.
   */
  public function getCore(): CoreInterface;

  /**
   * Injects the active Core implementation.
   *
   * Consumers override the driver's default Core lookup by passing any
   * class that implements 'CoreInterface' - the class name and namespace
   * do not matter. Typically called in a test bootstrap when the project
   * ships its own Core subclass (e.g. one that registers additional field
   * handlers in its 'registerDefaultFieldHandlers()' override).
   *
   * @param \DrevOps\BehatSteps\Driver\Core\CoreInterface $core
   *   The Core instance the driver should delegate to.
   */
  public function setCore(CoreInterface $core): void;

  /**
   * Returns the major Drupal version detected at construction time.
   *
   * The version is captured once when the driver is instantiated; the
   * detection itself may throw BootstrapException, but this getter does not.
   *
   * @return int
   *   The major Drupal version.
   *
   * @see drush_drupal_version()
   */
  public function getDrupalVersion(): int;

}
