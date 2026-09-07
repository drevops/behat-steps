<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Driver;

use Drupal\Component\Utility\Random;
use DrevOps\BehatSteps\Driver\Capability\AuthenticationCapabilityInterface;
use DrevOps\BehatSteps\Driver\Capability\CreationAliasCapabilityInterface;
use DrevOps\BehatSteps\Driver\Core\Core;
use DrevOps\BehatSteps\Driver\Core\CoreInterface;
use DrevOps\BehatSteps\Driver\Entity\EntityStubInterface;
use DrevOps\BehatSteps\Driver\Exception\BootstrapException;
use DrevOps\BehatSteps\Driver\Exception\UnsupportedDriverActionException;

/**
 * Fully bootstraps Drupal and uses native API calls.
 */
class DrupalDriver implements DrupalDriverInterface, CreationAliasCapabilityInterface {

  /**
   * Track whether Drupal has been bootstrapped.
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
   * URI for the Drupal installation.
   */
  protected readonly string $uri;

  /**
   * Drupal core version.
   */
  protected int $version;

  /**
   * Set Drupal root and URI.
   *
   * @param string $drupal_root
   *   The Drupal root path.
   * @param string $uri
   *   The URI for the Drupal installation.
   *
   * @throws \DrevOps\BehatSteps\Driver\Exception\BootstrapException
   *   Thrown when the Drupal installation is not found in the given root path.
   */
  public function __construct(string $drupal_root, string $uri) {
    $resolved = realpath($drupal_root);

    if ($resolved === FALSE) {
      throw new BootstrapException(sprintf('No Drupal installation found at %s', $drupal_root));
    }

    $this->drupalRoot = $resolved;
    $this->uri = $uri;
    $this->version = $this->detectMajorVersion();
  }

  /**
   * {@inheritdoc}
   */
  public function getRandom(): Random {
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
   * Automatically set the core from the current version.
   *
   * Walks from the detected Drupal version down to the default Core class,
   * using the first class that exists in the lookup chain:
   * DrevOps\BehatSteps\Driver\Core{N}\Core → ... → DrevOps\BehatSteps\Driver\Core\Core.
   *
   * @throws \DrevOps\BehatSteps\Driver\Exception\BootstrapException
   *   Thrown when no Core implementation is found for the detected version.
   */
  public function setCoreFromVersion(): void {
    $version = $this->getDrupalVersion();
    $candidates = [];

    for ($n = $version; $n >= 11; $n--) {
      $candidates[] = sprintf('DrevOps\\BehatSteps\\Driver\\Core%d\\Core', $n);
    }

    foreach ($candidates as $class) {
      if (!class_exists($class)) {
        continue;
      }

      $this->core = new $class($this->drupalRoot, $this->uri);

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
  public function getSubDriverPaths(): array {
    // Ensure system is bootstrapped.
    if (!$this->isBootstrapped()) {
      $this->bootstrap();
    }

    return $this->getCore()->getExtensionPathList();
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
  public function cacheClear(?string $type = NULL): void {
    $this->getCore()->cacheClear($type);
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
  public function configGet(string $name, string $key = ''): mixed {
    return $this->getCore()->configGet($name, $key);
  }

  /**
   * {@inheritdoc}
   */
  public function configGetOriginal(string $name, string $key = ''): mixed {
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
  public function nodeCreate(EntityStubInterface $stub): EntityStubInterface {
    return $this->getCore()->nodeCreate($stub);
  }

  /**
   * {@inheritdoc}
   */
  public function nodeDelete(EntityStubInterface $stub): void {
    $this->getCore()->nodeDelete($stub);
  }

  /**
   * {@inheritdoc}
   */
  public function termCreate(EntityStubInterface $stub): EntityStubInterface {
    return $this->getCore()->termCreate($stub);
  }

  /**
   * {@inheritdoc}
   */
  public function termDelete(EntityStubInterface $stub): bool {
    return $this->getCore()->termDelete($stub);
  }

  /**
   * {@inheritdoc}
   */
  public function entityCreate(EntityStubInterface $stub): EntityStubInterface {
    return $this->getCore()->entityCreate($stub);
  }

  /**
   * {@inheritdoc}
   */
  public function entityDelete(EntityStubInterface $stub): void {
    $this->getCore()->entityDelete($stub);
  }

  /**
   * {@inheritdoc}
   */
  public function blockPlace(EntityStubInterface $stub): EntityStubInterface {
    return $this->getCore()->blockPlace($stub);
  }

  /**
   * {@inheritdoc}
   */
  public function blockDelete(EntityStubInterface $stub): void {
    $this->getCore()->blockDelete($stub);
  }

  /**
   * {@inheritdoc}
   */
  public function blockContentCreate(EntityStubInterface $stub): EntityStubInterface {
    return $this->getCore()->blockContentCreate($stub);
  }

  /**
   * {@inheritdoc}
   */
  public function blockContentDelete(EntityStubInterface $stub): void {
    $this->getCore()->blockContentDelete($stub);
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
  public function languageCreate(EntityStubInterface $stub): EntityStubInterface|false {
    return $this->getCore()->languageCreate($stub);
  }

  /**
   * {@inheritdoc}
   */
  public function languageDelete(EntityStubInterface $stub): void {
    $this->getCore()->languageDelete($stub);
  }

  /**
   * {@inheritdoc}
   */
  public function mailStartCollecting(): void {
    $this->getCore()->mailStartCollecting();
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
  public function moduleUninstall(string $module_name): void {
    $this->getCore()->moduleUninstall($module_name);
  }

  /**
   * {@inheritdoc}
   */
  public function roleCreate(array $permissions, ?string $id = NULL, ?string $label = NULL): string {
    return $this->getCore()->roleCreate($permissions, $id, $label);
  }

  /**
   * {@inheritdoc}
   */
  public function roleDelete(string $role_name): void {
    $this->getCore()->roleDelete($role_name);
  }

  /**
   * {@inheritdoc}
   */
  public function userCreate(EntityStubInterface $stub): void {
    $this->getCore()->userCreate($stub);
  }

  /**
   * {@inheritdoc}
   */
  public function userDelete(EntityStubInterface $stub): void {
    $this->getCore()->userDelete($stub);
  }

  /**
   * {@inheritdoc}
   */
  public function userAddRole(EntityStubInterface $stub, string $role): void {
    $this->getCore()->userAddRole($stub, $role);
  }

  /**
   * {@inheritdoc}
   */
  public function watchdogFetch(int $count = 10, ?string $type = NULL, ?string $severity = NULL): string {
    return $this->getCore()->watchdogFetch($count, $type, $severity);
  }

  /**
   * Detects the major Drupal version from the filesystem.
   *
   * @return int
   *   The actual major version number (10, 11, 12, etc.).
   */
  protected function detectMajorVersion(): int {
    $version_files = [
      '/autoload.php',
      '/core/includes/bootstrap.inc',
    ];

    foreach ($version_files as $path) {
      if (!file_exists($this->drupalRoot . $path)) {
        continue;
      }

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
   * Subclasses override this to return a synthetic version for testing the
   * non-numeric and sub-11 branches of 'detectMajorVersion()'.
   */
  protected function readVersionConstant(): string {
    return \Drupal::VERSION;
  }

  /**
   * Returns the active Core, having asserted that it can authenticate.
   *
   * @return \DrevOps\BehatSteps\Driver\Capability\AuthenticationCapabilityInterface
   *   The active Core.
   *
   * @throws \DrevOps\BehatSteps\Driver\Exception\UnsupportedDriverActionException
   *   Thrown when the active Core does not implement the authentication
   *   capability. Returning without acting reports a login that never
   *   happened.
   */
  protected function getAuthCore(): AuthenticationCapabilityInterface {
    $core = $this->getCore();

    if ($core instanceof AuthenticationCapabilityInterface) {
      return $core;
    }

    $template = 'Authentication is not supported by %s: its Core ' . $core::class . ' does not implement ' . AuthenticationCapabilityInterface::class . '.';

    throw new UnsupportedDriverActionException($template, $this);
  }

}
