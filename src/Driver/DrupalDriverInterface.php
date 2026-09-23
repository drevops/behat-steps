<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Driver;

use DrevOps\BehatSteps\Driver\Capability\AuthenticationCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\BatchCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\BlockCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\CacheCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\ConfigCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\ContentCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\CoreCapabilityInterface;
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
  CoreCapabilityInterface,
  CronCapabilityInterface,
  LanguageCapabilityInterface,
  MailCapabilityInterface,
  ModuleCapabilityInterface,
  RoleCapabilityInterface,
  UserCapabilityInterface,
  WatchdogCapabilityInterface {

  /**
   * Injects the active Core implementation.
   *
   * Overrides the driver's default Core lookup. Any class that implements
   * 'CoreInterface' is accepted; the class name and namespace do not matter.
   *
   * @param \DrevOps\BehatSteps\Driver\Core\CoreInterface $core
   *   The Core instance the driver should delegate to.
   */
  public function setCore(CoreInterface $core): void;

}
