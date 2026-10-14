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
use DrevOps\BehatSteps\Backend\Capability\RoleCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\UserCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\BatchCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\BlockCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\ConfigCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\ConfigEntityCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\CoreCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\DraggableviewsCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\EntityDefinitionCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\FileCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\LogCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\ModuleCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\NodeCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\PathAliasCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\QueueCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Capability\QueueItemCapabilityInterface;
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
  FileCapabilityInterface,
  LanguageCapabilityInterface,
  LogCapabilityInterface,
  MailCapabilityInterface,
  ModuleCapabilityInterface,
  NodeCapabilityInterface,
  PathAliasCapabilityInterface,
  QueueCapabilityInterface,
  QueueItemCapabilityInterface,
  RoleCapabilityInterface,
  SearchApiCapabilityInterface,
  SearchIndexCapabilityInterface,
  StateCapabilityInterface,
  TermCapabilityInterface,
  UserActivityCapabilityInterface,
  UserCapabilityInterface,
  WorkflowCapabilityInterface {

  /**
   * Injects the active Core implementation.
   *
   * Overrides the backend's default Core lookup. Any class that implements
   * 'CoreInterface' is accepted; the class name and namespace do not matter.
   *
   * @param \DrevOps\BehatSteps\Backend\Drupal\Core\CoreInterface $core
   *   The Core instance the backend should delegate to.
   */
  public function setCore(CoreInterface $core): void;

}
