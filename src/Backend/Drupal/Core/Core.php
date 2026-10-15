<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Backend\Drupal\Core;

use DrevOps\BehatSteps\Backend\Alias\CreationAliasRegistryTrait;
use DrevOps\BehatSteps\Backend\Alias\RolesAlias;
use DrevOps\BehatSteps\Backend\Capability\AuthenticationCapabilityInterface;
use DrevOps\BehatSteps\Backend\Capability\CreationAliasCapabilityInterface;
use DrevOps\BehatSteps\Backend\Drupal\Core\Alias\AuthorAlias;
use DrevOps\BehatSteps\Backend\Drupal\Core\Alias\ParentTermAlias;
use DrevOps\BehatSteps\Backend\Drupal\Core\Alias\VocabularyMachineNameAlias;
use DrevOps\BehatSteps\Backend\Drupal\Core\Field\DefaultHandler;
use DrevOps\BehatSteps\Backend\Drupal\Core\Field\FieldClassifier;
use DrevOps\BehatSteps\Backend\Drupal\Core\Field\FieldClassifierInterface;
use DrevOps\BehatSteps\Backend\Drupal\Core\Field\FieldHandlerInterface;
use DrevOps\BehatSteps\Backend\Drupal\Core\Field\FieldShapeClassifier;
use DrevOps\BehatSteps\Backend\Drupal\Core\Field\FieldShapeClassifierInterface;
use DrevOps\BehatSteps\Backend\Drupal\Core\Field\Parser\EntityFieldParser;
use DrevOps\BehatSteps\Backend\Drupal\Core\Field\Parser\EntityFieldParserInterface;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Backend\Entity\EntityStubInterface;
use DrevOps\BehatSteps\Backend\Exception\BootstrapException;
use DrevOps\BehatSteps\Backend\Random\RandomGenerator;
use DrevOps\BehatSteps\Backend\Random\RandomGeneratorInterface;
use Drupal\block\BlockInterface;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\DatabaseBackend;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\Database\Statement\FetchAs;
use Drupal\Core\DrupalKernel;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\Core\Url;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeAccessControlHandlerInterface;
use Drupal\node\NodeAccessRebuild;
use Drupal\node\NodeInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Plugin\search_api\datasource\ContentEntityTrackingManager;
use Drupal\search_api\Utility\Utility;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\TermInterface;
use Drupal\user\AccountCancellation;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\OneTimeAuthentication;
use Drupal\views\ViewEntityInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Default Drupal core implementation.
 */
class Core implements CoreInterface, AuthenticationCapabilityInterface, CreationAliasCapabilityInterface {

  use CreationAliasRegistryTrait;

  /**
   * The operators a lookup condition may name.
   *
   * @var array<int, string>
   */
  protected const array LOOKUP_OPERATORS = ['=', 'IN', 'CONTAINS', 'LIKE'];

  /**
   * The config object recording the mail handlers the collector replaced.
   *
   * It is saved, so a run that stops before restoring them leaves the record
   * for the next run.
   */
  protected const string MAIL_ORIGINAL_CONFIG = 'system.mail_original';

  /**
   * The mail plugin that collects mail instead of sending it.
   */
  protected const string MAIL_COLLECTOR = 'test_mail_collector';

  /**
   * System path to the Drupal installation.
   */
  protected string $drupalRoot;

  /**
   * Random value generator.
   */
  protected RandomGeneratorInterface $random;

  /**
   * Registered field handler classes, keyed by field type id.
   *
   * @var array<string, class-string<FieldHandlerInterface>>
   */
  protected array $fieldHandlers = [];

  /**
   * Lazily created field classifier instance.
   */
  protected ?FieldClassifierInterface $fieldClassifier = NULL;

  /**
   * Lazily created field shape classifier instance.
   */
  protected ?FieldShapeClassifierInterface $fieldShapeClassifier = NULL;

  /**
   * Permission definitions keyed by machine name, NULL until first read.
   *
   * @var array<string, mixed>|null
   */
  protected ?array $allPermissions = NULL;

  /**
   * Sets up the Core implementation.
   *
   * @param string $drupal_root
   *   The absolute path to the Drupal root directory.
   * @param string $uri
   *   The URI used to access Drupal. Defaults to 'default'.
   * @param \DrevOps\BehatSteps\Backend\Random\RandomGeneratorInterface|null $random
   *   Random-value generator, or NULL for the package default.
   */
  public function __construct(string $drupal_root, protected string $uri = 'default', ?RandomGeneratorInterface $random = NULL) {
    $resolved = realpath($drupal_root);

    if ($resolved === FALSE) {
      throw new BootstrapException(sprintf('Could not resolve Drupal root "%s".', $drupal_root));
    }

    $this->drupalRoot = $resolved;
    $this->random = $random ?? new RandomGenerator();

    $this->registerDefaultFieldHandlers();
    $this->registerDefaultCreationAliases();
  }

  /**
   * Populates the creation-alias registry with aliases this class ships.
   */
  protected function registerDefaultCreationAliases(): void {
    $this->registerCreationAlias(new AuthorAlias());
    $this->registerCreationAlias(new VocabularyMachineNameAlias());
    $this->registerCreationAlias(new ParentTermAlias());
    $this->registerCreationAlias(new RolesAlias($this));
  }

  /**
   * {@inheritdoc}
   */
  public function getRandom(): RandomGeneratorInterface {
    return $this->random;
  }

  /**
   * {@inheritdoc}
   */
  public function registerFieldHandler(string $field_type, string $class): void {
    if (!class_exists($class)) {
      throw new \RuntimeException(sprintf('Handler class "%s" does not exist.', $class));
    }

    if (!is_subclass_of($class, FieldHandlerInterface::class)) {
      throw new \RuntimeException(sprintf('Handler class "%s" must implement "%s".', $class, FieldHandlerInterface::class));
    }

    if ((new \ReflectionClass($class))->isAbstract()) {
      throw new \RuntimeException(sprintf('Handler class "%s" must be instantiable.', $class));
    }

    $this->fieldHandlers[$field_type] = $class;
  }

  /**
   * Populates the field-handler registry with handlers this class ships.
   */
  protected function registerDefaultFieldHandlers(): void {
    $this->registerHandlersFromDirectory(__DIR__ . '/Field', __NAMESPACE__ . '\\Field');
  }

  /**
   * Scans a directory for handler classes and registers each one.
   *
   * A class is registered when it (a) is in '$namespace', (b) is named
   * '*Handler' but is not 'DefaultHandler' (the registry's fallback), (c)
   * is concrete, and (d) implements 'FieldHandlerInterface'. The field
   * type id it gets registered under is derived from the short class name:
   * 'EntityReferenceHandler' → 'entity_reference'.
   *
   * @param string $directory
   *   Absolute filesystem path to the directory containing '*Handler.php'
   *   files.
   * @param string $namespace
   *   Namespace of the classes in '$directory', without a trailing backslash.
   */
  protected function registerHandlersFromDirectory(string $directory, string $namespace): void {
    foreach (glob($directory . '/*Handler.php') ?: [] as $file) {
      $short = basename($file, '.php');

      if ($short === 'DefaultHandler') {
        continue;
      }

      $class = $namespace . '\\' . $short;

      if (!is_subclass_of($class, FieldHandlerInterface::class)) {
        continue;
      }

      if ((new \ReflectionClass($class))->isAbstract()) {
        continue;
      }

      $this->registerFieldHandler($this->deriveFieldType($short), $class);
    }
  }

  /**
   * Converts a handler's short class name to its Drupal field type id.
   *
   * Strips the 'Handler' suffix and snake-cases the remainder:
   * 'EntityReferenceHandler' → 'entity_reference'.
   */
  protected function deriveFieldType(string $short_class_name): string {
    $bare = substr($short_class_name, 0, -strlen('Handler'));

    return strtolower((string) preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $bare));
  }

  /**
   * Creates the field classifier instance for this Core.
   */
  protected function createFieldClassifier(): FieldClassifierInterface {
    return new FieldClassifier($this->getEntityFieldManager());
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldClassifier(): FieldClassifierInterface {
    if (!$this->fieldClassifier instanceof FieldClassifierInterface) {
      $this->fieldClassifier = $this->createFieldClassifier();
    }

    return $this->fieldClassifier;
  }

  /**
   * Creates the field shape classifier instance for this Core.
   */
  protected function createFieldShapeClassifier(): FieldShapeClassifierInterface {
    return new FieldShapeClassifier();
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldShapeClassifier(): FieldShapeClassifierInterface {
    if (!$this->fieldShapeClassifier instanceof FieldShapeClassifierInterface) {
      $this->fieldShapeClassifier = $this->createFieldShapeClassifier();
    }

    return $this->fieldShapeClassifier;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldHandler(EntityStubInterface $stub, string $entity_type, string $field_name): FieldHandlerInterface {
    $bundle = $this->resolveBundle($stub);
    $field_types = $this->getEntityFieldTypes($entity_type, $bundle);

    if (!isset($field_types[$field_name])) {
      throw new \RuntimeException(sprintf('Field "%s" not found on entity type "%s".', $field_name, $entity_type));
    }

    $field_type = $field_types[$field_name];
    $class = $this->fieldHandlers[$field_type] ?? DefaultHandler::class;

    if ($class === DefaultHandler::class) {
      $this->assertDefaultHandlerCanMarshal($entity_type, $field_name, $field_type, $bundle);
    }

    return new $class($stub, $entity_type, $field_name);
  }

  /**
   * Rejects a field the DefaultHandler fallback cannot marshal.
   *
   * @param string $entity_type
   *   The entity type ID.
   * @param string $field_name
   *   The field name.
   * @param string $field_type
   *   The field type ID, for the exception message.
   * @param string $bundle
   *   The bundle name, for the exception message.
   *
   * @throws \RuntimeException
   *   When the field's stored shape is not one the default handler can relay.
   */
  protected function assertDefaultHandlerCanMarshal(string $entity_type, string $field_name, string $field_type, string $bundle): void {
    $storage = $this->getEntityFieldManager()->getFieldStorageDefinitions($entity_type)[$field_name] ?? NULL;

    if ($storage === NULL) {
      return;
    }

    $shape = $this->getFieldShapeClassifier();
    $reason = NULL;

    if ($shape->fieldIsEntityReference($storage)) {
      $reason = 'it is an entity-reference value a dedicated handler must resolve to an id';
    }
    elseif ($shape->fieldIsComplexValue($storage)) {
      $reason = 'it holds a complex or nested value with no single scalar shape';
    }

    if ($reason !== NULL) {
      throw new \RuntimeException(sprintf(
        'No dedicated handler is registered for field "%s" (type "%s") on entity type "%s" bundle "%s", and DefaultHandler cannot marshal it: %s. Map its field type to a handler class under the "drupal.field_handlers" option.',
        $field_name,
        $field_type,
        $entity_type,
        $bundle,
        $reason,
      ));
    }
  }

  /**
   * Expands values on the given stub through the field-handler pipeline.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   Stub whose values bag will be mutated in place.
   */
  protected function expandEntityFields(EntityStubInterface $stub): void {
    $entity_type = $stub->getEntityType();
    $definition = $this->loadEntityTypeDefinition($entity_type);

    // The id key and bundle key identify the record, so neither enters the
    // handler pipeline. EntityReferenceHandler would turn an entity_reference
    // bundle value, such as 'type' on 'commerce_product', into
    // ['target_id' => ...] and break later bundle lookups on the stub.
    $skip = array_filter([$definition->getKey('id'), $definition->getKey('bundle')]);

    $bundle = $this->resolveBundle($stub);
    $field_types = $this->getEntityFieldTypes($entity_type, $bundle);

    foreach (array_keys($field_types) as $field_name) {
      if (in_array($field_name, $skip, TRUE)) {
        continue;
      }

      if (!$stub->hasValue($field_name)) {
        continue;
      }

      $expanded = $this->getFieldHandler($stub, $entity_type, $field_name)
        ->expand($stub->getValue($field_name));
      $stub->setValue($field_name, $expanded);
    }
  }

  /**
   * Resolves the bundle for an entity stub.
   *
   * Reads the entity type's bundle key from the values bag first, then the
   * typed 'bundle' constructor argument, then the entity type id.
   * Single-bundle entities like 'user' use the type id as their bundle.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The stub.
   *
   * @return string
   *   Bundle name. Never empty.
   */
  protected function resolveBundle(EntityStubInterface $stub): string {
    $bundle_key = $this->loadEntityTypeDefinition($stub->getEntityType())->getKey('bundle');

    if ($bundle_key && $stub->hasValue($bundle_key) && !empty($stub->getValue($bundle_key))) {
      return (string) $stub->getValue($bundle_key);
    }

    if ($stub->getBundle() !== NULL && $stub->getBundle() !== '') {
      return $stub->getBundle();
    }

    return $stub->getEntityType();
  }

  /**
   * Resolves an entity type definition, rethrowing with an actionable message.
   *
   * @param string $entity_type
   *   Entity type id to load.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface
   *   The resolved definition.
   *
   * @throws \RuntimeException
   *   If the entity type id is not registered.
   */
  protected function loadEntityTypeDefinition(string $entity_type): EntityTypeInterface {
    try {
      return \Drupal::entityTypeManager()->getDefinition($entity_type);
    }
    catch (PluginNotFoundException $exception) {
      throw new \RuntimeException(sprintf('Unknown entity type "%s".', $entity_type), 0, $exception);
    }
  }

  /**
   * {@inheritdoc}
   *
   * Executed only in consumer code that boots Drupal from outside a test,
   * so coverage is not measurable. Kernel tests already have Drupal booted,
   * and re-entering this path would tear the kernel down.
   *
   * @codeCoverageIgnore
   */
  public function bootstrap(): void {
    if (!defined('DRUPAL_ROOT')) {
      define('DRUPAL_ROOT', $this->drupalRoot);
    }

    // Drupal resolves 'file_public_path' and its other relative settings
    // against the working directory, so the process stays in the root.
    chdir(DRUPAL_ROOT);
    $autoloader = require DRUPAL_ROOT . '/autoload.php';
    require_once DRUPAL_ROOT . '/core/includes/bootstrap.inc';
    $this->validateDrupalSite();

    $request = Request::createFromGlobals();
    $kernel = DrupalKernel::createFromRequest($request, $autoloader, 'prod');
    $kernel->boot();
    // Route matching requires a route on the request.
    $request->attributes->set(RouteObjectInterface::ROUTE_OBJECT, new Route('<none>'));
    $request->attributes->set(RouteObjectInterface::ROUTE_NAME, '<none>');

    $kernel->preHandle($request);

    // The bootstrap requires an anonymous session.
    \Drupal::service('session_manager')->start();
  }

  /**
   * {@inheritdoc}
   */
  public function cacheClear(): void {
    drupal_flush_all_caches();
    $this->allPermissions = NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function createNode(EntityStubInterface $stub): EntityStubInterface {
    $type = $stub->getBundle() ?? $stub->getValue('type');

    if (empty($type)) {
      throw new \RuntimeException("Cannot create content because it is missing the required property 'type'.");
    }

    if (!$this->bundleExists('node', (string) $type)) {
      throw new \RuntimeException(sprintf('Cannot create content because provided content type %s does not exist.', $type));
    }

    if (!$stub->hasValue('type')) {
      $stub->setValue('type', $type);
    }

    $this->applyPreCreateAliases($stub, 'node');

    $this->expandEntityFields($stub);
    $entity = Node::create($stub->getValues());
    $entity->save();

    $stub->setValue('nid', $entity->id());
    $stub->markSaved($entity);

    $this->applyPostCreateAliases($stub, $entity, 'node');

    return $stub;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteNode(EntityStubInterface $stub): void {
    $node = $stub->isSaved() ? $stub->getSavedEntity() : NULL;

    if (!$node instanceof NodeInterface) {
      $nid = $stub->getValue('nid');

      if ($nid === NULL) {
        throw new \RuntimeException('Cannot resolve a node id from the stub: neither the saved entity nor a "nid" value is set.');
      }

      $node = Node::load($nid);
    }

    if ($node instanceof NodeInterface) {
      $node->delete();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function rebuildNodeAccess(?int $nid = NULL): void {
    if ($nid === NULL) {
      \Drupal::service(NodeAccessRebuild::class)->rebuild(FALSE);

      return;
    }

    $node = Node::load($nid);

    if (!$node instanceof NodeInterface) {
      throw new \RuntimeException(sprintf('No node with the id "%d" exists.', $nid));
    }

    $handler = \Drupal::entityTypeManager()->getAccessControlHandler('node');

    // @codeCoverageIgnoreStart
    if (!$handler instanceof NodeAccessControlHandlerInterface) {
      throw new \RuntimeException('The node access control handler does not support acquiring grants.');
    }

    // @codeCoverageIgnoreEnd
    \Drupal::service('node.grant_storage')->write($node, $handler->acquireGrants($node));
  }

  /**
   * {@inheritdoc}
   */
  public function cronRun(): bool {
    $_SERVER['REQUEST_TIME'] = time();
    \Drupal::request()->server->set('REQUEST_TIME', $_SERVER['REQUEST_TIME']);
    return \Drupal::service('cron')->run();
  }

  /**
   * {@inheritdoc}
   *
   * The header is written to '$_SERVER' under the name PHP gives a request
   * header, such as 'HTTP_X_CONFIG_NO_OVERRIDE'.
   */
  public function requestHeaderSet(string $name, string $value): void {
    $_SERVER[static::getRequestHeaderVariable($name)] = $value;
  }

  /**
   * {@inheritdoc}
   */
  public function requestHeaderUnset(string $name): void {
    unset($_SERVER[static::getRequestHeaderVariable($name)]);
  }

  /**
   * Returns the name PHP gives the server variable of a request header.
   *
   * @param string $name
   *   The header name, such as 'X-Config-No-Override'.
   */
  protected static function getRequestHeaderVariable(string $name): string {
    return 'HTTP_' . strtoupper(str_replace('-', '_', $name));
  }

  /**
   * {@inheritdoc}
   */
  public function createUser(EntityStubInterface $stub): EntityStubInterface {
    if (!$stub->hasValue('status')) {
      $stub->setValue('status', 1);
    }

    $this->applyPreCreateAliases($stub, 'user');

    $this->expandEntityFields($stub);
    $account = \Drupal::entityTypeManager()->getStorage('user')->create($stub->getValues());
    $account->save();

    $stub->setValue('uid', $account->id());
    $stub->markSaved($account);

    $this->applyPostCreateAliases($stub, $account, 'user');

    return $stub;
  }

  /**
   * {@inheritdoc}
   *
   * A permission given by its label is granted by its machine name.
   */
  public function createRole(array $permissions, ?string $id = NULL, ?string $label = NULL): EntityStubInterface {
    $rid = $id ?? strtolower($this->random->name(8, TRUE));
    $role_label = $label ?? ($id ?? trim($this->random->name(8, TRUE)));

    $this->convertPermissions($permissions);
    $this->checkPermissions($permissions);

    /** @var \Drupal\user\RoleInterface $role */
    $role = \Drupal::entityTypeManager()->getStorage('user_role')->create([
      'id' => $rid,
      'label' => $role_label,
    ]);

    foreach ($permissions as $permission) {
      $role->grantPermission($permission);
    }

    $role->save();

    $stub = new EntityStub('user_role', NULL, ['id' => (string) $role->id(), 'label' => (string) $role->label()]);

    $stub->markSaved($role);

    return $stub;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteRole(string $role_name): void {
    $role = Role::load($role_name);

    if ($role) {
      $role->delete();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function processBatch(): void {
    $batch =& batch_get();

    if ($batch) {
      $batch['progressive'] = FALSE;
      batch_process();
    }
  }

  /**
   * Retrieves all permissions.
   *
   * @return array<string, mixed>
   *   Array of all defined permissions.
   */
  protected function getAllPermissions(): array {
    $this->allPermissions ??= \Drupal::service('user.permissions')->getPermissions();

    return $this->allPermissions;
  }

  /**
   * Converts any permission labels to machine names.
   *
   * @param array<string> &$permissions
   *   Array of permission names.
   */
  protected function convertPermissions(array &$permissions): void {
    $all_permissions = $this->getAllPermissions();

    foreach ($all_permissions as $name => $definition) {
      // Permission titles are TranslatableMarkup objects, which never
      // strictly equal the plain string labels supplied by callers.
      $key = array_search((string) $definition['title'], $permissions, TRUE);

      if ($key === FALSE) {
        continue;
      }

      $permissions[$key] = $name;
    }
  }

  /**
   * Checks that every permission in the array is valid.
   *
   * @param array<string> $permissions
   *   Permissions to check.
   */
  protected function checkPermissions(array &$permissions): void {
    $available = array_keys($this->getAllPermissions());

    foreach ($permissions as $permission) {
      if (in_array($permission, $available, TRUE)) {
        continue;
      }

      throw new \RuntimeException(sprintf('Invalid permission "%s".', $permission));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteUser(EntityStubInterface $stub): void {
    $uid = (int) $this->resolveUid($stub);

    if (!User::load($uid) instanceof User) {
      return;
    }

    // 'AccountCancellation' exists from Drupal 11.5, and 11.4 has only
    // 'user_cancel()'.
    if (class_exists(AccountCancellation::class)) {
      // @codeCoverageIgnoreStart
      \Drupal::service(AccountCancellation::class)->cancel([], $uid, 'user_cancel_delete');
      // @codeCoverageIgnoreEnd
    }
    else {
      user_cancel([], $uid, 'user_cancel_delete');
    }

    // Either call schedules the deletion via batch, so the batch runs to
    // completion here and the deletion is synchronous.
    $this->processBatch();
  }

  /**
   * {@inheritdoc}
   */
  public function addUserRole(EntityStubInterface $stub, string $role): void {
    $query = \Drupal::entityQuery('user_role');
    $conditions = $query->orConditionGroup()
      ->condition('id', $role)
      ->condition('label', $role);
    $rids = $query->condition($conditions)->execute();

    if (!$rids) {
      throw new \RuntimeException(sprintf('No role "%s" exists.', $role));
    }

    $account = $this->loadUser($stub);
    $account->addRole(reset($rids));
    $account->save();
  }

  /**
   * {@inheritdoc}
   */
  public function getOneTimeLoginUrl(EntityStubInterface $stub): string {
    $account = $this->loadUser($stub);
    $timestamp = \Drupal::time()->getRequestTime();

    return Url::fromRoute('user.reset', [
      'uid' => $account->id(),
      'timestamp' => $timestamp,
      'hash' => \Drupal::service(OneTimeAuthentication::class)->generateHmac($account, $timestamp),
    ])->toString();
  }

  /**
   * {@inheritdoc}
   */
  public function getUserRoles(EntityStubInterface $stub): array {
    return array_values($this->loadUser($stub)->getRoles());
  }

  /**
   * {@inheritdoc}
   */
  public function setUserPassword(EntityStubInterface $stub, string $password): void {
    $this->loadUser($stub)->setPassword($password)->save();
  }

  /**
   * {@inheritdoc}
   */
  public function setUserLastAccessTime(EntityStubInterface $stub, int $timestamp): void {
    $this->loadUser($stub)->setLastAccessTime($timestamp)->save();
  }

  /**
   * {@inheritdoc}
   */
  public function setUserLastLoginTime(EntityStubInterface $stub, int $timestamp): void {
    $this->loadUser($stub)->setLastLoginTime($timestamp)->save();
  }

  /**
   * Resolves the user id from a stub.
   *
   * The saved-entity slot is the only authoritative source after
   * 'createUser()', so it is read first. A 'uid' value the caller may have
   * populated manually is the fallback.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The user stub to read the id from.
   *
   * @return int|string
   *   The user id.
   *
   * @throws \RuntimeException
   *   Thrown when the stub carries no id. Uid 0 is the anonymous user, so a
   *   caller that acts on an unresolved stub acts on the wrong account.
   */
  protected function resolveUid(EntityStubInterface $stub): int|string {
    $uid = $stub->getId() ?? $stub->getValue('uid');

    if ($uid === NULL) {
      throw new \RuntimeException('Cannot resolve a user id from the stub: neither the saved entity nor a "uid" value is set.');
    }

    return $uid;
  }

  /**
   * Loads the account a stub points at.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The user stub to load the account for.
   *
   * @return \Drupal\user\Entity\User
   *   The loaded account.
   *
   * @throws \RuntimeException
   *   Thrown when the stub carries no id, or when the id it carries no
   *   longer resolves to an account.
   */
  protected function loadUser(EntityStubInterface $stub): User {
    $uid = $this->resolveUid($stub);
    $account = User::load($uid);

    if (!$account instanceof User) {
      throw new \RuntimeException(sprintf('No user with id "%s" exists.', $uid));
    }

    return $account;
  }

  /**
   * {@inheritdoc}
   *
   * Runs only during an in-process Drupal boot, which the kernel test
   * framework cannot exercise end-to-end, so coverage is not measurable.
   *
   * @codeCoverageIgnore
   */
  public function validateDrupalSite(): void {
    if ('default' !== $this->uri) {
      // The HTTP headers Drupal needs are faked from the URI.
      $drupal_base_url = parse_url($this->uri);

      if ($drupal_base_url === FALSE) {
        throw new BootstrapException(sprintf('Cannot parse the site URI %s.', $this->uri));
      }

      $drupal_base_url += [
        'path' => NULL,
        'host' => NULL,
        'port' => NULL,
      ];
      $_SERVER['HTTP_HOST'] = $drupal_base_url['host'];

      if ($drupal_base_url['port']) {
        $_SERVER['HTTP_HOST'] .= ':' . $drupal_base_url['port'];
      }

      $_SERVER['SERVER_PORT'] = $drupal_base_url['port'];

      if (array_key_exists('path', $drupal_base_url)) {
        $_SERVER['PHP_SELF'] = $drupal_base_url['path'] . '/index.php';
      }
      else {
        $_SERVER['PHP_SELF'] = '/index.php';
      }
    }
    else {
      $_SERVER['HTTP_HOST'] = 'default';
      $_SERVER['PHP_SELF'] = '/index.php';
    }

    $_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'];

    $conf_path = DrupalKernel::findSitePath(Request::createFromGlobals());
    $conf_file = $this->drupalRoot . sprintf('/%s/settings.php', $conf_path);

    if (!file_exists($conf_file)) {
      throw new BootstrapException(sprintf('Could not find a Drupal settings.php file at "%s".', $conf_file));
    }

    $drushrc_file = $this->drupalRoot . sprintf('/%s/drushrc.php', $conf_path);

    if (file_exists($drushrc_file)) {
      require_once $drushrc_file;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function createTerm(EntityStubInterface $stub): EntityStubInterface {
    $this->applyPreCreateAliases($stub, 'taxonomy_term');

    $vocabulary = $stub->getBundle() ?? $stub->getValue('vid');

    if (empty($vocabulary)) {
      throw new \RuntimeException("Cannot create term because the vocabulary is missing. Supply a bundle, a 'vid' value, or the 'vocabulary_machine_name' creation alias.");
    }

    if (Vocabulary::load($vocabulary) === NULL) {
      throw new \RuntimeException(sprintf('Cannot create term because vocabulary "%s" does not exist.', $vocabulary));
    }

    $stub->setValue('vid', $vocabulary);

    $this->expandEntityFields($stub);
    $entity = Term::create($stub->getValues());
    $entity->save();

    $stub->setValue('tid', $entity->id());
    $stub->markSaved($entity);

    $this->applyPostCreateAliases($stub, $entity, 'taxonomy_term');

    return $stub;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteTerm(EntityStubInterface $stub): void {
    $term = $stub->isSaved() ? $stub->getSavedEntity() : NULL;

    if (!$term instanceof TermInterface) {
      $tid = $stub->getValue('tid');

      if ($tid === NULL) {
        throw new \RuntimeException('Cannot resolve a term id from the stub: neither the saved entity nor a "tid" value is set.');
      }

      $term = Term::load($tid);
    }

    if ($term instanceof TermInterface) {
      $term->delete();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function placeBlock(string $plugin_id, string $label): EntityStubInterface {
    $values = ['plugin' => $plugin_id, 'theme' => $this->getDefaultTheme(), 'settings' => ['label' => $label]];

    /** @var \Drupal\block\BlockInterface $placement */
    $placement = \Drupal::entityTypeManager()->getStorage('block')->create($values);
    $values['id'] = \Drupal::service('block.repository')->getUniqueMachineName($placement->getPlugin()->getMachineNameSuggestion(), $values['theme']);
    $placement->set('id', $values['id']);
    $placement->save();

    return (new EntityStub('block', NULL, $values))->markSaved($placement);
  }

  /**
   * {@inheritdoc}
   */
  public function configureBlock(EntityStubInterface $stub, array $configuration): void {
    $block = $this->loadBlock($stub, TRUE);

    if (array_key_exists('label', $configuration) || array_key_exists('label_display', $configuration)) {
      $settings = $block->get('settings');
      $settings = is_array($settings) ? $settings : [];

      foreach (['label', 'label_display'] as $key) {
        if (array_key_exists($key, $configuration)) {
          $settings[$key] = $configuration[$key];
        }
      }

      $block->set('settings', $settings);
    }

    foreach (['region', 'status'] as $key) {
      if (array_key_exists($key, $configuration)) {
        $block->set($key, $configuration[$key]);
      }
    }

    $block->save();
  }

  /**
   * {@inheritdoc}
   */
  public function setBlockVisibility(EntityStubInterface $stub, string $condition, array $configuration): void {
    $block = $this->loadBlock($stub, TRUE);

    $configuration['id'] = $condition;
    $visibility = $block->get('visibility');
    $visibility = is_array($visibility) ? $visibility : [];
    $visibility[$condition] = $configuration;

    $block->set('visibility', $visibility)->save();
  }

  /**
   * {@inheritdoc}
   */
  public function unsetBlockVisibility(EntityStubInterface $stub, string $condition): void {
    $block = $this->loadBlock($stub, TRUE);

    $visibility = $block->get('visibility');
    $visibility = is_array($visibility) ? $visibility : [];

    if (!array_key_exists($condition, $visibility)) {
      return;
    }

    unset($visibility[$condition]);

    $block->set('visibility', $visibility)->save();
  }

  /**
   * {@inheritdoc}
   */
  public function getBlockRegion(EntityStubInterface $stub): string {
    return (string) $this->loadBlock($stub, FALSE)->getRegion();
  }

  /**
   * {@inheritdoc}
   */
  public function findBlockIdsByLabel(string $label): array {
    return $this->findEntityIds('block', ['theme' => $this->getDefaultTheme(), 'settings.label' => $label]);
  }

  /**
   * Loads the block a stub stands for.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   A 'block' stub carrying its 'id', or one returned by 'placeBlock()'.
   * @param bool $is_override_free
   *   Whether to load the stored values rather than the overridden ones, so
   *   a save writes no override into the stored configuration.
   *
   * @throws \RuntimeException
   *   When the stub carries no id, or the id names no block.
   */
  protected function loadBlock(EntityStubInterface $stub, bool $is_override_free): BlockInterface {
    $storage = $this->getConfigEntityStorage('block');
    $id = $this->resolveStubId($stub);
    $block = $is_override_free ? $storage->loadOverrideFree($id) : $storage->load($id);

    if (!$block instanceof BlockInterface) {
      throw new \RuntimeException(sprintf('No block with the id "%s" exists.', $id));
    }

    return $block;
  }

  /**
   * Returns the machine name of the default theme.
   */
  protected function getDefaultTheme(): string {
    return (string) \Drupal::config('system.theme')->get('default');
  }

  /**
   * {@inheritdoc}
   */
  public function deleteBlock(EntityStubInterface $stub): void {
    $entity = $stub->isSaved() ? $stub->getSavedEntity() : NULL;

    if (!$entity instanceof EntityInterface) {
      $id = $stub->getValue('id');

      if (!is_string($id) || $id === '') {
        throw new \RuntimeException('Cannot delete a block placement from a stub without a string "id" property.');
      }

      $entity = \Drupal::entityTypeManager()->getStorage('block')->load($id);
    }

    if ($entity instanceof EntityInterface) {
      $entity->delete();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function createBlockContent(EntityStubInterface $stub): EntityStubInterface {
    return $this->createEntity($stub);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteBlockContent(EntityStubInterface $stub): void {
    $this->deleteEntity($stub);
  }

  /**
   * {@inheritdoc}
   */
  public function findBlockPluginId(string $admin_label): ?string {
    /** @var \Drupal\Core\Block\BlockManagerInterface $block_manager */
    $block_manager = \Drupal::service('plugin.manager.block');

    foreach ($block_manager->getDefinitions() as $plugin_id => $definition) {
      if ((string) $definition['admin_label'] === $admin_label) {
        return (string) $plugin_id;
      }
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function hasConditionPlugin(string $id): bool {
    return \Drupal::service('plugin.manager.condition')->hasDefinition($id);
  }

  /**
   * {@inheritdoc}
   */
  public function getModuleList(): array {
    return array_keys(\Drupal::moduleHandler()->getModuleList());
  }

  /**
   * {@inheritdoc}
   */
  public function getExtensionPathList(): array {
    $paths = [];

    foreach (\Drupal::moduleHandler()->getModuleList() as $module) {
      $paths[] = $this->drupalRoot . '/' . $module->getPath();
    }

    return $paths;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityFieldTypes(string $entity_type, ?string $bundle = NULL): array {
    $entity_field_manager = $this->getEntityFieldManager();
    $bundle_fields = [];
    $fields = $entity_field_manager->getFieldStorageDefinitions($entity_type)
      + $entity_field_manager->getBaseFieldDefinitions($entity_type);

    if ($bundle !== NULL) {
      $bundle_fields = $entity_field_manager->getFieldDefinitions($entity_type, $bundle);
      $fields += $bundle_fields;
    }

    $types = [];

    foreach ($fields as $field_name => $field) {
      // Only F1, F5 and F9 enter the expansion pipeline (see
      // src/Backend/Drupal/Core/Field/README.md), and F5 is scoped to the bundle
      // when known. Without that scope, a configurable field storage
      // attached only to other bundles would enter the type map and fail in
      // AbstractHandler::__construct().
      $is_base_standard = $this->getFieldClassifier()->fieldIsBaseStandard($entity_type, $field_name);
      $is_configurable = $this->getFieldClassifier()->fieldIsConfigurable($entity_type, $field_name)
        && ($bundle === NULL || isset($bundle_fields[$field_name]));
      $is_bundle_storage_backed = $bundle !== NULL
        && $this->getFieldClassifier()->fieldIsBundleStorageBacked($entity_type, $field_name, $bundle);

      if (!$is_base_standard && !$is_configurable && !$is_bundle_storage_backed) {
        continue;
      }

      $types[$field_name] = $field->getType();
    }

    return $types;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityIdKey(string $entity_type): string {
    $id_key = $this->loadEntityTypeDefinition($entity_type)->getKey('id');

    if (!is_string($id_key) || $id_key === '') {
      throw new \RuntimeException(sprintf('The entity type "%s" declares no id key.', $entity_type));
    }

    return $id_key;
  }

  /**
   * {@inheritdoc}
   */
  public function bundleExists(string $entity_type, string $bundle): bool {
    return array_key_exists($bundle, $this->getBundleInfo($entity_type));
  }

  /**
   * {@inheritdoc}
   */
  public function getBundleLabel(string $entity_type, string $bundle): string {
    $bundles = $this->getBundleInfo($entity_type);

    if (!array_key_exists($bundle, $bundles)) {
      throw new \RuntimeException(sprintf('The entity type "%s" has no bundle "%s".', $entity_type, $bundle));
    }

    return (string) ($bundles[$bundle]['label'] ?? $bundle);
  }

  /**
   * Returns the bundle info of an entity type, keyed by bundle.
   *
   * @param string $entity_type
   *   The entity type id.
   *
   * @return array<string, array<string, mixed>>
   *   The info of each bundle, with its 'label'. An entity type without
   *   bundles has 1, keyed by the entity type id.
   *
   * @throws \RuntimeException
   *   When the entity type is unknown.
   */
  protected function getBundleInfo(string $entity_type): array {
    $this->loadEntityTypeDefinition($entity_type);

    /** @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundle_info */
    $bundle_info = \Drupal::service('entity_type.bundle.info');

    return $bundle_info->getBundleInfo($entity_type);
  }

  /**
   * {@inheritdoc}
   */
  public function parseEntityFields(EntityStubInterface $stub, array $ignored_properties = []): void {
    $parser = $this->createEntityFieldParser($stub->getEntityType(), $stub->getBundle());
    $parser->ignoring($ignored_properties);

    $stub->setValues($parser->parse($stub->getValues()));
  }

  /**
   * Creates the parser that shapes a stub's raw values for 1 parse.
   *
   * @param string $entity_type
   *   The entity type the values belong to.
   * @param string|null $bundle
   *   The bundle, or NULL for an entity type without bundles.
   */
  protected function createEntityFieldParser(string $entity_type, ?string $bundle): EntityFieldParserInterface {
    return new EntityFieldParser($entity_type, $this->getFieldClassifier(), $bundle);
  }

  /**
   * Returns the entity field manager service.
   *
   * @return \Drupal\Core\Entity\EntityFieldManagerInterface
   *   The entity field manager.
   */
  protected function getEntityFieldManager(): EntityFieldManagerInterface {
    return \Drupal::service('entity_field.manager');
  }

  /**
   * {@inheritdoc}
   */
  public function createLanguage(EntityStubInterface $stub): EntityStubInterface {
    $langcode = $this->resolveLangcode($stub);

    if (ConfigurableLanguage::load($langcode)) {
      return $stub;
    }

    $entity = ConfigurableLanguage::createFromLangcode($langcode);
    $entity->save();
    $stub->markSaved($entity);

    return $stub;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteLanguage(EntityStubInterface $stub): void {
    $configurable_language = ConfigurableLanguage::load($this->resolveLangcode($stub));

    if ($configurable_language instanceof ConfigurableLanguage) {
      $configurable_language->delete();
    }
  }

  /**
   * Returns a non-empty langcode string from the stub or throws.
   */
  protected function resolveLangcode(EntityStubInterface $stub): string {
    $langcode = $stub->getValue('langcode');

    if (!is_string($langcode) || $langcode === '') {
      throw new \RuntimeException('Cannot operate on a language without a non-empty "langcode" value.');
    }

    return $langcode;
  }

  /**
   * {@inheritdoc}
   */
  public function cacheClearStatic(): void {
    // Drupal 11.4 keeps its statics in 'drupal_static()' and Drupal 12 keeps
    // several of core's in the 'memory' bin, so both are cleared.
    drupal_static_reset();
    \Drupal::cache('memory')->deleteAll();
    // A config object or an entity loaded in this process stays as loaded
    // until its own cache is reset. Until then, a change made by another
    // process is invisible.
    \Drupal::configFactory()->reset();
    \Drupal::service('entity.memory_cache')->deleteAll();
    $this->allPermissions = NULL;
    \Drupal::service('cache_tags.invalidator')->resetChecksums();

    foreach (\Drupal::entityTypeManager()->getDefinitions() as $definition) {
      \Drupal::entityTypeManager()->getAccessControlHandler($definition->id())->resetCache();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function cacheClearBin(string $bin): void {
    $this->getCacheBin($bin)->deleteAll();
  }

  /**
   * {@inheritdoc}
   */
  public function cacheInvalidateTags(array $tags): void {
    if ($tags === []) {
      return;
    }

    \Drupal::service('cache_tags.invalidator')->invalidateTags($tags);
  }

  /**
   * {@inheritdoc}
   */
  public function cacheDeletePagePath(string $bin, string $path, bool $is_pattern = FALSE): void {
    $backend = $this->getCacheBin($bin);

    if (!$backend instanceof DatabaseBackend) {
      $backend->deleteAll();

      return;
    }

    $table = 'cache_' . $bin;
    $database = \Drupal::database();

    // The database backend creates its table on the first write, so a missing
    // table holds no entries.
    if (!$database->schema()->tableExists($table)) {
      return;
    }

    $parts = $is_pattern ? explode('*', $path) : [$path];

    // LIKE cannot anchor the path after the host, so it only narrows the
    // candidates.
    $like = implode('%', array_map($database->escapeLike(...), $parts));
    $candidates = $database->select($table, 'c')->fields('c', ['cid'])->condition('cid', '%' . $like . '%', 'LIKE')->execute()->fetchCol();

    $regex = '#^' . implode('.*', array_map(static fn(string $part): string => preg_quote($part, '#'), $parts)) . '$#';
    $cids = array_filter($candidates, fn(string $cid): bool => preg_match($regex, $this->findPageCachePath($cid) ?? '') === 1);

    // Chunk the IN list to stay under the database placeholder limit.
    foreach (array_chunk($cids, 1000) as $chunk) {
      $database->delete($table)->condition('cid', $chunk, 'IN')->execute();
    }
  }

  /**
   * Returns a cache bin.
   *
   * @param string $bin
   *   The bin name.
   *
   * @throws \RuntimeException
   *   When the bin does not exist.
   */
  protected function getCacheBin(string $bin): CacheBackendInterface {
    if (!\Drupal::hasService('cache.' . $bin)) {
      throw new \RuntimeException(sprintf('The cache bin "%s" does not exist.', $bin));
    }

    return \Drupal::cache($bin);
  }

  /**
   * Finds the path of the URL an internal page cache entry is stored for.
   *
   * @param string $cid
   *   The cache ID: the absolute URL, ':' and the request format.
   *
   * @return string|null
   *   The path, or NULL when the cache ID holds no URL path.
   */
  protected function findPageCachePath(string $cid): ?string {
    $url = parse_url($cid);

    if (!is_array($url) || !isset($url['path'])) {
      return NULL;
    }

    // With a query string, parse_url() returns the ':<format>' suffix inside
    // the query.
    if (isset($url['query'])) {
      return $url['path'];
    }

    // The format contains no ':', so the last ':' in the path starts it.
    $separator = strrpos($url['path'], ':');

    return $separator === FALSE ? $url['path'] : substr($url['path'], 0, $separator);
  }

  /**
   * {@inheritdoc}
   */
  public function configGet(string $name, ?string $key = NULL): mixed {
    return \Drupal::config($name)->get($key ?? '');
  }

  /**
   * {@inheritdoc}
   */
  public function configGetOriginal(string $name, ?string $key = NULL): mixed {
    return \Drupal::config($name)->getOriginal($key ?? '', FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function configSet(string $name, string $key, mixed $value): void {
    \Drupal::configFactory()->getEditable($name)
      ->set($key, $value)
      ->save();
  }

  /**
   * {@inheritdoc}
   */
  public function configExists(string $name): bool {
    return !\Drupal::configFactory()->getEditable($name)->isNew();
  }

  /**
   * {@inheritdoc}
   */
  public function configGetData(string $name): array {
    return \Drupal::configFactory()->getEditable($name)->getRawData();
  }

  /**
   * {@inheritdoc}
   */
  public function configSetData(string $name, array $data): void {
    \Drupal::configFactory()->getEditable($name)
      ->setData($data)
      ->save();
  }

  /**
   * {@inheritdoc}
   */
  public function configDelete(string $name): void {
    \Drupal::configFactory()->getEditable($name)->delete();
  }

  /**
   * {@inheritdoc}
   */
  public function stateGet(string $name): mixed {
    return \Drupal::state()->get($name);
  }

  /**
   * {@inheritdoc}
   */
  public function stateSet(string $name, mixed $value): void {
    \Drupal::state()->set($name, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function stateDelete(string $name): void {
    \Drupal::state()->delete($name);
  }

  /**
   * {@inheritdoc}
   */
  public function stateExists(string $name): bool {
    // The state service reads a stored NULL and an absent key alike, so the
    // backing key-value store is queried instead.
    return \Drupal::keyValue('state')->has($name);
  }

  /**
   * {@inheritdoc}
   */
  public function createEntity(EntityStubInterface $stub): EntityStubInterface {
    $entity_type = $stub->getEntityType();

    if ($entity_type === '') {
      throw new \RuntimeException('You must specify an entity type to create an entity.');
    }

    $definition = $this->loadEntityTypeDefinition($entity_type);
    $bundle_key = $definition->getKey('bundle');
    $id_key = $definition->getKey('id');

    if (!is_string($id_key)) {
      throw new \RuntimeException(sprintf('Cannot create an entity of type "%s" because it declares no id key.', $entity_type));
    }

    if ($bundle_key && !$stub->hasValue($bundle_key) && $stub->getBundle() !== NULL) {
      $stub->setValue($bundle_key, $stub->getBundle());
    }

    if ($bundle_key && $stub->hasValue($bundle_key) && $stub->getValue($bundle_key) !== NULL && !$this->bundleExists($entity_type, (string) $stub->getValue($bundle_key))) {
      throw new \RuntimeException(sprintf('Cannot create entity because provided bundle "%s" does not exist.', $stub->getValue($bundle_key)));
    }

    $this->expandEntityFields($stub);
    $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->create($stub->getValues());
    $entity->save();

    $stub->setValue($id_key, $entity->id());
    $stub->markSaved($entity);

    return $stub;
  }

  /**
   * {@inheritdoc}
   *
   * The entity is loaded again rather than taken from a saved stub, so a
   * change made since the stub was saved is kept.
   */
  public function updateEntity(EntityStubInterface $stub): EntityStubInterface {
    $entity_type = $stub->getEntityType();
    $definition = $this->loadEntityTypeDefinition($entity_type);

    if (!$definition->entityClassImplements(FieldableEntityInterface::class)) {
      throw new \RuntimeException(sprintf('Cannot update an entity of type "%s" because it is not a content entity type.', $entity_type));
    }

    $id = $this->resolveStubId($stub);
    $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($id);

    if (!$entity instanceof FieldableEntityInterface) {
      throw new \RuntimeException(sprintf('Cannot update the entity of type "%s" with the id "%s" because it does not exist.', $entity_type, $id));
    }

    $identifying_keys = [$definition->getKey('id'), $definition->getKey('bundle')];
    $values = array_diff_key($stub->getValues(), array_flip(array_filter($identifying_keys, is_string(...))));

    // Every field is checked before any is set, so a typo in 1 value leaves
    // the entity unchanged.
    foreach (array_keys($values) as $field_name) {
      if (!$entity->hasField($field_name)) {
        throw new \RuntimeException(sprintf('Cannot update the entity of type "%s" with the id "%s" because it has no field "%s".', $entity_type, $id, $field_name));
      }
    }

    // The stub need not name a bundle, so the values expand against the
    // bundle of the loaded entity.
    $expanded = new EntityStub($entity_type, $entity->bundle(), $values);
    $this->expandEntityFields($expanded);

    foreach ($expanded->getValues() as $field_name => $value) {
      $entity->set($field_name, $value);
      $stub->setValue($field_name, $value);
    }

    $entity->save();
    $stub->markSaved($entity);

    return $stub;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityValues(string $entity_type, int|string $id, array $fields): array {
    if (!$this->loadEntityTypeDefinition($entity_type)->entityClassImplements(FieldableEntityInterface::class)) {
      throw new \RuntimeException(sprintf('Cannot read the values of an entity of type "%s" because it is not a content entity type.', $entity_type));
    }

    $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($id);

    if (!$entity instanceof FieldableEntityInterface) {
      return [];
    }

    $values = [];

    foreach ($fields as $field_name) {
      if (!$entity->hasField($field_name)) {
        throw new \RuntimeException(sprintf('Cannot read the entity of type "%s" with the id "%s" because it has no field "%s".', $entity_type, $id, $field_name));
      }

      $values[$field_name] = $entity->get($field_name)->getValue();
    }

    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteEntity(EntityStubInterface $stub): void {
    $entity = $this->findStubEntity($stub);

    if ($entity instanceof EntityInterface) {
      $entity->delete();
    }
  }

  /**
   * {@inheritdoc}
   *
   * The path is the route's own, with no base path and no path alias. A caller
   * adds the base path when it prefixes the site's URL, and this process may
   * hold a stale copy of the alias.
   */
  public function getEntityUrl(EntityStubInterface $stub, string $rel = 'canonical'): string {
    $entity = $this->getStubEntity($stub);

    if (!$entity->hasLinkTemplate($rel)) {
      throw new \RuntimeException(sprintf('The entity type "%s" has no "%s" link.', $entity->getEntityTypeId(), $rel));
    }

    return '/' . $entity->toUrl($rel)->getInternalPath();
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityBundle(EntityStubInterface $stub): string {
    return $this->getStubEntity($stub)->bundle();
  }

  /**
   * {@inheritdoc}
   */
  public function setEntityPathAlias(EntityStubInterface $stub, string $alias): void {
    $path = ltrim(trim($alias), '/');

    if ($path === '') {
      throw new \RuntimeException(sprintf('Cannot set the path alias "%s" because it names no path.', $alias));
    }

    $entity_type = $stub->getEntityType();
    $id = $this->resolveStubId($stub);

    // The current value carries the 'pid' of the existing alias, so the save
    // changes that alias rather than adding a second one.
    $value = (array) ($this->getEntityValues($entity_type, $id, ['path'])['path'][0] ?? []);
    $value['alias'] = '/' . $path;

    // 0 is 'PathautoState::SKIP', so pathauto does not regenerate the alias
    // on save.
    if (\Drupal::moduleHandler()->moduleExists('pathauto')) {
      $value['pathauto'] = 0;
    }

    $values = [$this->getEntityIdKey($entity_type) => $id, 'path' => $value];

    $this->updateEntity(new EntityStub($entity_type, NULL, $values));
  }

  /**
   * {@inheritdoc}
   */
  public function getMenuLinkPluginId(EntityStubInterface $stub): string {
    $link = $this->findStubEntity($stub);

    if (!$link instanceof MenuLinkContentInterface) {
      throw new \RuntimeException(sprintf('No menu link with the id "%s" exists.', $this->resolveStubId($stub)));
    }

    return $link->getPluginId();
  }

  /**
   * {@inheritdoc}
   */
  public function createRedirect(string $source, string $destination, int $status_code = 301): EntityStubInterface {
    $values = [
      'redirect_source' => $this->normalizeRedirectSource($source),
      'redirect_redirect' => $this->normalizeRedirectDestination($destination),
      'status_code' => $status_code,
    ];

    return $this->createEntity(new EntityStub('redirect', NULL, $values));
  }

  /**
   * {@inheritdoc}
   */
  public function deleteRedirect(EntityStubInterface $stub): void {
    $this->deleteEntity($stub);
  }

  /**
   * {@inheritdoc}
   */
  public function findRedirectIds(string $source, ?string $destination = NULL, ?int $status_code = NULL): array {
    $conditions = ['redirect_source.path' => $this->normalizeRedirectSource($source)];

    if ($destination !== NULL) {
      $conditions['redirect_redirect.uri'] = $this->normalizeRedirectDestination($destination);
    }

    if ($status_code !== NULL) {
      $conditions['status_code'] = $status_code;
    }

    return $this->findEntityIds('redirect', $conditions);
  }

  /**
   * Normalizes a source path the way 'Redirect::setSource()' stores it.
   *
   * @param string $path
   *   The source path, with or without its leading slash.
   *
   * @return string
   *   The path without its leading slash.
   */
  protected function normalizeRedirectSource(string $path): string {
    return ltrim(trim($path), '/');
  }

  /**
   * Normalizes a destination the way 'Redirect::setRedirect()' stores it.
   *
   * An internal path gains the 'internal:/' scheme. A destination with a URL
   * scheme, or opening with '//', is external and passes through unchanged,
   * as does one already under the 'internal:' scheme.
   *
   * @param string $uri
   *   The destination.
   *
   * @return string
   *   The URI the redirect stores.
   */
  protected function normalizeRedirectDestination(string $uri): string {
    if (str_starts_with($uri, 'internal:')) {
      return $uri;
    }

    $is_external = str_starts_with($uri, '//') || parse_url($uri, PHP_URL_SCHEME) !== NULL;

    return $is_external ? $uri : 'internal:/' . ltrim($uri, '/');
  }

  /**
   * Returns the entity a stub stands for.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   A stub holding the saved entity, or the entity type's id key.
   *
   * @throws \RuntimeException
   *   When the stub holds no saved entity and no usable id, or no entity has
   *   the stub's id.
   */
  protected function getStubEntity(EntityStubInterface $stub): EntityInterface {
    $entity = $this->findStubEntity($stub);

    if (!$entity instanceof EntityInterface) {
      throw new \RuntimeException(sprintf('No entity of type "%s" with the id "%s" exists.', $stub->getEntityType(), $this->resolveStubId($stub)));
    }

    return $entity;
  }

  /**
   * Finds the entity a stub stands for.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   A stub holding the saved entity, or the entity type's id key.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The entity, or NULL when no entity has the stub's id.
   *
   * @throws \RuntimeException
   *   When the stub holds no saved entity and no usable id.
   */
  protected function findStubEntity(EntityStubInterface $stub): ?EntityInterface {
    $entity = $stub->isSaved() ? $stub->getSavedEntity() : NULL;

    if ($entity instanceof EntityInterface) {
      return $entity;
    }

    $id = $this->resolveStubId($stub);

    return \Drupal::entityTypeManager()->getStorage($stub->getEntityType())->load($id);
  }

  /**
   * Reads the id a stub carries under its entity type's id key.
   *
   * Falls back to the id of the saved entity when the stub has no value under
   * the id key.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStubInterface $stub
   *   The stub.
   *
   * @return int|string
   *   The id.
   *
   * @throws \RuntimeException
   *   When the stub holds no usable value under the id key and no saved
   *   entity with an id.
   */
  protected function resolveStubId(EntityStubInterface $stub): int|string {
    $entity_type = $stub->getEntityType();
    $id_key = (string) $this->loadEntityTypeDefinition($entity_type)->getKey('id');
    $has_id_value = $id_key !== '' && $stub->hasValue($id_key);

    if (!$has_id_value && $stub->getId() === NULL) {
      throw new \RuntimeException(sprintf('Cannot resolve an entity of type "%s" from a stub without the id key "%s" set.', $entity_type, $id_key));
    }

    $id = $has_id_value ? $stub->getValue($id_key) : $stub->getId();

    if ((!is_int($id) && !is_string($id)) || $id === '') {
      throw new \RuntimeException(sprintf('Cannot resolve an entity of type "%s" from a stub with an empty id key "%s".', $entity_type, $id_key));
    }

    return $id;
  }

  /**
   * {@inheritdoc}
   */
  public function createConfigEntity(EntityStubInterface $stub): EntityStubInterface {
    $storage = $this->getConfigEntityStorage($stub->getEntityType());

    $entity = $storage->create($stub->getValues());
    $entity->save();

    $stub->setValue((string) $storage->getEntityType()->getKey('id'), $entity->id());
    $stub->markSaved($entity);

    return $stub;
  }

  /**
   * {@inheritdoc}
   */
  public function updateConfigEntity(EntityStubInterface $stub): EntityStubInterface {
    $entity_type = $stub->getEntityType();
    $storage = $this->getConfigEntityStorage($entity_type);
    $id_key = (string) $storage->getEntityType()->getKey('id');

    $id = $this->resolveStubId($stub);
    $entity = $storage->loadOverrideFree($id);

    if (!$entity instanceof ConfigEntityInterface) {
      throw new \RuntimeException(sprintf('Cannot update the entity of type "%s" with the id "%s" because it does not exist.', $entity_type, $id));
    }

    foreach ($stub->getValues() as $key => $value) {
      if ($key !== $id_key) {
        $entity->set($key, $value);
      }
    }

    $entity->save();
    $stub->markSaved($entity);

    return $stub;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteConfigEntity(EntityStubInterface $stub): void {
    $storage = $this->getConfigEntityStorage($stub->getEntityType());
    $id = $this->resolveStubId($stub);
    $entity = $storage->load($id);

    if ($entity instanceof EntityInterface) {
      $entity->delete();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function duplicateConfigEntity(EntityStubInterface $source, array $overrides): EntityStubInterface {
    $entity_type = $source->getEntityType();
    $storage = $this->getConfigEntityStorage($entity_type);
    $id_key = (string) $storage->getEntityType()->getKey('id');

    $id = $this->resolveStubId($source);
    $new_id = $overrides[$id_key] ?? NULL;

    if ((!is_int($new_id) && !is_string($new_id)) || $new_id === '') {
      throw new \RuntimeException(sprintf('Cannot duplicate the entity of type "%s" with the id "%s" without a new id under "%s".', $entity_type, $id, $id_key));
    }

    $original = $storage->loadOverrideFree($id);

    if (!$original instanceof ConfigEntityInterface) {
      throw new \RuntimeException(sprintf('Cannot duplicate the entity of type "%s" with the id "%s" because it does not exist.', $entity_type, $id));
    }

    $copy = $original->createDuplicate();

    foreach ($overrides as $key => $value) {
      $copy->set($key, $value);
    }

    $copy->save();

    return (new EntityStub($entity_type, NULL, $overrides))->markSaved($copy);
  }

  /**
   * Returns the storage of a config entity type.
   *
   * @param string $entity_type
   *   The entity type id.
   *
   * @return \Drupal\Core\Config\Entity\ConfigEntityStorageInterface
   *   The storage.
   *
   * @throws \RuntimeException
   *   When the entity type is unknown, or is not a config entity type.
   */
  protected function getConfigEntityStorage(string $entity_type): ConfigEntityStorageInterface {
    $this->loadEntityTypeDefinition($entity_type);

    $storage = \Drupal::entityTypeManager()->getStorage($entity_type);

    if (!$storage instanceof ConfigEntityStorageInterface) {
      throw new \RuntimeException(sprintf('Entity type "%s" is not a config entity type.', $entity_type));
    }

    return $storage;
  }

  /**
   * {@inheritdoc}
   */
  public function findEntityIds(string $entity_type, array $conditions = [], ?string $bundle = NULL): array {
    $definition = $this->loadEntityTypeDefinition($entity_type);
    $bundle_key = $bundle === NULL ? NULL : $this->resolveBundleKey($definition, $entity_type);

    $resolved = [];

    foreach ($conditions as $field => $value) {
      $resolved[$field] = $this->resolveLookupCondition($field, $value);
    }

    $query = \Drupal::entityTypeManager()->getStorage($entity_type)->getQuery()->accessCheck(FALSE);

    if ($bundle_key !== NULL) {
      $query->condition($bundle_key, $bundle);
    }

    foreach ($resolved as $field => [$operator, $operand]) {
      $query->condition($field, $operand, $operator);
    }

    $ids = array_map(strval(...), array_values($query->execute()));

    // Natural order puts '10' after '9', so the last id is the newest entity's.
    usort($ids, strnatcmp(...));

    return $ids;
  }

  /**
   * Returns the key an entity type stores its bundle under.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $definition
   *   The entity type definition.
   * @param string $entity_type
   *   The entity type id, for the exception message.
   *
   * @return string
   *   The bundle key, such as 'type' for a node or 'vid' for a term.
   *
   * @throws \RuntimeException
   *   When the entity type declares no bundle key.
   */
  protected function resolveBundleKey(EntityTypeInterface $definition, string $entity_type): string {
    $bundle_key = $definition->getKey('bundle');

    if (!is_string($bundle_key) || $bundle_key === '') {
      throw new \RuntimeException(sprintf('Entity type "%s" declares no bundle key, so it cannot be looked up by bundle.', $entity_type));
    }

    return $bundle_key;
  }

  /**
   * Splits a lookup condition value into its operator and operand.
   *
   * @param string $field
   *   The field name the condition is on, for the exception message.
   * @param mixed $value
   *   A scalar, matched with '=', or an [operator, value] pair.
   *
   * @return array{string, mixed}
   *   The operator and the operand.
   *
   * @throws \RuntimeException
   *   When the value is an array that is not an [operator, value] pair, the
   *   operator is not one of 'LOOKUP_OPERATORS', or the operand does not fit
   *   the operator.
   */
  protected function resolveLookupCondition(string $field, mixed $value): array {
    if (!is_array($value)) {
      return ['=', $value];
    }

    if (!array_is_list($value) || count($value) !== 2 || !is_string($value[0])) {
      throw new \RuntimeException(sprintf('The condition on "%s" is a value or an [operator, value] pair.', $field));
    }

    $operator = strtoupper($value[0]);

    if (!in_array($operator, static::LOOKUP_OPERATORS, TRUE)) {
      throw new \RuntimeException(sprintf('The condition on "%s" names the operator "%s", which is not one of %s.', $field, $value[0], implode(', ', static::LOOKUP_OPERATORS)));
    }

    if ($operator === 'IN' && (!is_array($value[1]) || $value[1] === [])) {
      throw new \RuntimeException(sprintf('The "IN" condition on "%s" takes a non-empty list of values.', $field));
    }

    if ($operator !== 'IN' && is_array($value[1])) {
      throw new \RuntimeException(sprintf('The "%s" condition on "%s" takes a single value.', $operator, $field));
    }

    return [$operator, $value[1]];
  }

  /**
   * {@inheritdoc}
   */
  public function moduleInstall(string $module_name): void {
    \Drupal::service('module_installer')->install([$module_name]);
    $this->allPermissions = NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function moduleUninstall(string $module_name): void {
    \Drupal::service('module_installer')->uninstall([$module_name]);
    $this->allPermissions = NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function moduleIsEnabled(string $module_name): bool {
    return \Drupal::moduleHandler()->moduleExists($module_name);
  }

  /**
   * {@inheritdoc}
   */
  public function moduleIsPresent(string $module_name): bool {
    return array_key_exists($module_name, \Drupal::service('extension.list.module')->getList());
  }

  /**
   * {@inheritdoc}
   *
   * A run that stopped before restoring a handler left the real handler in the
   * record and the collector in 'system.mail'. A type the record already holds
   * therefore keeps its recorded handler.
   *
   * A type that holds the collector with no record is recorded as it stands,
   * so the stop restores what the site held. The stop does not guess at what
   * another run replaced.
   */
  public function mailStartCollecting(array $handler_types = ['default']): void {
    if ($handler_types === []) {
      throw new \RuntimeException('Collecting mail takes at least 1 handler type.');
    }

    $mail = \Drupal::configFactory()->getEditable('system.mail');
    $original = \Drupal::configFactory()->getEditable(static::MAIL_ORIGINAL_CONFIG);
    $recorded = $original->get('interface');
    $recorded = is_array($recorded) ? $recorded : [];

    foreach ($handler_types as $type) {
      if (!array_key_exists($type, $recorded)) {
        $recorded[$type] = $mail->get('interface.' . $type);
      }

      $mail->set('interface.' . $type, static::MAIL_COLLECTOR);
    }

    $original->set('interface', $recorded)->save();
    $mail->save();

    $this->startCollectingSystemMail($original);
  }

  /**
   * {@inheritdoc}
   */
  public function mailStopCollecting(): void {
    $original = \Drupal::configFactory()->getEditable(static::MAIL_ORIGINAL_CONFIG);

    if ($original->isNew()) {
      return;
    }

    $mail = \Drupal::configFactory()->getEditable('system.mail');
    $recorded = $original->get('interface');

    foreach (is_array($recorded) ? $recorded : [] as $type => $handler) {
      $this->restoreConfigKey($mail, 'interface.' . $type, $handler);
    }

    $mail->save();

    $this->stopCollectingSystemMail($original);
    $original->delete();
  }

  /**
   * {@inheritdoc}
   */
  public function mailGet(): array {
    \Drupal::state()->resetCache();
    $mail = \Drupal::state()->get('system.test_mail_collector');

    return is_array($mail) ? array_values($mail) : [];
  }

  /**
   * {@inheritdoc}
   */
  public function mailClear(): void {
    \Drupal::state()->set('system.test_mail_collector', []);
  }

  /**
   * {@inheritdoc}
   */
  public function mailSend(string $body, string $subject, string $to, string $langcode, array $attachments = []): bool {
    // The mail is sent via the system module's hook_mail().
    $params['context']['message'] = $body;
    $params['context']['subject'] = $subject;

    if ($attachments !== []) {
      $params['attachments'] = $attachments;
    }

    $mail_manager = \Drupal::service('plugin.manager.mail');
    $result = $mail_manager->mail('system', '', $to, $langcode, $params, NULL, TRUE);
    return !empty($result['result']);
  }

  /**
   * Collects mail from the Mail System module too, when it is enabled.
   *
   * The module's default sender is recorded beside the handlers, so
   * 'mailStopCollecting()' restores it.
   *
   * @param \Drupal\Core\Config\Config $original
   *   The editable record of what the collector replaced.
   */
  protected function startCollectingSystemMail(Config $original): void {
    if (!\Drupal::moduleHandler()->moduleExists('mailsystem')) {
      return;
    }

    $settings = \Drupal::configFactory()->getEditable('mailsystem.settings');

    if (!array_key_exists('mailsystem', $original->getRawData())) {
      $original->set('mailsystem', ['sender' => $settings->get('defaults.sender')])->save();
    }

    $settings->set('defaults.sender', static::MAIL_COLLECTOR)->save();
  }

  /**
   * Restores the Mail System module's default sender, when it was recorded.
   *
   * @param \Drupal\Core\Config\Config $original
   *   The editable record of what the collector replaced.
   */
  protected function stopCollectingSystemMail(Config $original): void {
    if (!\Drupal::moduleHandler()->moduleExists('mailsystem')) {
      return;
    }

    $recorded = $original->get('mailsystem');

    if (!is_array($recorded) || !array_key_exists('sender', $recorded)) {
      return;
    }

    $settings = \Drupal::configFactory()->getEditable('mailsystem.settings');
    $this->restoreConfigKey($settings, 'defaults.sender', $recorded['sender']);
    $settings->save();
  }

  /**
   * Writes a recorded value back to a config key without saving.
   *
   * @param \Drupal\Core\Config\Config $config
   *   The editable config object.
   * @param string $key
   *   The key to restore.
   * @param mixed $value
   *   The recorded value. NULL records a key the site did not have, so the
   *   key is cleared rather than set.
   */
  protected function restoreConfigKey(Config $config, string $key, mixed $value): void {
    if ($value === NULL) {
      $config->clear($key);

      return;
    }

    $config->set($key, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function queueAddItem(string $queue, mixed $data): void {
    \Drupal::queue($queue)->createItem($data);
  }

  /**
   * {@inheritdoc}
   */
  public function queueCountItems(string $queue): int {
    return \Drupal::queue($queue)->numberOfItems();
  }

  /**
   * {@inheritdoc}
   */
  public function queueDelete(string $queue): void {
    \Drupal::queue($queue)->deleteQueue();
  }

  /**
   * {@inheritdoc}
   *
   * An exception a worker throws propagates, leaving the item claimed.
   */
  public function queueProcess(string $queue, int $limit, int $lease_time): int {
    try {
      $worker = \Drupal::service('plugin.manager.queue_worker')->createInstance($queue);
    }
    catch (PluginNotFoundException $exception) {
      throw new \RuntimeException(sprintf('No queue worker processes the queue "%s".', $queue), 0, $exception);
    }

    $queue_instance = \Drupal::queue($queue);
    $processed = 0;

    while ($processed < $limit) {
      /** @var \stdClass|false $item */
      $item = $queue_instance->claimItem($lease_time);

      if (!$item) {
        break;
      }

      $worker->processItem($item->data);
      $queue_instance->deleteItem($item);
      $processed++;
    }

    return $processed;
  }

  /**
   * {@inheritdoc}
   */
  public function logGetLastId(): int {
    $query = \Drupal::database()->select('watchdog', 'w');
    $query->addExpression('MAX(w.wid)', 'wid');
    $wid = $query->execute()->fetchField();

    return is_numeric($wid) ? (int) $wid : 0;
  }

  /**
   * {@inheritdoc}
   */
  public function logRead(array $types, int $after_id, int $severity): array {
    if ($types === []) {
      return [];
    }

    $rows = \Drupal::database()->select('watchdog', 'w')
      ->fields('w')
      ->condition('w.type', $types, 'IN')
      ->condition('w.wid', $after_id, '>')
      ->condition('w.severity', $severity, '<=')
      ->orderBy('w.wid')
      ->execute()
      ->fetchAll(FetchAs::Associative);

    $entries = [];

    foreach ($rows as $row) {
      if (!is_array($row)) {
        continue;
      }

      $variables = is_string($row['variables'] ?? NULL) ? unserialize($row['variables'], ['allowed_classes' => FALSE]) : NULL;

      $row['wid'] = (int) ($row['wid'] ?? 0);
      $row['severity'] = (int) ($row['severity'] ?? static::SEVERITY_DEBUG);
      $row['variables'] = is_array($variables) ? $variables : [];

      $entries[] = $row;
    }

    return $entries;
  }

  /**
   * {@inheritdoc}
   */
  public function logDelete(array $ids): void {
    if ($ids === []) {
      return;
    }

    \Drupal::database()->delete('watchdog')->condition('wid', $ids, 'IN')->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function fileWriteUnmanaged(string $uri, string $content): void {
    $this->prepareFileDirectory($uri);

    if (@file_put_contents($uri, $content) === FALSE) {
      throw new \RuntimeException(sprintf('Unable to write the file "%s".', $uri));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function fileReadUnmanaged(string $uri): string {
    if (!$this->fileExistsUnmanaged($uri)) {
      throw new \RuntimeException(sprintf('The file "%s" does not exist.', $uri));
    }

    $content = @file_get_contents($uri);

    // @codeCoverageIgnoreStart
    if ($content === FALSE) {
      throw new \RuntimeException(sprintf('Unable to read the file "%s".', $uri));
    }

    // @codeCoverageIgnoreEnd
    return $content;
  }

  /**
   * {@inheritdoc}
   */
  public function fileExistsUnmanaged(string $uri): bool {
    return @is_file($uri);
  }

  /**
   * {@inheritdoc}
   */
  public function fileDeleteUnmanaged(string $uri): void {
    if (!$this->fileExistsUnmanaged($uri)) {
      return;
    }

    $this->getFileSystem()->delete($uri);
  }

  /**
   * {@inheritdoc}
   */
  public function filePrepareDirectory(string $uri): bool {
    $scheme = StreamWrapperManager::getScheme($uri);

    // A scheme the site has not registered has no directory to prepare, and
    // PHP warns on a stat of a URI it cannot resolve.
    if (is_string($scheme) && !\Drupal::service('stream_wrapper_manager')->isValidScheme($scheme)) {
      return FALSE;
    }

    return $this->getFileSystem()->prepareDirectory($uri, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
  }

  /**
   * {@inheritdoc}
   */
  public function fileWriteManaged(string $uri, string $content, EntityStubInterface $stub): EntityStubInterface {
    $this->prepareFileDirectory($uri);

    $file = \Drupal::service('file.repository')->writeData($content, $uri, FileExists::Replace);

    foreach ($stub->getValues() as $property => $value) {
      $file->set($property, $value);
    }

    $file->save();

    $stub->setValue('fid', $file->id());
    $stub->markSaved($file);

    return $stub;
  }

  /**
   * Creates the directory of a file URI, when it is missing.
   *
   * @param string $uri
   *   The file URI.
   *
   * @throws \RuntimeException
   *   When the directory cannot be prepared.
   */
  protected function prepareFileDirectory(string $uri): void {
    $directory = $this->getFileSystem()->dirname($uri);

    if (!$this->filePrepareDirectory($directory)) {
      throw new \RuntimeException(sprintf('Unable to prepare the directory "%s".', $directory));
    }
  }

  /**
   * Returns the file system service.
   */
  protected function getFileSystem(): FileSystemInterface {
    return \Drupal::service('file_system');
  }

  /**
   * {@inheritdoc}
   */
  public function searchIndexEntity(string $entity_type, int|string $id): bool {
    $definition = $this->loadEntityTypeDefinition($entity_type);
    $entity = \Drupal::entityTypeManager()->getStorage($definition->id())->load($id);

    if (!$entity instanceof ContentEntityInterface) {
      throw new \RuntimeException(sprintf('No content entity of type "%s" with the id "%s" exists.', $entity_type, $id));
    }

    /** @var \Drupal\search_api\Plugin\search_api\datasource\ContentEntityTrackingManager $tracking_manager */
    $tracking_manager = \Drupal::service('search_api.entity_datasource.tracking_manager');
    $tracking_manager->entityInsert($entity);

    $indexes = array_filter($tracking_manager->getIndexesForEntity($entity), static fn(IndexInterface $index): bool => $index->status());

    foreach ($indexes as $index) {
      $index->indexSpecificItems($index->loadItemsMultiple($this->getSearchItemIds($index, $entity)));
    }

    return $indexes !== [];
  }

  /**
   * {@inheritdoc}
   */
  public function searchIndexItems(int $limit): void {
    if ($limit === 0) {
      return;
    }

    /** @var \Drupal\search_api\IndexInterface[] $indexes */
    $indexes = \Drupal::entityTypeManager()->getStorage('search_api_index')->loadByProperties(['status' => TRUE]);

    if ($indexes === []) {
      throw new \RuntimeException('No active search indexes found.');
    }

    foreach ($indexes as $index) {
      $index->indexItems($limit);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function searchRunCron(string $module = 'search_api'): void {
    \Drupal::moduleHandler()->invoke($module, 'cron');
  }

  /**
   * Returns the ids of the items an entity is indexed as on a search index.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The search index.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return list<string>
   *   The item id of each translation of the entity in a language the index
   *   includes.
   */
  protected function getSearchItemIds(IndexInterface $index, ContentEntityInterface $entity): array {
    $datasource_id = 'entity:' . $entity->getEntityTypeId();
    $raw_ids = [];

    foreach ($entity->getTranslationLanguages() as $language) {
      $raw_ids[] = ContentEntityTrackingManager::formatItemId($entity->getEntityTypeId(), (string) $entity->id(), $language->getId());
    }

    $raw_ids = ContentEntityTrackingManager::filterValidItemIds($index, $datasource_id, $raw_ids);

    return array_values(array_map(static fn(string $raw_id): string => Utility::createCombinedId($datasource_id, $raw_id), $raw_ids));
  }

  /**
   * {@inheritdoc}
   */
  public function hasWorkflowState(string $entity_type, string $bundle, string $state): bool {
    $entity_type_manager = \Drupal::entityTypeManager();

    if (!$entity_type_manager->hasDefinition('workflow')) {
      return FALSE;
    }

    foreach ($entity_type_manager->getStorage('workflow')->loadMultiple() as $workflow) {
      $type_settings = $workflow->get('type_settings');
      $bundles = is_array($type_settings) ? $type_settings['entity_types'][$entity_type] ?? [] : [];

      if (is_array($bundles) && in_array($bundle, $bundles, TRUE) && isset($type_settings['states'][$state])) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function setModerationState(EntityStubInterface $stub, string $state): void {
    $entity_type = $stub->getEntityType();
    $values = [$this->getEntityIdKey($entity_type) => $this->resolveStubId($stub), 'moderation_state' => $state];

    $this->updateEntity(new EntityStub($entity_type, NULL, $values));
  }

  /**
   * {@inheritdoc}
   *
   * The order is read at query time, so the tags the module's own form submit
   * invalidates are invalidated here too. They are the list tags of the view's
   * base entity type and the view's own.
   */
  public function draggableviewsSetOrder(string $view_id, string $display_id, array $entity_ids): void {
    $view = \Drupal::entityTypeManager()->getStorage('view')->load($view_id);

    if (!$view instanceof ViewEntityInterface) {
      throw new \RuntimeException(sprintf('No view with the id "%s" exists.', $view_id));
    }

    $database = \Drupal::database();
    // The rows are replaced 1 entity at a time, so the order is written whole
    // or not at all.
    $transaction = $database->startTransaction();

    try {
      foreach ($entity_ids as $weight => $entity_id) {
        // The delete and the insert mirror draggableviews_views_submit().
        $database->delete('draggableviews_structure')
          ->condition('view_name', $view_id)
          ->condition('view_display', $display_id)
          ->condition('entity_id', $entity_id)
          ->execute();

        $database->insert('draggableviews_structure')->fields([
          'view_name' => $view_id,
          'view_display' => $display_id,
          'args' => '[]',
          'entity_id' => $entity_id,
          'weight' => $weight,
        ])->execute();
      }
    }
    catch (\Exception $exception) {
      // @codeCoverageIgnoreStart
      $transaction->rollBack();
      throw $exception;
      // @codeCoverageIgnoreEnd
    }

    $tags = ['config:views.view.' . $view_id, 'config:views.view.' . $view_id . '.' . $display_id];
    $base_entity_type = $view->getExecutable()->getBaseEntityType();

    if ($base_entity_type instanceof EntityTypeInterface) {
      $tags = array_merge($base_entity_type->getListCacheTags(), $tags);
    }

    $this->cacheInvalidateTags($tags);
  }

  /**
   * {@inheritdoc}
   */
  public function login(EntityStubInterface $stub): void {
    \Drupal::service('account_switcher')->switchTo($this->loadUser($stub));
  }

  /**
   * {@inheritdoc}
   */
  public function logout(): void {
    // AccountSwitcher::switchBack() throws RuntimeException when the stack is
    // empty, so the loop runs until it does and every stacked account is
    // popped.
    try {
      for (;;) {
        \Drupal::service('account_switcher')->switchBack();
      }
    }
    catch (\RuntimeException) {
    }
  }

}
