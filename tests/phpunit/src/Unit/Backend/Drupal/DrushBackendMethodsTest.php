<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend\Drupal;

use DrevOps\BehatSteps\Backend\Drupal\DrushBackend;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Backend\Random\RandomGeneratorInterface;
use DrevOps\BehatSteps\Tests\Unit\Backend\Fixtures\ParserExposingDrushBackend;
use DrevOps\BehatSteps\Tests\Unit\Backend\Fixtures\RecordingDrushBackend;
use DrevOps\BehatSteps\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Exercises every 'DrushBackend' public method to guarantee line coverage.
 *
 * The actual Drush binary is never executed here; end-to-end behavior is
 * covered separately.
 */
#[CoversClass(DrushBackend::class)]
#[Group('backends')]
#[Group('drush')]
class DrushBackendMethodsTest extends UnitTestCase {

  public function testBootstrapMarksAsBootstrapped(): void {
    $backend = $this->createBackend();

    $this->assertFalse($backend->isBootstrapped());
    $backend->bootstrap();
    $this->assertTrue($backend->isBootstrapped());
  }

  public function testGetRandomReturnsGenerator(): void {
    $backend = $this->createBackend();

    $this->assertInstanceOf(RandomGeneratorInterface::class, $backend->getRandom());
  }

  public function testArgumentsRoundTrip(): void {
    $backend = $this->createBackend();
    $backend->setArguments('--uri=http://example.com');

    $this->assertSame('--uri=http://example.com', $backend->getArguments());
  }

  public function testProcessBatchIsNoop(): void {
    $backend = $this->createBackend();
    $backend->processBatch();

    $this->addToAssertionCount(1);
  }

  public function testCacheClearRebuilds(): void {
    $backend = $this->createBackend();

    $backend->cacheClear();

    $this->assertSame(['cache:rebuild'], array_column($backend->invocations, 'command'));
    $this->assertSame([], $backend->invocations[0]['arguments']);
  }

  public function testCacheClearStaticIsNoop(): void {
    $backend = $this->createBackend();
    $backend->cacheClearStatic();

    $this->addToAssertionCount(1);
  }

  public function testMagicCallForwardsToDrush(): void {
    $backend = $this->createBackend();
    $backend->drushResponse = 'magic-output';

    $result = $backend->__call('status', ['format=json']);

    $this->assertSame('magic-output', $result);
    $this->assertNotEmpty($backend->invocations);
    $this->assertSame('status', $backend->invocations[0]['command']);
  }

  public function testCreateUserWithRolesInvokesRoleAssignment(): void {
    $backend = $this->createBackend();
    $backend->drushResponse = "User ID   :   7\nUser name :   bob\n";
    $backend->drushResponses = ['role:list' => static::buildRoleListOutput(['editor' => 'Editor', 'reviewer' => 'Reviewer'])];

    $user = new EntityStub('user', NULL, [
      'name' => 'bob',
      'pass' => 'pw',
      'mail' => 'bob@ex.co',
      'roles' => ['editor', 'reviewer'],
    ]);
    $backend->createUser($user);

    $commands = array_column($backend->invocations, 'command');
    $this->assertSame('user:create', $commands[0]);
    $this->assertContains('user:role:add', $commands, 'Expected a user:role:add invocation for each role.');
    $this->assertSame(2, array_count_values($commands)['user:role:add'] ?? 0);
  }

  public function testCreateUserThrowsWhenDrushReportsNoUserId(): void {
    $backend = $this->createBackend();
    $backend->drushResponse = "Nothing resembling a user id.\n";

    $user = new EntityStub('user', NULL, [
      'name' => 'bob',
      'pass' => 'pw',
      'mail' => 'bob@ex.co',
    ]);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessageMatches('/did not report a user id after creating "bob"/');

    $backend->createUser($user);
  }

  public function testCreateUserReturnsTheStub(): void {
    $backend = $this->createBackend();
    $backend->drushResponse = "User ID   :   7\n";

    $user = new EntityStub('user', NULL, ['name' => 'bob', 'pass' => 'pw', 'mail' => 'bob@ex.co']);

    $this->assertSame($user, $backend->createUser($user));
    $this->assertSame(7, $user->getValue('uid'));
    $this->assertFalse($user->isSaved(), 'The backend holds no account, so the stub stays unsaved.');
  }

  /**
   * Tests that 'createRole()' returns a stub naming the role it created.
   *
   * @param array<int, mixed> $args
   *   Positional arguments for 'createRole()'.
   */
  #[DataProvider('dataProviderCreateRoleReturnsTheRoleStub')]
  public function testCreateRoleReturnsTheRoleStub(array $args): void {
    $backend = $this->createBackend();

    $created = $backend->createRole(...$args);

    $this->assertSame('user_role', $created->getEntityType());
    $this->assertFalse($created->isSaved());
    $this->assertSame(['id' => $backend->invocations[0]['arguments'][0], 'label' => $backend->invocations[0]['arguments'][1]], $created->getValues());
  }

  public static function dataProviderCreateRoleReturnsTheRoleStub(): \Iterator {
    yield 'generated id and label' => [[[]]];
    yield 'explicit id' => [[[], 'editor']];
    yield 'explicit id and label' => [[['access content'], 'editor', 'Editor']];
  }

  /**
   * Tests that a successful delete runs no existence check.
   *
   * @param string $method
   *   The backend delete method.
   * @param array<int, mixed> $args
   *   Positional arguments for the method.
   * @param string $delete_command
   *   The Drush command that deletes the target.
   */
  #[DataProvider('dataProviderDeleteRunsOneCommandOnSuccess')]
  public function testDeleteRunsOneCommandOnSuccess(string $method, array $args, string $delete_command): void {
    $backend = $this->createBackend();

    $backend->{$method}(...$args);

    $this->assertSame([$delete_command], array_column($backend->invocations, 'command'));
  }

  public static function dataProviderDeleteRunsOneCommandOnSuccess(): \Iterator {
    yield 'deleteRole' => ['deleteRole', ['editor'], 'role:delete'];
    yield 'deleteUser' => ['deleteUser', [new EntityStub('user', NULL, ['name' => 'alice'])], 'user:cancel'];
  }

  /**
   * Tests that a failed delete is tolerated when the target does not exist.
   *
   * @param string $method
   *   The backend delete method.
   * @param array<int, mixed> $args
   *   Positional arguments for the method.
   * @param string $delete_command
   *   The Drush command that deletes the target.
   * @param string $exists_command
   *   The Drush command that checks whether the target exists.
   */
  #[DataProvider('dataProviderDeleteToleratesMissingTarget')]
  public function testDeleteToleratesMissingTarget(string $method, array $args, string $delete_command, string $exists_command): void {
    $backend = $this->createBackend();
    $backend->drushFailures = [$delete_command => 1];
    $backend->drushExitCode = 1;

    $backend->{$method}(...$args);

    $this->assertSame([$delete_command, $exists_command], array_column($backend->invocations, 'command'));
  }

  public static function dataProviderDeleteToleratesMissingTarget(): \Iterator {
    yield 'deleteRole' => ['deleteRole', ['editor'], 'role:delete', 'config:get'];
    yield 'deleteUser' => ['deleteUser', [new EntityStub('user', NULL, ['name' => 'alice'])], 'user:cancel', 'user:information'];
  }

  /**
   * Tests that a failed delete surfaces while the target still exists.
   *
   * @param string $method
   *   The backend delete method.
   * @param array<int, mixed> $args
   *   Positional arguments for the method.
   * @param string $delete_command
   *   The Drush command that deletes the target.
   */
  #[DataProvider('dataProviderDeleteFailureSurfacesWhileTargetExists')]
  public function testDeleteFailureSurfacesWhileTargetExists(string $method, array $args, string $delete_command): void {
    $backend = $this->createBackend();
    $backend->drushFailures = [$delete_command => 1];

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage(sprintf('Drush command "%s" exited with code 1.', $delete_command));

    $backend->{$method}(...$args);
  }

  public static function dataProviderDeleteFailureSurfacesWhileTargetExists(): \Iterator {
    yield 'deleteRole' => ['deleteRole', ['editor'], 'role:delete'];
    yield 'deleteUser' => ['deleteUser', [new EntityStub('user', NULL, ['name' => 'alice'])], 'user:cancel'];
  }

  public function testDeleteUserLooksUpNamelessStubByUid(): void {
    $backend = $this->createBackend();
    $backend->drushFailures = ['user:cancel' => 1];
    $backend->drushExitCode = 1;

    $backend->deleteUser(new EntityStub('user', NULL, ['uid' => 7]));

    $this->assertSame(['user:cancel', 'user:information'], array_column($backend->invocations, 'command'));
    $this->assertSame([[], []], array_column($backend->invocations, 'arguments'));
    $this->assertSame(['yes' => NULL, 'delete-content' => NULL, 'uid' => '7'], $backend->invocations[0]['options']);
    $this->assertSame(['uid' => '7'], $backend->invocations[1]['options']);
  }

  public function testDeleteUserRejectsStubWithoutIdentifier(): void {
    $backend = $this->createBackend();

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Cannot delete a user from a stub without a "name" or "uid" value.');

    $backend->deleteUser(new EntityStub('user'));
  }

  /**
   * Tests that a role is assigned by the machine name its name or label has.
   *
   * @param string $role
   *   The role machine name or label passed to 'addUserRole()'.
   * @param string $expected_rid
   *   The machine name 'user:role:add' must receive.
   */
  #[DataProvider('dataProviderAddUserRoleResolvesTheRole')]
  public function testAddUserRoleResolvesTheRole(string $role, string $expected_rid): void {
    $backend = $this->createBackend();
    $backend->drushResponses = ['role:list' => static::buildRoleListOutput(['content_editor' => 'Content editor', 'editor' => 'Reviewer', 'reviewer' => 'Editor'])];

    $backend->addUserRole(new EntityStub('user', NULL, ['name' => 'alice']), $role);

    $this->assertSame(['role:list', 'user:role:add'], array_column($backend->invocations, 'command'));
    $this->assertSame(['format' => 'json'], $backend->invocations[0]['options']);
    $this->assertSame([$expected_rid, 'alice'], $backend->invocations[1]['arguments']);
  }

  public static function dataProviderAddUserRoleResolvesTheRole(): \Iterator {
    yield 'machine name' => ['content_editor', 'content_editor'];
    yield 'machine name in another case' => ['Content_Editor', 'content_editor'];
    yield 'label' => ['Content editor', 'content_editor'];
    yield 'label in another case' => ['CONTENT EDITOR', 'content_editor'];
    yield 'machine name before the label of another role' => ['editor', 'editor'];
  }

  /**
   * Tests that a role no machine name or label names is not assigned.
   *
   * @param string $role_list
   *   The output 'role:list' returns.
   */
  #[DataProvider('dataProviderAddUserRoleThrowsOnUnknownRole')]
  public function testAddUserRoleThrowsOnUnknownRole(string $role_list): void {
    $backend = $this->createBackend();
    $backend->drushResponses = ['role:list' => $role_list];

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('No role "Ghost" exists.');

    $backend->addUserRole(new EntityStub('user', NULL, ['name' => 'alice']), 'Ghost');
  }

  public static function dataProviderAddUserRoleThrowsOnUnknownRole(): \Iterator {
    yield 'other roles' => [static::buildRoleListOutput(['editor' => 'Editor'])];
    yield 'no roles' => ['{}'];
    yield 'output that is not JSON' => ['Drush printed a warning.'];
  }

  /**
   * Tests that 'drush()' spawns the configured binary.
   *
   * Uses 'echo' as the binary so the test runs deterministically without
   * requiring a real Drush install. Echo prints the assembled command back on
   * stdout, which 'drush()' then returns.
   */
  public function testDrushExecutesBinaryAndReturnsOutput(): void {
    $echo = $this->resolveSystemBinary('echo');

    if ($echo === NULL) {
      $this->markTestSkipped('echo binary is not available on this system.');
    }

    $backend = new DrushBackend('alias', binary: $echo);

    $result = $backend->drush('version', [], ['format' => 'json']);

    $this->assertStringContainsString('@alias', $result);
    $this->assertStringContainsString('--format=json', $result);
    $this->assertStringContainsString('version', $result);
  }

  public function testDrushAlwaysEmitsNoAnsiFlag(): void {
    $echo = $this->resolveSystemBinary('echo');

    if ($echo === NULL) {
      $this->markTestSkipped('echo binary is not available on this system.');
    }

    $backend = new DrushBackend('alias', binary: $echo);

    $result = $backend->drush('version');

    $this->assertStringContainsString('--no-ansi', $result);
  }

  public function testResolveProjectDrushPrefersComposerBin(): void {
    $binary = $this->writeFixture('bin/drush', '');
    $original = getenv('COMPOSER_BIN_DIR');
    putenv('COMPOSER_BIN_DIR=' . dirname($binary));

    try {
      $backend = new DrushBackend('alias');
      $this->assertSame($binary, $backend->binary);
    }
    finally {
      putenv('COMPOSER_BIN_DIR' . ($original === FALSE ? '' : '=' . $original));
    }
  }

  public function testResolveProjectDrushFallsBackToVendorBin(): void {
    $this->writeFixture('vendor/bin/drush', '');
    $original_cwd = (string) getcwd();
    $original_composer = getenv('COMPOSER_BIN_DIR');
    putenv('COMPOSER_BIN_DIR');
    chdir(static::$tmp);

    try {
      $backend = new DrushBackend('alias');
      $this->assertSame(getcwd() . '/vendor/bin/drush', $backend->binary);
    }
    finally {
      chdir($original_cwd);

      if ($original_composer !== FALSE) {
        putenv('COMPOSER_BIN_DIR=' . $original_composer);
      }
    }
  }

  public function testDrushThrowsRuntimeExceptionOnFailure(): void {
    $false = $this->resolveSystemBinary('false');

    if ($false === NULL) {
      $this->markTestSkipped('false binary is not available on this system.');
    }

    $backend = new DrushBackend('alias', binary: $false);

    $this->expectException(\RuntimeException::class);
    $backend->drush('version');
  }

  /**
   * Tests 'parseArguments()' serializes boolean and value options.
   *
   * @param array<string, string|bool|null> $options
   *   Options passed to 'parseArguments()'.
   * @param array<int, string> $expected
   *   The expected argv entries.
   */
  #[DataProvider('dataProviderParseArguments')]
  public function testParseArguments(array $options, array $expected): void {
    $this->assertSame($expected, ParserExposingDrushBackend::callParseArguments($options));
  }

  public static function dataProviderParseArguments(): \Iterator {
    yield 'empty' => [[], []];
    yield 'single flag' => [['yes' => NULL], ['--yes']];
    yield 'single valued option' => [['format' => 'json'], ['--format=json']];
    yield 'flag and valued' => [['yes' => NULL, 'format' => 'json'], ['--yes', '--format=json']];
    yield 'multiple valued' => [['format' => 'json', 'root' => '/var/www'], ['--format=json', '--root=/var/www']];
    yield 'value carrying shell syntax stays one argument' => [['name' => '$(id)'], ['--name=$(id)']];
  }

  /**
   * Tests 'parseArguments()' rejects an option name that is not a bare name.
   *
   * @param string $name
   *   The option name to reject.
   */
  #[DataProvider('dataProviderParseArgumentsRejectsName')]
  public function testParseArgumentsRejectsName(string $name): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Invalid Drush option name: ' . $name);

    ParserExposingDrushBackend::callParseArguments([$name => 'value']);
  }

  public static function dataProviderParseArgumentsRejectsName(): \Iterator {
    yield 'space' => ['two words'];
    yield 'leading dash' => ['-format'];
    yield 'equals sign' => ['format=json'];
    yield 'slash' => ['some/path'];
  }

  /**
   * Tests every command-issuing method drives 'drush()' as expected.
   *
   * Each command is issued by its canonical colon-separated name, which
   * Drush 13 and later register, never by a legacy alias.
   *
   * @param string $method
   *   The backend method name.
   * @param array<int, mixed> $args
   *   Positional arguments for the backend method.
   * @param string|null $expected_command
   *   A Drush command string the method must invoke.
   * @param string|null $drush_response
   *   Raw output returned by the stubbed 'drush()' call, or NULL for none.
   */
  #[DataProvider('dataProviderInvokesDrush')]
  public function testInvokesDrush(string $method, array $args, ?string $expected_command, ?string $drush_response = NULL): void {
    $backend = $this->createBackend();
    $backend->drushResponse = $drush_response ?? '';
    $backend->drushResponses = ['version' => '{"drush-version":"13.8.0"}'];

    $backend->{$method}(...$args);

    $commands = array_column($backend->invocations, 'command');

    $this->assertNotEmpty($commands, 'Expected at least one drush() invocation.');

    if ($expected_command !== NULL) {
      $this->assertContains($expected_command, $commands);
    }

    // 'version' is the 1 Drush command with no namespaced form.
    foreach (array_diff($commands, ['version']) as $command) {
      $this->assertMatchesRegularExpression('/^[a-z]+(?:-[a-z]+)*(?::[a-z]+(?:-[a-z]+)*)+$/', $command, sprintf('The command "%s" is not a canonical colon-separated Drush command name.', $command));
    }
  }

  public static function dataProviderInvokesDrush(): \Iterator {
    $user = new EntityStub('user', NULL, ['name' => 'alice', 'pass' => 'pw', 'mail' => 'alice@ex.co']);

    yield 'cacheClear' => ['cacheClear', [], 'cache:rebuild'];
    yield 'cacheClearBin' => ['cacheClearBin', ['render'], 'cache:clear'];
    yield 'cacheInvalidateTags' => ['cacheInvalidateTags', [['node_list']], 'cache:tags'];
    yield 'cacheDeletePagePath' => ['cacheDeletePagePath', ['page', '/about'], 'cache:clear'];
    yield 'getOneTimeLoginUrl' => ['getOneTimeLoginUrl', [new EntityStub('user', NULL, ['uid' => 7])], 'user:login', 'http://default/user/reset/7/1/hash/login'];
    yield 'queueCountItems' => ['queueCountItems', ['behat_test'], 'queue:list', '{"behat_test":{"queue":"behat_test","items":3,"class":"DatabaseQueue"}}'];
    yield 'queueDelete' => ['queueDelete', ['behat_test'], 'queue:delete'];
    yield 'queueProcess' => ['queueProcess', ['behat_test', 2, 30], 'queue:run', 'Processed 2 items from the behat_test queue in 0.01 sec.'];
    yield 'logGetLastId' => ['logGetLastId', [], 'watchdog:show', '{"5":{"wid":"5","type":"php","severity":"Warning","message":"m"}}'];
    yield 'logRead' => ['logRead', [['php'], 0, 4], 'watchdog:show', '{"5":{"wid":"5","type":"php","severity":"Warning","message":"m"}}'];
    yield 'logDelete' => ['logDelete', [[5]], 'watchdog:delete'];
    yield 'searchIndexItems' => ['searchIndexItems', [3], 'search-api:index'];
    yield 'createUser' => ['createUser', [$user], 'user:create', "User ID   :   9\n"];
    yield 'deleteUser' => ['deleteUser', [$user], 'user:cancel'];
    yield 'addUserRole' => ['addUserRole', [$user, 'admin'], 'role:list', static::buildRoleListOutput(['admin' => 'Admin'])];
    yield 'getUserRoles' => ['getUserRoles', [$user], 'user:information', '{"7":{"uid":"7","name":"alice","roles":["authenticated"]}}'];
    yield 'setUserPassword' => ['setUserPassword', [$user, 'new-password'], 'user:password'];
    yield 'cronRun' => ['cronRun', [], 'core:cron'];
    yield 'moduleInstall' => ['moduleInstall', ['dblog'], 'pm:install'];
    yield 'moduleUninstall' => ['moduleUninstall', ['dblog'], 'pm:uninstall'];
    yield 'configGet' => ['configGet', ['system.site', 'name'], 'config:get', '"Example"'];
    yield 'configGetOriginal' => ['configGetOriginal', ['system.site'], 'config:get', '{}'];
    yield 'configSet' => ['configSet', ['system.site', 'name', 'v'], 'config:set'];
    yield 'configExists' => ['configExists', ['system.site'], 'config:get', '{}'];
    yield 'configGetData' => ['configGetData', ['system.site'], 'config:get', '{"name":"Example"}'];
    yield 'configSetData' => ['configSetData', ['system.site', ['name' => 'Example']], 'config:get', '{"name":"Old"}'];
    yield 'configDelete' => ['configDelete', ['system.site'], 'config:get', '{}'];
    yield 'stateGet' => ['stateGet', ['my.key'], 'state:get', '{"my.key":"v"}'];
    yield 'stateSet' => ['stateSet', ['my.key', 'v'], 'state:set'];
    yield 'stateDelete' => ['stateDelete', ['my.key'], 'state:delete'];
    yield 'stateExists' => ['stateExists', ['my.key'], 'state:get', '{"my.key":"v"}'];
    yield 'moduleIsEnabled' => ['moduleIsEnabled', ['dblog'], 'pm:list', '{"dblog":{"status":"Enabled"}}'];
    yield 'moduleIsPresent' => ['moduleIsPresent', ['dblog'], 'pm:list', '{"dblog":{"status":"Disabled"}}'];
    yield 'createRole no permissions' => ['createRole', [[]], 'role:create'];
    yield 'createRole with permissions' => ['createRole', [['access content']], 'role:create'];
    yield 'createRole with explicit id' => ['createRole', [[], 'editor'], 'role:create'];
    yield 'createRole with id and label' => ['createRole', [['access content'], 'editor', 'Editor'], 'role:create'];
    yield 'deleteRole' => ['deleteRole', ['editor'], 'role:delete'];
  }

  /**
   * Tests that a config write requests an input format Drush parses.
   *
   * 'config:set' parses its value only under '--input-format=yaml'. Under any
   * other format the value is stored verbatim, so a JSON payload is stored as
   * JSON text, not as the value it encodes.
   */
  public function testConfigSetRequestsParsedInputFormat(): void {
    $backend = $this->createBackend();

    $backend->configSet('system.site', 'page', ['front' => '/node']);

    $this->assertSame('config:set', $backend->invocations[0]['command']);
    $this->assertSame('yaml', $backend->invocations[0]['options']['input-format']);
    $this->assertSame('{"front":"\/node"}', $backend->invocations[0]['arguments'][2]);
  }

  /**
   * Tests that only the effective read asks Drush to apply overrides.
   */
  public function testConfigReadsSeparateStoredFromEffective(): void {
    $backend = $this->createBackend();
    $backend->drushResponse = '{"system.site:name":"Example"}';

    $backend->configGet('system.site', 'name');
    $this->assertArrayHasKey('include-overridden', $backend->invocations[0]['options']);

    $backend->configGetOriginal('system.site', 'name');
    $this->assertArrayNotHasKey('include-overridden', $backend->invocations[1]['options']);
  }

  /**
   * Tests that a keyed read returns the value rather than Drush's envelope.
   *
   * @param string $method
   *   The backend method to call.
   * @param array<int, mixed> $args
   *   Positional arguments for the method.
   * @param string $drush_response
   *   Raw JSON the stubbed Drush call returns.
   * @param mixed $expected
   *   The value the method must return.
   */
  #[DataProvider('dataProviderUnwrapsEnvelope')]
  public function testUnwrapsEnvelope(string $method, array $args, string $drush_response, mixed $expected): void {
    $backend = $this->createBackend();
    $backend->drushResponse = $drush_response;

    $this->assertSame($expected, $backend->{$method}(...$args));
  }

  public static function dataProviderUnwrapsEnvelope(): \Iterator {
    yield 'config key read unwraps the name:key entry' => [
      'configGet',
      ['system.site', 'name'],
      '{"system.site:name":"Example"}',
      'Example',
    ];
    yield 'config whole-object read is already unwrapped' => [
      'configGet',
      ['system.site'],
      '{"name":"Example"}',
      ['name' => 'Example'],
    ];
    yield 'state read unwraps the key entry' => [
      'stateGet',
      ['my.key'],
      '{"my.key":42}',
      42,
    ];
  }

  /**
   * Tests that a read of a missing object reports absence rather than failing.
   *
   * 'config:get' exits non-zero for an object that does not exist, while the
   * capability promises the absent value Drupal's config API reports.
   */
  public function testConfigReadOfMissingObjectReturnsNull(): void {
    $backend = $this->createBackend();
    $backend->drushExitCode = 1;

    $this->assertNull($backend->configGet('missing.object', 'name'));
    $this->assertSame([], $backend->configGetData('missing.object'));
    $this->assertFalse($backend->configExists('missing.object'));
  }

  public function testConfigDeleteOfMissingObjectIsNoOp(): void {
    $backend = $this->createBackend();
    $backend->drushExitCode = 1;

    $backend->configDelete('missing.object');

    $this->assertSame(['config:get'], array_column($backend->invocations, 'command'));
  }

  /**
   * Tests that a module lookup matches the machine name exactly.
   *
   * The 'pm:list' filter matches any substring of a name, so a listing
   * holding only a longer-named module must not report the module as present.
   */
  public function testModuleLookupMatchesTheExactName(): void {
    $backend = $this->createBackend();
    $backend->drushResponse = '{"node_storage_body_field":{"status":"Enabled"}}';

    $this->assertFalse($backend->moduleIsEnabled('node'));
    $this->assertFalse($backend->moduleIsPresent('node'));

    $backend->drushResponse = '{"node":{"status":"Enabled"},"search_node":{"status":"Enabled"}}';

    $this->assertTrue($backend->moduleIsEnabled('node'));
    $this->assertTrue($backend->moduleIsPresent('node'));
  }

  /**
   * Tests that a failed write restores the configuration object.
   *
   * The delete and the write are separate commands, so a write that fails
   * after the delete would otherwise leave the object missing instead of
   * unchanged.
   */
  public function testConfigSetDataRestoresTheObjectWhenTheWriteFails(): void {
    $backend = $this->createBackend();
    $backend->drushResponse = '{"name":"Original"}';
    $backend->drushFailures['config:set'] = 1;

    try {
      $backend->configSetData('system.site', ['name' => 'Replacement']);
      $this->fail('Expected the failed write to be rethrown.');
    }
    catch (\RuntimeException $exception) {
      $this->assertStringContainsString('config:set', $exception->getMessage());
    }

    $sets = array_values(array_filter($backend->invocations, static fn(array $invocation): bool => $invocation['command'] === 'config:set'));

    $this->assertCount(2, $sets, 'The failed write is followed by a restoring write.');
    $this->assertSame('{"name":"Original"}', $sets[1]['arguments'][2], 'The restore writes back the data read before the delete.');
  }

  public function testConfigSetDataReplacesRatherThanMerges(): void {
    $backend = $this->createBackend();
    $backend->drushResponse = '{"name":"Original","slogan":"Dropped"}';

    $backend->configSetData('system.site', ['name' => 'Example']);

    $commands = array_column($backend->invocations, 'command');

    $this->assertContains('config:delete', $commands, 'The object must be deleted so omitted keys do not survive.');
    $this->assertSame('config:set', end($commands));
  }

  /**
   * Tests that an empty object is written without being deleted.
   *
   * Deleting it would drop no key and leave nothing to restore from, because
   * 'config:set' rejects an empty object.
   */
  public function testConfigSetDataKeepsAnEmptyObjectInPlace(): void {
    $backend = $this->createBackend();
    $backend->drushResponse = '{}';

    $backend->configSetData('system.site', ['name' => 'Example']);

    $commands = array_column($backend->invocations, 'command');

    $this->assertNotContains('config:delete', $commands);
    $this->assertSame('config:set', end($commands));
  }

  public function testQueueCountItemsReadsTheListing(): void {
    $backend = $this->createBackend();
    $backend->drushResponse = '{"behat_test":{"queue":"behat_test","items":3,"class":"DatabaseQueue"}}';

    $this->assertSame(3, $backend->queueCountItems('behat_test'));
    $this->assertSame(['format' => 'json'], $backend->invocations[0]['options']);
    $this->assertSame(0, $backend->queueCountItems('other'), 'A queue Drush does not list reports 0 items.');

    $backend->drushResponse = 'Drush printed a warning.';

    $this->assertSame(0, $backend->queueCountItems('behat_test'));
  }

  public function testQueueDeleteToleratesQueueDrushDoesNotList(): void {
    $backend = $this->createBackend();
    $backend->drushFailures = ['queue:delete' => 1];
    $backend->drushResponses = ['queue:list' => '{}'];

    $backend->queueDelete('behat_test');

    $this->assertSame(['queue:delete', 'queue:list'], array_column($backend->invocations, 'command'));
  }

  public function testQueueDeleteFailureSurfacesWhileTheQueueIsListed(): void {
    $backend = $this->createBackend();
    $backend->drushFailures = ['queue:delete' => 1];
    $backend->drushResponses = ['queue:list' => '{"behat_test":{"items":1}}'];

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Drush command "queue:delete" exited with code 1.');

    $backend->queueDelete('behat_test');
  }

  public function testQueueProcessPassesTheLimitsAndReadsTheCount(): void {
    $backend = $this->createBackend();
    $backend->drushResponse = ' [success] Processed 2 items from the behat_test queue in 0.03 sec.';

    $this->assertSame(2, $backend->queueProcess('behat_test', 5, 45));
    $this->assertSame('queue:run', $backend->invocations[0]['command']);
    $this->assertSame(['behat_test'], $backend->invocations[0]['arguments']);
    $this->assertSame(['items-limit' => '5', 'lease-time' => '45'], $backend->invocations[0]['options']);
  }

  public function testQueueProcessWithZeroLimitRunsNothing(): void {
    $backend = $this->createBackend();

    $this->assertSame(0, $backend->queueProcess('behat_test', 0, 30));
    $this->assertSame([], $backend->invocations);
  }

  public function testQueueProcessThrowsWithoutReportedCount(): void {
    $backend = $this->createBackend();
    $backend->drushResponse = 'Nothing resembling a count.';

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Drush did not report how many items of the queue "behat_test" it processed. Output: Nothing resembling a count.');

    $backend->queueProcess('behat_test', 5, 30);
  }

  public function testQueueProcessThrowsWhenDrushFails(): void {
    $backend = $this->createBackend();
    $backend->drushExitCode = 1;

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Drush command "queue:run" exited with code 1.');

    $backend->queueProcess('behat_test', 5, 30);
  }

  /**
   * Tests that the newest log entry id is read from a 1-entry listing.
   *
   * @param string $drush_response
   *   Raw output the stubbed 'watchdog:show' call returns.
   * @param int $expected
   *   The id the method must return.
   */
  #[DataProvider('dataProviderLogGetLastId')]
  public function testLogGetLastId(string $drush_response, int $expected): void {
    $backend = $this->createBackend();
    $backend->drushResponse = $drush_response;

    $this->assertSame($expected, $backend->logGetLastId());
    $this->assertSame(['format' => 'json', 'count' => '1', 'extended' => NULL], $backend->invocations[0]['options']);
  }

  public static function dataProviderLogGetLastId(): \Iterator {
    yield 'an entry' => ['{"12":{"wid":"12","type":"php"}}', 12];
    yield 'no output' => ['', 0];
    yield 'a notice instead of JSON' => [' [notice] No log messages available.', 0];
  }

  public function testLogReadFiltersByTypeAndIdAndMapsTheSeverity(): void {
    $backend = $this->createBackend();
    $backend->drushResponse = '{"4":{"wid":"4","type":"php","severity":"Warning","message":"Newest"},"3":{"wid":"3","type":"cron","severity":"Error","message":"Other type"},"2":{"wid":"2","type":"php","severity":"Unusual","message":"Unknown label"},"1":{"wid":"1","type":"php","severity":"Error","message":"Oldest"}}';

    $entries = $backend->logRead(['php'], 1, 4);

    $this->assertSame(['format' => 'json', 'count' => '100000', 'extended' => NULL, 'severity-min' => '4'], $backend->invocations[0]['options']);
    $this->assertSame([2, 4], array_column($entries, 'wid'), 'The entries after the id come back oldest first.');
    $this->assertSame(['Unknown label', 'Newest'], array_column($entries, 'message'));
    $this->assertSame(['Unusual', 4], array_column($entries, 'severity'), 'A known label maps to its level and an unknown one stays as printed.');
    $this->assertSame([[], []], array_column($entries, 'variables'));
  }

  public function testLogReadRejectsReadThatOverflowsItsCount(): void {
    $backend = new class('alias') extends RecordingDrushBackend {

      protected const int LOG_READ_COUNT = 2;

    };
    $backend->drushResponse = '{"9":{"wid":"9","type":"php","severity":"Error","message":"Newest"},"8":{"wid":"8","type":"php","severity":"Error","message":"Older"}}';

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('The log holds more than 2 entries newer than the id 5, which is more than 1 read fetches.');

    $backend->logRead(['php'], 5, 4);
  }

  public function testLogReadAcceptsFullReadReachingTheWatermark(): void {
    $backend = new class('alias') extends RecordingDrushBackend {

      protected const int LOG_READ_COUNT = 2;

    };
    $backend->drushResponse = '{"6":{"wid":"6","type":"php","severity":"Error","message":"Newest"},"5":{"wid":"5","type":"php","severity":"Error","message":"Watermark"}}';

    $this->assertSame([6], array_column($backend->logRead(['php'], 5, 4), 'wid'));
  }

  public function testLogReadWithNoTypesReadsNothing(): void {
    $backend = $this->createBackend();

    $this->assertSame([], $backend->logRead([], 0, 4));
    $this->assertSame([], $backend->invocations);
  }

  public function testLogDeleteToleratesMissingEntry(): void {
    $backend = $this->createBackend();
    $backend->drushFailures = ['watchdog:delete' => 1];
    $backend->drushExitCode = 1;

    $backend->logDelete([7]);

    $this->assertSame(['watchdog:delete', 'watchdog:show-one'], array_column($backend->invocations, 'command'));
    $this->assertSame([['7'], ['7']], array_column($backend->invocations, 'arguments'));
    $this->assertSame(['yes' => NULL], $backend->invocations[0]['options']);
  }

  public function testLogDeleteFailureSurfacesWhileTheEntryExists(): void {
    $backend = $this->createBackend();
    $backend->drushFailures = ['watchdog:delete' => 1];

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Drush command "watchdog:delete" exited with code 1.');

    $backend->logDelete([7]);
  }

  /**
   * Tests that the one-time login path is read from the printed link.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStub $stub
   *   The user stub.
   * @param array<string, string> $expected_options
   *   The option naming the user that 'user:login' must receive.
   */
  #[DataProvider('dataProviderGetOneTimeLoginUrl')]
  public function testGetOneTimeLoginUrl(EntityStub $stub, array $expected_options): void {
    $backend = $this->createBackend();
    $backend->drushResponses = ['version' => '{"drush-version":"13.8.0"}', 'user:login' => "http://default/sub/user/reset/7/1700000000/abc-DEF_1/login\n"];

    $this->assertSame('/sub/user/reset/7/1700000000/abc-DEF_1', $backend->getOneTimeLoginUrl($stub));
    $this->assertSame('user:login', $backend->invocations[1]['command']);
    $this->assertSame($expected_options + ['no-browser' => NULL], $backend->invocations[1]['options']);
  }

  public static function dataProviderGetOneTimeLoginUrl(): \Iterator {
    yield 'a uid value' => [new EntityStub('user', NULL, ['uid' => 7, 'name' => 'alice']), ['uid' => '7']];
    yield 'a name value' => [new EntityStub('user', NULL, ['name' => 'alice']), ['name' => 'alice']];
  }

  /**
   * Tests that the browser flag goes to the Drush majors that take it.
   *
   * @param string $version_output
   *   The output 'drush version --format=json' prints.
   * @param array<string, string|null> $expected_options
   *   The options 'user:login' must receive.
   */
  #[DataProvider('dataProviderGetOneTimeLoginUrlPassesNoBrowserOnlyBelowDrush14')]
  public function testGetOneTimeLoginUrlPassesNoBrowserOnlyBelowDrush14(string $version_output, array $expected_options): void {
    $backend = $this->createBackend();
    $backend->drushResponses = ['version' => $version_output, 'user:login' => 'http://default/user/reset/7/1/hash/login'];

    $backend->getOneTimeLoginUrl(new EntityStub('user', NULL, ['uid' => 7]));

    $this->assertSame('version', $backend->invocations[0]['command']);
    $this->assertSame(['format' => 'json'], $backend->invocations[0]['options']);
    $this->assertSame($expected_options, $backend->invocations[1]['options']);
  }

  public static function dataProviderGetOneTimeLoginUrlPassesNoBrowserOnlyBelowDrush14(): \Iterator {
    yield 'Drush 13' => ['{"drush-version":"13.8.0"}', ['uid' => '7', 'no-browser' => NULL]];
    yield 'Drush 14' => ['{"drush-version":"14.0.0-dev"}', ['uid' => '7']];
  }

  public function testGetOneTimeLoginUrlReadsTheDrushVersionOnce(): void {
    $backend = $this->createBackend();
    $backend->drushResponses = ['version' => '{"drush-version":"13.8.0"}', 'user:login' => 'http://default/user/reset/7/1/hash/login'];

    $backend->getOneTimeLoginUrl(new EntityStub('user', NULL, ['uid' => 7]));
    $backend->getOneTimeLoginUrl(new EntityStub('user', NULL, ['uid' => 8]));

    $this->assertSame(['version', 'user:login', 'user:login'], array_column($backend->invocations, 'command'));
  }

  public function testGetOneTimeLoginUrlRejectsMissingDrushVersion(): void {
    $backend = $this->createBackend();
    $backend->drushResponses = ['version' => 'Drush printed a warning.'];

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Drush did not print its version. Output: Drush printed a warning.');

    $backend->getOneTimeLoginUrl(new EntityStub('user', NULL, ['uid' => 7]));
  }

  public function testGetOneTimeLoginUrlRejectsStubWithoutIdentifier(): void {
    $backend = $this->createBackend();

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Cannot resolve a user from the stub: neither the saved entity, a "uid" value nor a "name" value is set.');

    $backend->getOneTimeLoginUrl(new EntityStub('user'));
  }

  public function testGetOneTimeLoginUrlRejectsOutputWithoutLink(): void {
    $backend = $this->createBackend();
    $backend->drushResponses = ['version' => '{"drush-version":"13.8.0"}'];
    $backend->drushResponse = 'Drush printed a warning.';

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Drush did not print a one-time login link. Output: Drush printed a warning.');

    $backend->getOneTimeLoginUrl(new EntityStub('user', NULL, ['uid' => 7]));
  }

  /**
   * Tests that the roles are read from the account Drush prints.
   *
   * @param \DrevOps\BehatSteps\Backend\Entity\EntityStub $stub
   *   The user stub.
   * @param array<int, string> $expected_arguments
   *   The arguments 'user:information' must receive.
   * @param array<string, string> $expected_options
   *   The options 'user:information' must receive.
   */
  #[DataProvider('dataProviderGetUserRoles')]
  public function testGetUserRoles(EntityStub $stub, array $expected_arguments, array $expected_options): void {
    $backend = $this->createBackend();
    $backend->drushResponses = ['user:information' => '{"7":{"uid":"7","name":"alice","roles":["authenticated","editor"]}}'];

    $this->assertSame(['authenticated', 'editor'], $backend->getUserRoles($stub));
    $this->assertSame([['command' => 'user:information', 'arguments' => $expected_arguments, 'options' => $expected_options]], $backend->invocations);
  }

  public static function dataProviderGetUserRoles(): \Iterator {
    yield 'a uid value' => [new EntityStub('user', NULL, ['uid' => 7, 'name' => 'alice']), [], ['uid' => '7', 'format' => 'json']];
    yield 'a name value' => [new EntityStub('user', NULL, ['name' => 'alice']), ['alice'], ['format' => 'json']];
  }

  public function testGetUserRolesReadsNoRolesFromAnAccountWithoutList(): void {
    $backend = $this->createBackend();
    $backend->drushResponses = ['user:information' => '{"7":{"uid":"7","name":"alice","roles":"authenticated"}}'];

    $this->assertSame([], $backend->getUserRoles(new EntityStub('user', NULL, ['uid' => 7])));
  }

  public function testGetUserRolesRejectsOutputWithoutAnAccount(): void {
    $backend = $this->createBackend();
    $backend->drushResponses = ['user:information' => 'Drush printed a warning.'];

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Drush did not print the account of the user "7". Output: Drush printed a warning.');

    $backend->getUserRoles(new EntityStub('user', NULL, ['uid' => 7]));
  }

  public function testGetUserRolesRejectsStubWithoutIdentifier(): void {
    $backend = $this->createBackend();

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Cannot resolve a user from the stub: neither the saved entity, a "uid" value nor a "name" value is set.');

    $backend->getUserRoles(new EntityStub('user'));
  }

  public function testSetUserPasswordPassesTheNameTheStubCarries(): void {
    $backend = $this->createBackend();

    $backend->setUserPassword(new EntityStub('user', NULL, ['uid' => 7, 'name' => 'alice']), 'correct horse');

    $this->assertSame([['command' => 'user:password', 'arguments' => ['alice', 'correct horse'], 'options' => []]], $backend->invocations);
  }

  public function testSetUserPasswordLooksUpTheNameOfNamelessStub(): void {
    $backend = $this->createBackend();
    $backend->drushResponses = ['user:information' => '{"7":{"uid":"7","name":"alice","roles":["authenticated"]}}'];

    $backend->setUserPassword(new EntityStub('user', NULL, ['uid' => 7]), 'correct horse');

    $this->assertSame(['user:information', 'user:password'], array_column($backend->invocations, 'command'));
    $this->assertSame(['alice', 'correct horse'], $backend->invocations[1]['arguments']);
  }

  public function testCacheInvalidateTagsJoinsTheTags(): void {
    $backend = $this->createBackend();

    $backend->cacheInvalidateTags(['node_list', 'rendered']);

    $this->assertSame([['node_list,rendered']], array_column($backend->invocations, 'arguments'));
  }

  public function testCacheInvalidateTagsWithNoTagsRunsNothing(): void {
    $backend = $this->createBackend();

    $backend->cacheInvalidateTags([]);

    $this->assertSame([], $backend->invocations);
  }

  public function testCacheDeletePagePathEmptiesTheBin(): void {
    $backend = $this->createBackend();

    $backend->cacheDeletePagePath('page', '/about*', TRUE);

    $this->assertSame([['bin', 'page']], array_column($backend->invocations, 'arguments'));
  }

  public function testSearchIndexItemsPassesTheLimit(): void {
    $backend = $this->createBackend();

    $backend->searchIndexItems(3);

    $this->assertSame(['limit' => '3'], $backend->invocations[0]['options']);
  }

  public function testSearchIndexItemsWithZeroLimitRunsNothing(): void {
    $backend = $this->createBackend();

    $backend->searchIndexItems(0);

    $this->assertSame([], $backend->invocations);
  }

  /**
   * Returns the first executable location for a system utility, or NULL.
   */
  protected function resolveSystemBinary(string $name): ?string {
    foreach (['/bin/' . $name, '/usr/bin/' . $name] as $candidate) {
      if (is_executable($candidate)) {
        return $candidate;
      }
    }

    return NULL;
  }

  protected function createBackend(): RecordingDrushBackend {
    return new RecordingDrushBackend('alias');
  }

  /**
   * Builds the output 'drush role:list --format=json' prints for roles.
   *
   * @param array<string, string> $labels
   *   The role labels keyed by machine name.
   */
  protected static function buildRoleListOutput(array $labels): string {
    $roles = [];

    foreach ($labels as $rid => $label) {
      $roles[$rid] = ['rid' => $rid, 'label' => $label, 'perms' => []];
    }

    return (string) json_encode($roles);
  }

}
