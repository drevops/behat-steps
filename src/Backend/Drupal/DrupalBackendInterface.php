<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Drupal;

use DrevOps\BehatSteps\Backend\BackendInterface;
use DrevOps\BehatSteps\Backend\Capability\AuthenticationCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\CacheCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\ContentCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\CronCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\EntityLookupCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\LanguageCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\MailCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\RequestEnvironmentCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\RoleCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\UserCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\BatchCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\BlockCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\ConfigCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\ConfigEntityCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\CoreCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\DraggableviewsCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\EntityDefinitionCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\FieldHandlerCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\FileCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\LogCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\MenuLinkCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\ModuleCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\NodeCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\PathAliasCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\QueueCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\QueueItemCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\RedirectCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\SearchApiCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\SearchIndexCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\StateCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\TermCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\UserActivityCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\WorkflowCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Core\CoreInterface;

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
  ConfigEntityCapabilityInterface,
  ContentCapabilityInterface,
  CoreCapabilityInterface,
  CronCapabilityInterface,
  DraggableviewsCapabilityInterface,
  EntityDefinitionCapabilityInterface,
  EntityLookupCapabilityInterface,
  FieldHandlerCapabilityInterface,
  FileCapabilityInterface,
  LanguageCapabilityInterface,
  LogCapabilityInterface,
  MailCapabilityInterface,
  MenuLinkCapabilityInterface,
  ModuleCapabilityInterface,
  NodeCapabilityInterface,
  PathAliasCapabilityInterface,
  QueueCapabilityInterface,
  QueueItemCapabilityInterface,
  RedirectCapabilityInterface,
  RequestEnvironmentCapabilityInterface,
  RoleCapabilityInterface,
  SearchApiCapabilityInterface,
  SearchIndexCapabilityInterface,
  StateCapabilityInterface,
  TermCapabilityInterface,
  UserActivityCapabilityInterface,
  UserCapabilityInterface,
  WorkflowCapabilityInterface {

  /**
   * Replaces the Core the backend delegates to.
   *
   * A class implementing 'CoreInterface' and built on no Core of this package
   * is accepted as it is. Otherwise, the nearest Core of this package among
   * the class and its parents has to be the one for the detected major.
   *
   * @param \DrevOps\BehatSteps\Backend\Drupal\Core\CoreInterface $core
   *   The Core instance the backend should delegate to.
   *
   * @throws \DrevOps\BehatSteps\Backend\Exception\BootstrapException
   *   When the nearest Core of this package among the class and its parents
   *   is for another major.
   */
  public function setCore(CoreInterface $core): void;

}
