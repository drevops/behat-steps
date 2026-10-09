<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend;

use DrevOps\BehatSteps\Backend\Capability\AuthenticationCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\BatchCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\BlockCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\CacheCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\ConfigCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\ContentCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\CoreCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\CronCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\LanguageCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\MailCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\ModuleCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\RoleCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\StateCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\UserCapabilityInterface;
use DrevOps\BehatSteps\Backend\Core\CoreInterface;

/**
 * Contract for the full-featured Drupal backend.
 *
 * Bootstraps Drupal in-process. The contract covers every capability except
 * 'DrushCapabilityInterface' and 'CreationAliasCapabilityInterface'.
 */
interface DrupalBackendInterface extends
  BackendInterface,
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
  StateCapabilityInterface,
  UserCapabilityInterface {

  /**
   * Injects the active Core implementation.
   *
   * Overrides the backend's default Core lookup. Any class that implements
   * 'CoreInterface' is accepted; the class name and namespace do not matter.
   *
   * @param \DrevOps\BehatSteps\Backend\Core\CoreInterface $core
   *   The Core instance the backend should delegate to.
   */
  public function setCore(CoreInterface $core): void;

}
