<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Backend;

use DrevOps\BehatSteps\Backend\DrushBackend;
use DrevOps\BehatSteps\Backend\Entity\EntityStub;
use DrevOps\BehatSteps\Tests\Unit\Backend\Fixtures\ArgumentsExposingDrushBackend;
use DrevOps\BehatSteps\Tests\Unit\Backend\Fixtures\RecordingDrushBackend;
use Drupal\Component\Utility\Random;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Exercises every 'DrushBackend' public method to guarantee line coverage.
 *
 * Most tests replace 'drush()' with a recorder and assert on the commands it
 * records. The rest run a system binary such as 'echo' through the real
 * 'drush()', or cover 'resolveProjectDrush()' and 'parseArguments()'. The
 * actual Drush binary is never executed here; end-to-end behaviour is covered
 * separately.
 */
#[CoversClass(DrushBackend::class)]
#[Group('backends')]
#[Group('drush')]
class DrushBackendMethodsTest extends TestCase {

  /**
   * Directory the throwaway Drush binary layouts are built under.
   */
  protected const TEMP_ROOT = __DIR__ . '/../../../../../.artifacts/tmp';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    if (!is_dir(self::TEMP_ROOT)) {
      mkdir(self::TEMP_ROOT, 0777, TRUE);
    }
  }

  public function testBootstrapMarksAsBootstrapped(): void {
    $backend = $this->createBackend();

    $this->assertFalse($backend->isBootstrapped());
    $backend->bootstrap();
    $this->assertTrue($backend->isBootstrapped());
  }

  public function testGetRandomReturnsGenerator(): void {
    $backend = $this->createBackend();

    $this->assertInstanceOf(Random::class, $backend->getRandom());
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

    $this->assertNotEmpty($backend->invocations);
    $commands = array_column($backend->invocations, 'command');
    $this->assertContains('cache:rebuild', $commands);
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

  public function testUserCreateWithRolesInvokesRoleAssignment(): void {
    $backend = $this->createBackend();
    $backend->drushResponse = "User ID   :   7\nUser name :   bob\n";

    $user = new EntityStub('user', NULL, [
      'name' => 'bob',
      'pass' => 'pw',
      'mail' => 'bob@ex.co',
      'roles' => ['editor', 'reviewer'],
    ]);
    $backend->userCreate($user);

    $commands = array_column($backend->invocations, 'command');
    $this->assertSame('user-create', $commands[0]);
    $this->assertContains('user-add-role', $commands, 'Expected a user-add-role invocation for each role.');
    $this->assertSame(2, array_count_values($commands)['user-add-role'] ?? 0);
  }

  public function testUserCreateThrowsWhenDrushReportsNoUserId(): void {
    $backend = $this->createBackend();
    $backend->drushResponse = "Nothing resembling a user id.\n";

    $user = new EntityStub('user', NULL, [
      'name' => 'bob',
      'pass' => 'pw',
      'mail' => 'bob@ex.co',
    ]);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessageMatches("/did not report a user id after creating 'bob'/");

    $backend->userCreate($user);
  }

  /**
   * Tests 'cacheClear()' with a drush-only bin skips the rebuild.
   */
  public function testCacheClearDrushOnlySkipsRebuild(): void {
    $backend = $this->createBackend();

    $backend->cacheClear('drush');

    $this->assertCount(1, $backend->invocations);
    $this->assertSame('cache-clear', $backend->invocations[0]['command']);
    $this->assertSame(['drush'], $backend->invocations[0]['arguments']);
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

  /**
   * Tests that 'resolveProjectDrush()' prefers 'COMPOSER_BIN_DIR'.
   */
  public function testResolveProjectDrushPrefersComposerBin(): void {
    $temp_dir = self::TEMP_ROOT . '/drush-backend-test-' . uniqid();
    mkdir($temp_dir, 0777, TRUE);
    touch($temp_dir . '/drush');
    $previous = getenv('COMPOSER_BIN_DIR');
    putenv('COMPOSER_BIN_DIR=' . $temp_dir);

    try {
      $backend = new DrushBackend('alias');
      $this->assertSame($temp_dir . '/drush', $backend->binary);
    }
    finally {
      putenv('COMPOSER_BIN_DIR' . ($previous === FALSE ? '' : '=' . $previous));
      unlink($temp_dir . '/drush');
      rmdir($temp_dir);
    }
  }

  public function testResolveProjectDrushFallsBackToVendorBin(): void {
    $temp_dir = self::TEMP_ROOT . '/drush-backend-cwd-' . uniqid();
    mkdir($temp_dir . '/vendor/bin', 0777, TRUE);
    touch($temp_dir . '/vendor/bin/drush');
    $previous_cwd = (string) getcwd();
    $previous_composer = getenv('COMPOSER_BIN_DIR');
    putenv('COMPOSER_BIN_DIR');
    chdir($temp_dir);

    try {
      $backend = new DrushBackend('alias');
      $this->assertSame(getcwd() . '/vendor/bin/drush', $backend->binary);
    }
    finally {
      chdir($previous_cwd);
      if ($previous_composer !== FALSE) {
        putenv('COMPOSER_BIN_DIR=' . $previous_composer);
      }
      unlink($temp_dir . '/vendor/bin/drush');
      rmdir($temp_dir . '/vendor/bin');
      rmdir($temp_dir . '/vendor');
      rmdir($temp_dir);
    }
  }

  /**
   * Tests that 'drush()' throws a 'RuntimeException' on a non-zero exit.
   */
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
   * Tests 'parseArguments()' serialises boolean and value options.
   *
   * @param array<string, string|bool|null> $options
   *   Options passed to 'parseArguments()'.
   * @param array<int, string> $expected
   *   The expected argv entries.
   */
  #[DataProvider('dataProviderParseArguments')]
  public function testParseArguments(array $options, array $expected): void {
    $this->assertSame($expected, ArgumentsExposingDrushBackend::callParseArguments($options));
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

    ArgumentsExposingDrushBackend::callParseArguments([$name => 'value']);
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
   * @param string $method
   *   The backend method name.
   * @param array<int, mixed> $args
   *   Positional arguments for the backend method.
   * @param string|null $expected_command
   *   The first Drush command string expected to be invoked.
   * @param string $drush_response
   *   Raw output returned by the stubbed 'drush()' call.
   */
  #[DataProvider('dataProviderInvokesDrush')]
  public function testInvokesDrush(string $method, array $args, ?string $expected_command, string $drush_response = ''): void {
    $backend = $this->createBackend();
    $backend->drushResponse = $drush_response;

    $backend->{$method}(...$args);

    $this->assertNotEmpty($backend->invocations, 'Expected at least one drush() invocation.');

    if ($expected_command !== NULL) {
      $this->assertSame($expected_command, $backend->invocations[0]['command']);
    }
  }

  /**
   * Data provider: method -> args -> first-expected-drush-command.
   */
  public static function dataProviderInvokesDrush(): \Iterator {
    $user = new EntityStub('user', NULL, ['name' => 'alice', 'pass' => 'pw', 'mail' => 'alice@ex.co']);

    yield 'userCreate' => ['userCreate', [$user], 'user-create', "User ID   :   9\n"];
    yield 'userDelete' => ['userDelete', [$user], 'user-cancel'];
    yield 'userAddRole' => ['userAddRole', [$user, 'admin'], 'user-add-role'];
    yield 'cronRun' => ['cronRun', [], 'cron'];
    yield 'moduleInstall' => ['moduleInstall', ['dblog'], 'pm-enable'];
    yield 'moduleUninstall' => ['moduleUninstall', ['dblog'], 'pm-uninstall'];
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
    yield 'roleCreate no permissions' => ['roleCreate', [[]], 'role:create'];
    yield 'roleCreate with permissions' => ['roleCreate', [['access content']], 'role:create'];
    yield 'roleCreate with explicit id' => ['roleCreate', [[], 'editor'], 'role:create'];
    yield 'roleCreate with id and label' => ['roleCreate', [['access content'], 'editor', 'Editor'], 'role:create'];
    yield 'roleDelete' => ['roleDelete', ['editor'], 'role:delete'];
  }

  /**
   * Tests that a config write requests an input format Drush parses.
   *
   * 'config:set' parses its value only under '--input-format=yaml'; under any
   * other format the value is stored verbatim, so a JSON payload would be
   * stored as its own encoding, not as the value it encodes.
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

  /**
   * Tests that deleting a missing configuration object issues no delete.
   */
  public function testConfigDeleteOfMissingObjectIsNoOp(): void {
    $backend = $this->createBackend();
    $backend->drushExitCode = 1;

    $backend->configDelete('missing.object');

    $this->assertSame(['config:get'], array_column($backend->invocations, 'command'));
  }

  /**
   * Tests that a module lookup matches the machine name exactly.
   *
   * The 'pm:list' filter matches any substring of a name, so a listing that
   * only holds a module with a longer name must not report the module as
   * present.
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
    catch (\RuntimeException $e) {
      $this->assertStringContainsString('config:set', $e->getMessage());
    }

    $sets = array_values(array_filter($backend->invocations, static fn(array $invocation): bool => $invocation['command'] === 'config:set'));

    $this->assertCount(2, $sets, 'The failed write is followed by a restoring write.');
    $this->assertSame('{"name":"Original"}', $sets[1]['arguments'][2], 'The restore writes back the data read before the delete.');
  }

  /**
   * Tests that a whole-object write drops the keys the new data omits.
   */
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

  /**
   * Creates a backend with a stubbed 'drush()' that records every invocation.
   */
  protected function createBackend(): RecordingDrushBackend {
    return new RecordingDrushBackend('alias');
  }

}
