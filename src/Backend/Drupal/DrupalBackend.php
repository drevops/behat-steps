<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Drupal;

use DrevOps\BehatSteps\Backend\Capability\AuthenticationCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\CreationAliasCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Core\Core;
use DrevOps\BehatSteps\Backend\Drupal\Core\CoreInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;
use DrevOps\BehatSteps\Backend\Exception\BootstrapException;
use DrevOps\BehatSteps\Backend\Exception\UnsupportedBackendActionException;
use DrevOps\BehatSteps\Backend\Random\RandomGeneratorInterface;

/**
 * Fully bootstraps Drupal and uses native API calls.
 */
class DrupalBackend implements DrupalBackendInterface, CreationAliasCapabilityInterface {

  /**
   * Whether Drupal has been bootstrapped.
   */
  protected bool $bootstrapped = FALSE;

  /**
   * Drupal core object.
   */
  protected CoreInterface $core;

  /**
   * System path to the Drupal installation.
   */
  protected readonly string $drupalRoot;

  /**
   * Drupal core version.
   */
  protected int $version;

  /**
   * Sets the Drupal root and URI.
   *
   * @param string $drupal_root
   *   The Drupal root path.
   * @param string $uri
   *   The URI for the Drupal installation.
   *
   * @throws \DrevOps\BehatSteps\Backend\Exception\BootstrapException
   *   Thrown when no Drupal installation is found at the root path, or when
   *   its core major version is not numeric or below 11.
   */
  public function __construct(string $drupal_root, protected readonly string $uri) {
    $resolved = realpath($drupal_root);

    if ($resolved === FALSE) {
      throw new BootstrapException(sprintf('No Drupal installation found at %s.', $drupal_root));
    }

    $this->drupalRoot = $resolved;
    $this->version = $this->detectMajorVersion();
  }

  /**
   * {@inheritdoc}
   */
  public function getRandom(): RandomGeneratorInterface {
    return $this->getCore()->getRandom();
  }

  /**
   * {@inheritdoc}
   */
  public function bootstrap(): void {
    $this->getCore()->bootstrap();
    $this->bootstrapped = TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function isBootstrapped(): bool {
    return $this->bootstrapped;
  }

  /**
   * {@inheritdoc}
   */
  public function processBatch(): void {
    $this->getCore()->processBatch();
  }

  /**
   * {@inheritdoc}
   */
  public function getDrupalVersion(): int {
    return $this->version;
  }

  /**
   * Sets the core from the current version.
   *
   * Uses the first class that exists in the lookup chain:
   * 'DrevOps\BehatSteps\Backend\Drupal\Core{N}\Core' for each major version from
   * the detected one downwards, then 'DrevOps\BehatSteps\Backend\Drupal\Core\Core'.
   *
   * @throws \DrevOps\BehatSteps\Backend\Exception\BootstrapException
   *   When a version-specific Core class exists but does not implement
   *   'CoreInterface'.
   */
  public function setCoreFromVersion(): void {
    $version = $this->getDrupalVersion();
    $candidates = [];

    for ($n = $version; $n >= 11; $n--) {
      $candidates[] = sprintf('DrevOps\\BehatSteps\\Backend\\Drupal\\Core%d\\Core', $n);
    }

    foreach ($candidates as $class) {
      if (!class_exists($class)) {
        continue;
      }

      $core = new $class($this->drupalRoot, $this->uri);

      if (!$core instanceof CoreInterface) {
        throw new BootstrapException(sprintf('%s must implement %s.', $class, CoreInterface::class));
      }

      $this->core = $core;

      return;
    }

    $this->core = new Core($this->drupalRoot, $this->uri);
  }

  /**
   * {@inheritdoc}
   */
  public function getCore(): CoreInterface {
    return $this->core;
  }

  /**
   * {@inheritdoc}
   */
  public function setCore(CoreInterface $core): void {
    $this->core = $core;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreationAliases(string $entity_type): array {
    if (!isset($this->core)) {
      return [];
    }

    $core = $this->getCore();

    if (!$core instanceof CreationAliasCapabilityInterface) {
      return [];
    }

    return $core->getCreationAliases($entity_type);
  }

  /**
   * {@inheritdoc}
   */
  public function login(EntityStubInterface $stub): void {
    $this->getAuthCore()->login($stub);
  }

  /**
   * {@inheritdoc}
   */
  public function logout(): void {
    $this->getAuthCore()->logout();
  }

  /**
   * {@inheritdoc}
   */
  public function cacheClear(): void {
    $this->getCore()->cacheClear();
  }

  /**
   * {@inheritdoc}
   */
  public function cacheClearStatic(): void {
    $this->getCore()->cacheClearStatic();
  }

  /**
   * {@inheritdoc}
   */
  public function configGet(string $name, ?string $key = NULL): mixed {
    return $this->getCore()->configGet($name, $key);
  }

  /**
   * {@inheritdoc}
   */
  public function configGetOriginal(string $name, ?string $key = NULL): mixed {
    return $this->getCore()->configGetOriginal($name, $key);
  }

  /**
   * {@inheritdoc}
   */
  public function configSet(string $name, string $key, mixed $value): void {
    $this->getCore()->configSet($name, $key, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function configExists(string $name): bool {
    return $this->getCore()->configExists($name);
  }

  /**
   * {@inheritdoc}
   */
  public function configGetData(string $name): array {
    return $this->getCore()->configGetData($name);
  }

  /**
   * {@inheritdoc}
   */
  public function configSetData(string $name, array $data): void {
    $this->getCore()->configSetData($name, $data);
  }

  /**
   * {@inheritdoc}
   */
  public function configDelete(string $name): void {
    $this->getCore()->configDelete($name);
  }

  /**
   * {@inheritdoc}
   */
  public function stateGet(string $name): mixed {
    return $this->getCore()->stateGet($name);
  }

  /**
   * {@inheritdoc}
   */
  public function stateSet(string $name, mixed $value): void {
    $this->getCore()->stateSet($name, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function stateDelete(string $name): void {
    $this->getCore()->stateDelete($name);
  }

  /**
   * {@inheritdoc}
   */
  public function stateExists(string $name): bool {
    return $this->getCore()->stateExists($name);
  }

  /**
   * {@inheritdoc}
   */
  public function createNode(EntityStubInterface $stub): EntityStubInterface {
    return $this->getCore()->createNode($stub);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteNode(EntityStubInterface $stub): void {
    $this->getCore()->deleteNode($stub);
  }

  /**
   * {@inheritdoc}
   */
  public function createTerm(EntityStubInterface $stub): EntityStubInterface {
    return $this->getCore()->createTerm($stub);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteTerm(EntityStubInterface $stub): void {
    $this->getCore()->deleteTerm($stub);
  }

  /**
   * {@inheritdoc}
   */
  public function createEntity(EntityStubInterface $stub): EntityStubInterface {
    return $this->getCore()->createEntity($stub);
  }

  /**
   * {@inheritdoc}
   */
  public function updateEntity(EntityStubInterface $stub): EntityStubInterface {
    return $this->getCore()->updateEntity($stub);
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityValues(string $entity_type, int|string $id, array $fields): array {
    return $this->getCore()->getEntityValues($entity_type, $id, $fields);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteEntity(EntityStubInterface $stub): void {
    $this->getCore()->deleteEntity($stub);
  }

  /**
   * {@inheritdoc}
   */
  public function createConfigEntity(EntityStubInterface $stub): EntityStubInterface {
    return $this->getCore()->createConfigEntity($stub);
  }

  /**
   * {@inheritdoc}
   */
  public function updateConfigEntity(EntityStubInterface $stub): EntityStubInterface {
    return $this->getCore()->updateConfigEntity($stub);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteConfigEntity(EntityStubInterface $stub): void {
    $this->getCore()->deleteConfigEntity($stub);
  }

  /**
   * {@inheritdoc}
   */
  public function duplicateConfigEntity(EntityStubInterface $source, array $overrides): EntityStubInterface {
    return $this->getCore()->duplicateConfigEntity($source, $overrides);
  }

  /**
   * {@inheritdoc}
   */
  public function findEntityIds(string $entity_type, array $conditions = [], ?string $bundle = NULL): array {
    return $this->getCore()->findEntityIds($entity_type, $conditions, $bundle);
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityIdKey(string $entity_type): string {
    return $this->getCore()->getEntityIdKey($entity_type);
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityFieldTypes(string $entity_type, ?string $bundle = NULL): array {
    return $this->getCore()->getEntityFieldTypes($entity_type, $bundle);
  }

  /**
   * {@inheritdoc}
   */
  public function parseEntityFields(EntityStubInterface $stub, array $ignored_properties = []): void {
    $this->getCore()->parseEntityFields($stub, $ignored_properties);
  }

  /**
   * {@inheritdoc}
   */
  public function placeBlock(EntityStubInterface $stub): EntityStubInterface {
    return $this->getCore()->placeBlock($stub);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteBlock(EntityStubInterface $stub): void {
    $this->getCore()->deleteBlock($stub);
  }

  /**
   * {@inheritdoc}
   */
  public function createBlockContent(EntityStubInterface $stub): EntityStubInterface {
    return $this->getCore()->createBlockContent($stub);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteBlockContent(EntityStubInterface $stub): void {
    $this->getCore()->deleteBlockContent($stub);
  }

  /**
   * {@inheritdoc}
   */
  public function cronRun(): bool {
    return $this->getCore()->cronRun();
  }

  /**
   * {@inheritdoc}
   */
  public function createLanguage(EntityStubInterface $stub): EntityStubInterface {
    return $this->getCore()->createLanguage($stub);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteLanguage(EntityStubInterface $stub): void {
    $this->getCore()->deleteLanguage($stub);
  }

  /**
   * {@inheritdoc}
   */
  public function mailStartCollecting(array $handler_types = ['default']): void {
    $this->getCore()->mailStartCollecting($handler_types);
  }

  /**
   * {@inheritdoc}
   */
  public function mailStopCollecting(): void {
    $this->getCore()->mailStopCollecting();
  }

  /**
   * {@inheritdoc}
   */
  public function mailGet(): array {
    return $this->getCore()->mailGet();
  }

  /**
   * {@inheritdoc}
   */
  public function mailClear(): void {
    $this->getCore()->mailClear();
  }

  /**
   * {@inheritdoc}
   */
  public function mailSend(string $body, string $subject, string $to, string $langcode, array $attachments = []): bool {
    return $this->getCore()->mailSend($body, $subject, $to, $langcode, $attachments);
  }

  /**
   * {@inheritdoc}
   */
  public function moduleInstall(string $module_name): void {
    $this->getCore()->moduleInstall($module_name);
  }

  /**
   * {@inheritdoc}
   */
  public function moduleIsEnabled(string $module_name): bool {
    return $this->getCore()->moduleIsEnabled($module_name);
  }

  /**
   * {@inheritdoc}
   */
  public function moduleIsPresent(string $module_name): bool {
    return $this->getCore()->moduleIsPresent($module_name);
  }

  /**
   * {@inheritdoc}
   */
  public function moduleUninstall(string $module_name): void {
    $this->getCore()->moduleUninstall($module_name);
  }

  /**
   * {@inheritdoc}
   */
  public function createRole(array $permissions, ?string $id = NULL, ?string $label = NULL): EntityStubInterface {
    return $this->getCore()->createRole($permissions, $id, $label);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteRole(string $role_name): void {
    $this->getCore()->deleteRole($role_name);
  }

  /**
   * {@inheritdoc}
   */
  public function createUser(EntityStubInterface $stub): EntityStubInterface {
    return $this->getCore()->createUser($stub);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteUser(EntityStubInterface $stub): void {
    $this->getCore()->deleteUser($stub);
  }

  /**
   * {@inheritdoc}
   */
  public function addUserRole(EntityStubInterface $stub, string $role): void {
    $this->getCore()->addUserRole($stub, $role);
  }

  /**
   * {@inheritdoc}
   */
  public function getOneTimeLoginUrl(EntityStubInterface $stub): string {
    return $this->getCore()->getOneTimeLoginUrl($stub);
  }

  /**
   * {@inheritdoc}
   */
  public function getUserRoles(EntityStubInterface $stub): array {
    return $this->getCore()->getUserRoles($stub);
  }

  /**
   * {@inheritdoc}
   */
  public function setUserPassword(EntityStubInterface $stub, string $password): void {
    $this->getCore()->setUserPassword($stub, $password);
  }

  /**
   * {@inheritdoc}
   */
  public function setUserLastAccessTime(EntityStubInterface $stub, int $timestamp): void {
    $this->getCore()->setUserLastAccessTime($stub, $timestamp);
  }

  /**
   * {@inheritdoc}
   */
  public function setUserLastLoginTime(EntityStubInterface $stub, int $timestamp): void {
    $this->getCore()->setUserLastLoginTime($stub, $timestamp);
  }

  /**
   * {@inheritdoc}
   */
  public function cacheClearBin(string $bin): void {
    $this->getCore()->cacheClearBin($bin);
  }

  /**
   * {@inheritdoc}
   */
  public function cacheInvalidateTags(array $tags): void {
    $this->getCore()->cacheInvalidateTags($tags);
  }

  /**
   * {@inheritdoc}
   */
  public function cacheDeletePagePath(string $bin, string $path, bool $is_pattern = FALSE): void {
    $this->getCore()->cacheDeletePagePath($bin, $path, $is_pattern);
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityUrl(EntityStubInterface $stub, string $rel = 'canonical'): string {
    return $this->getCore()->getEntityUrl($stub, $rel);
  }

  /**
   * {@inheritdoc}
   */
  public function setEntityPathAlias(EntityStubInterface $stub, string $alias): void {
    $this->getCore()->setEntityPathAlias($stub, $alias);
  }

  /**
   * {@inheritdoc}
   */
  public function getMenuLinkPluginId(EntityStubInterface $stub): string {
    return $this->getCore()->getMenuLinkPluginId($stub);
  }

  /**
   * {@inheritdoc}
   */
  public function rebuildNodeAccess(?int $nid = NULL): void {
    $this->getCore()->rebuildNodeAccess($nid);
  }

  /**
   * {@inheritdoc}
   */
  public function findBlockPluginId(string $admin_label): ?string {
    return $this->getCore()->findBlockPluginId($admin_label);
  }

  /**
   * {@inheritdoc}
   */
  public function hasConditionPlugin(string $id): bool {
    return $this->getCore()->hasConditionPlugin($id);
  }

  /**
   * {@inheritdoc}
   */
  public function queueAddItem(string $queue, mixed $data): void {
    $this->getCore()->queueAddItem($queue, $data);
  }

  /**
   * {@inheritdoc}
   */
  public function queueCountItems(string $queue): int {
    return $this->getCore()->queueCountItems($queue);
  }

  /**
   * {@inheritdoc}
   */
  public function queueDelete(string $queue): void {
    $this->getCore()->queueDelete($queue);
  }

  /**
   * {@inheritdoc}
   */
  public function queueProcess(string $queue, int $limit, int $lease_time): int {
    return $this->getCore()->queueProcess($queue, $limit, $lease_time);
  }

  /**
   * {@inheritdoc}
   */
  public function logGetLastId(): int {
    return $this->getCore()->logGetLastId();
  }

  /**
   * {@inheritdoc}
   */
  public function logRead(array $types, int $after_id, int $severity): array {
    return $this->getCore()->logRead($types, $after_id, $severity);
  }

  /**
   * {@inheritdoc}
   */
  public function logDelete(array $ids): void {
    $this->getCore()->logDelete($ids);
  }

  /**
   * {@inheritdoc}
   */
  public function fileWriteUnmanaged(string $uri, string $content): void {
    $this->getCore()->fileWriteUnmanaged($uri, $content);
  }

  /**
   * {@inheritdoc}
   */
  public function fileReadUnmanaged(string $uri): string {
    return $this->getCore()->fileReadUnmanaged($uri);
  }

  /**
   * {@inheritdoc}
   */
  public function fileExistsUnmanaged(string $uri): bool {
    return $this->getCore()->fileExistsUnmanaged($uri);
  }

  /**
   * {@inheritdoc}
   */
  public function fileDeleteUnmanaged(string $uri): void {
    $this->getCore()->fileDeleteUnmanaged($uri);
  }

  /**
   * {@inheritdoc}
   */
  public function filePrepareDirectory(string $uri): bool {
    return $this->getCore()->filePrepareDirectory($uri);
  }

  /**
   * {@inheritdoc}
   */
  public function fileWriteManaged(string $uri, string $content, EntityStubInterface $stub): EntityStubInterface {
    return $this->getCore()->fileWriteManaged($uri, $content, $stub);
  }

  /**
   * {@inheritdoc}
   */
  public function searchIndexEntity(string $entity_type, int|string $id): bool {
    return $this->getCore()->searchIndexEntity($entity_type, $id);
  }

  /**
   * {@inheritdoc}
   */
  public function searchIndexItems(int $limit): void {
    $this->getCore()->searchIndexItems($limit);
  }

  /**
   * {@inheritdoc}
   */
  public function searchRunCron(string $module = 'search_api'): void {
    $this->getCore()->searchRunCron($module);
  }

  /**
   * {@inheritdoc}
   */
  public function hasWorkflowState(string $entity_type, string $bundle, string $state): bool {
    return $this->getCore()->hasWorkflowState($entity_type, $bundle, $state);
  }

  /**
   * {@inheritdoc}
   */
  public function draggableviewsSetOrder(string $view_id, string $display_id, array $entity_ids): void {
    $this->getCore()->draggableviewsSetOrder($view_id, $display_id, $entity_ids);
  }

  /**
   * Detects the major Drupal version from the filesystem.
   *
   * @return int
   *   The major version number, 11 or higher.
   */
  protected function detectMajorVersion(): int {
    $version_files = [
      '/autoload.php',
      '/core/includes/bootstrap.inc',
    ];

    // A root missing either file cannot define '\Drupal' itself, so VERSION
    // would describe whichever installation the process loaded first.
    foreach ($version_files as $path) {
      if (file_exists($this->drupalRoot . $path)) {
        continue;
      }

      throw new BootstrapException(sprintf('No Drupal installation found at %s: %s is missing.', $this->drupalRoot, $path));
    }

    foreach ($version_files as $path) {
      require_once $this->drupalRoot . $path;
    }

    $version_string = $this->readVersionConstant();
    $major = explode('.', $version_string)[0];

    if (!is_numeric($major)) {
      throw new BootstrapException(sprintf('Unable to extract major Drupal core version from version string %s.', $version_string));
    }

    if ((int) $major < 11) {
      throw new BootstrapException(sprintf('Unsupported Drupal core version %s. Drupal 11 or higher is required.', $version_string));
    }

    return (int) $major;
  }

  /**
   * Reads the Drupal VERSION constant.
   *
   * A subclass can override this to return a synthetic version.
   */
  protected function readVersionConstant(): string {
    return \Drupal::VERSION;
  }

  /**
   * Returns the active Core, having asserted that it can authenticate.
   *
   * @return \DrevOps\BehatSteps\Backend\Capability\AuthenticationCapabilityInterface
   *   The active Core.
   *
   * @throws \DrevOps\BehatSteps\Backend\Exception\UnsupportedBackendActionException
   *   Thrown when the active Core does not implement the authentication
   *   capability.
   */
  protected function getAuthCore(): AuthenticationCapabilityInterface {
    $core = $this->getCore();

    if ($core instanceof AuthenticationCapabilityInterface) {
      return $core;
    }

    $template = 'Authentication is not supported by %s: its Core ' . $core::class . ' does not implement ' . AuthenticationCapabilityInterface::class . '.';

    throw new UnsupportedBackendActionException($template, $this);
  }

}
