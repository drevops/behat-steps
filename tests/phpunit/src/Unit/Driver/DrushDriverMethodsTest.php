<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Driver;

use DrevOps\BehatSteps\Driver\DrushDriver;
use DrevOps\BehatSteps\Driver\Entity\EntityStub;
use DrevOps\BehatSteps\Tests\Unit\Driver\Fixtures\ArgumentsExposingDrushDriver;
use DrevOps\BehatSteps\Tests\Unit\Driver\Fixtures\RecordingDrushDriver;
use Drupal\Component\Utility\Random;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Exercises every 'DrushDriver' public method to guarantee line coverage.
 *
 * Each test replaces the 'drush()' method with a recorder and verifies the
 * expected Drush command is invoked at least once. The actual Drush binary is
 * never executed here; end-to-end behaviour is covered separately.
 */
#[CoversClass(DrushDriver::class)]
#[Group('drivers')]
#[Group('drush')]
class DrushDriverMethodsTest extends TestCase {

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

  /**
   * Tests that 'bootstrap()' flips the bootstrapped flag.
   */
  public function testBootstrapMarksAsBootstrapped(): void {
    $driver = $this->createDriver();

    $this->assertFalse($driver->isBootstrapped());
    $driver->bootstrap();
    $this->assertTrue($driver->isBootstrapped());
  }

  /**
   * Tests that 'getRandom()' returns the random generator.
   */
  public function testGetRandomReturnsGenerator(): void {
    $driver = $this->createDriver();

    $this->assertInstanceOf(Random::class, $driver->getRandom());
  }

  /**
   * Tests that 'setArguments()' and 'getArguments()' are symmetrical.
   */
  public function testArgumentsRoundTrip(): void {
    $driver = $this->createDriver();
    $driver->setArguments('--uri=http://example.com');

    $this->assertSame('--uri=http://example.com', $driver->getArguments());
  }

  /**
   * Tests that 'processBatch()' is a no-op.
   */
  public function testProcessBatchIsNoop(): void {
    $driver = $this->createDriver();
    $driver->processBatch();

    $this->addToAssertionCount(1);
  }

  /**
   * Tests 'cacheClear()' rebuilds the cache.
   */
  public function testCacheClearRebuilds(): void {
    $driver = $this->createDriver();

    $driver->cacheClear();

    $this->assertNotEmpty($driver->invocations);
    $commands = array_column($driver->invocations, 'command');
    $this->assertContains('cache:rebuild', $commands);
  }

  /**
   * Tests that 'cacheClearStatic()' is a no-op.
   */
  public function testCacheClearStaticIsNoop(): void {
    $driver = $this->createDriver();
    $driver->cacheClearStatic();

    $this->addToAssertionCount(1);
  }

  /**
   * Tests that '__call()' forwards unknown methods through 'drush()'.
   */
  public function testMagicCallForwardsToDrush(): void {
    $driver = $this->createDriver();
    $driver->drushResponse = 'magic-output';

    $result = $driver->__call('status', ['format=json']);

    $this->assertSame('magic-output', $result);
    $this->assertNotEmpty($driver->invocations);
    $this->assertSame('status', $driver->invocations[0]['command']);
  }

  /**
   * Tests 'userCreate()' applies roles when the user object declares them.
   */
  public function testUserCreateWithRolesInvokesRoleAssignment(): void {
    $driver = $this->createDriver();
    $driver->drushResponse = "User ID   :   7\nUser name :   bob\n";

    $user = new EntityStub('user', NULL, [
      'name' => 'bob',
      'pass' => 'pw',
      'mail' => 'bob@ex.co',
      'roles' => ['editor', 'reviewer'],
    ]);
    $driver->userCreate($user);

    $commands = array_column($driver->invocations, 'command');
    $this->assertSame('user-create', $commands[0]);
    $this->assertContains('user-add-role', $commands, 'Expected a user-add-role invocation for each role.');
    $this->assertSame(2, array_count_values($commands)['user-add-role'] ?? 0);
  }

  /**
   * Tests 'userCreate()' rejects a response carrying no user id.
   */
  public function testUserCreateThrowsWhenDrushReportsNoUserId(): void {
    $driver = $this->createDriver();
    $driver->drushResponse = "Nothing resembling a user id.\n";

    $user = new EntityStub('user', NULL, [
      'name' => 'bob',
      'pass' => 'pw',
      'mail' => 'bob@ex.co',
    ]);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessageMatches("/did not report a user id after creating 'bob'/");

    $driver->userCreate($user);
  }

  /**
   * Tests 'cacheClear()' with a drush-only bin skips the rebuild.
   */
  public function testCacheClearDrushOnlySkipsRebuild(): void {
    $driver = $this->createDriver();

    $driver->cacheClear('drush');

    $this->assertCount(1, $driver->invocations);
    $this->assertSame('cache-clear', $driver->invocations[0]['command']);
    $this->assertSame(['drush'], $driver->invocations[0]['arguments']);
  }

  /**
   * Tests that 'drush()' actually spawns the configured binary.
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

    $driver = new DrushDriver('alias', binary: $echo);

    $result = $driver->drush('version', [], ['format' => 'json']);

    $this->assertStringContainsString('@alias', $result);
    $this->assertStringContainsString('--format=json', $result);
    $this->assertStringContainsString('version', $result);
  }

  /**
   * Tests that 'drush()' always emits the '--no-ansi' flag.
   */
  public function testDrushAlwaysEmitsNoAnsiFlag(): void {
    $echo = $this->resolveSystemBinary('echo');
    if ($echo === NULL) {
      $this->markTestSkipped('echo binary is not available on this system.');
    }

    $driver = new DrushDriver('alias', binary: $echo);

    $result = $driver->drush('version');

    $this->assertStringContainsString('--no-ansi', $result);
  }

  /**
   * Tests that 'resolveProjectDrush()' picks up COMPOSER_BIN_DIR first.
   */
  public function testResolveProjectDrushPrefersComposerBin(): void {
    $temp_dir = self::TEMP_ROOT . '/drush-driver-test-' . uniqid();
    mkdir($temp_dir, 0777, TRUE);
    touch($temp_dir . '/drush');
    $previous = getenv('COMPOSER_BIN_DIR');
    putenv('COMPOSER_BIN_DIR=' . $temp_dir);

    try {
      $driver = new DrushDriver('alias');
      $this->assertSame($temp_dir . '/drush', $driver->binary);
    }
    finally {
      putenv('COMPOSER_BIN_DIR' . ($previous === FALSE ? '' : '=' . $previous));
      unlink($temp_dir . '/drush');
      rmdir($temp_dir);
    }
  }

  /**
   * Tests that 'resolveProjectDrush()' falls back to 'vendor/bin/drush'.
   */
  public function testResolveProjectDrushFallsBackToVendorBin(): void {
    $temp_dir = self::TEMP_ROOT . '/drush-driver-cwd-' . uniqid();
    mkdir($temp_dir . '/vendor/bin', 0777, TRUE);
    touch($temp_dir . '/vendor/bin/drush');
    $previous_cwd = (string) getcwd();
    $previous_composer = getenv('COMPOSER_BIN_DIR');
    putenv('COMPOSER_BIN_DIR');
    chdir($temp_dir);

    try {
      $driver = new DrushDriver('alias');
      $this->assertSame(getcwd() . '/vendor/bin/drush', $driver->binary);
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

    $driver = new DrushDriver('alias', binary: $false);

    $this->expectException(\RuntimeException::class);
    $driver->drush('version');
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
   * Tests 'parseArguments()' serialises boolean and value options.
   *
   * @param array<string, string|bool|null> $options
   *   Options passed to 'parseArguments()'.
   * @param array<int, string> $expected
   *   The expected argv entries.
   */
  #[DataProvider('dataProviderParseArguments')]
  public function testParseArguments(array $options, array $expected): void {
    $this->assertSame($expected, ArgumentsExposingDrushDriver::expose($options));
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

    ArgumentsExposingDrushDriver::expose([$name => 'value']);
  }

  /**
   * Data provider for 'testParseArgumentsRejectsName()'.
   */
  public static function dataProviderParseArgumentsRejectsName(): \Iterator {
    yield 'space' => ['two words'];
    yield 'leading dash' => ['-format'];
    yield 'equals sign' => ['format=json'];
    yield 'slash' => ['some/path'];
  }

  /**
   * Data provider for 'testParseArguments()'.
   */
  public static function dataProviderParseArguments(): \Iterator {
    yield 'empty' => [[], []];
    yield 'single flag' => [['yes' => NULL], ['--yes']];
    yield 'single valued option' => [['format' => 'json'], ['--format=json']];
    yield 'flag and valued' => [['yes' => NULL, 'format' => 'json'], ['--yes', '--format=json']];
    yield 'multiple valued' => [['format' => 'json', 'root' => '/var/www'], ['--format=json', '--root=/var/www']];
    yield 'value carrying shell syntax stays one argument' => [['name' => '$(id)'], ['--name=$(id)']];
  }

  /**
   * Tests every command-issuing method drives 'drush()' as expected.
   *
   * @param string $method
   *   The driver method name.
   * @param array<int, mixed> $args
   *   Positional arguments for the driver method.
   * @param string|null $expected_command
   *   The first Drush command string expected to be invoked.
   * @param string $drush_response
   *   Raw output returned by the stubbed 'drush()' call.
   */
  #[DataProvider('dataProviderInvokesDrush')]
  public function testInvokesDrush(string $method, array $args, ?string $expected_command, string $drush_response = ''): void {
    $driver = $this->createDriver();
    $driver->drushResponse = $drush_response;

    $driver->{$method}(...$args);

    $this->assertNotEmpty($driver->invocations, 'Expected at least one drush() invocation.');

    if ($expected_command !== NULL) {
      $this->assertSame($expected_command, $driver->invocations[0]['command']);
    }
  }

  /**
   * Tests that a config write hands Drush a format it actually parses.
   *
   * 'config:set' parses its value only under '--input-format=yaml'; any other
   * value is stored verbatim, so a JSON payload would land as its own encoding
   * rather than as the value it encodes.
   */
  public function testConfigSetRequestsParsedInputFormat(): void {
    $driver = $this->createDriver();

    $driver->configSet('system.site', 'page', ['front' => '/node']);

    $this->assertSame('config:set', $driver->invocations[0]['command']);
    $this->assertSame('yaml', $driver->invocations[0]['options']['input-format']);
    $this->assertSame('{"front":"\/node"}', $driver->invocations[0]['arguments'][2]);
  }

  /**
   * Tests that only the effective read asks Drush to apply overrides.
   */
  public function testConfigReadsSeparateStoredFromEffective(): void {
    $driver = $this->createDriver();
    $driver->drushResponse = '{"system.site:name":"Example"}';

    $driver->configGet('system.site', 'name');
    $this->assertArrayHasKey('include-overridden', $driver->invocations[0]['options']);

    $driver->configGetOriginal('system.site', 'name');
    $this->assertArrayNotHasKey('include-overridden', $driver->invocations[1]['options']);
  }

  /**
   * Tests that a keyed read returns the value rather than Drush's envelope.
   *
   * @param string $method
   *   The driver method to call.
   * @param array<int, mixed> $args
   *   Positional arguments for the method.
   * @param string $drush_response
   *   Raw JSON the stubbed Drush call returns.
   * @param mixed $expected
   *   The value the method must return.
   */
  #[DataProvider('dataProviderUnwrapsEnvelope')]
  public function testUnwrapsEnvelope(string $method, array $args, string $drush_response, mixed $expected): void {
    $driver = $this->createDriver();
    $driver->drushResponse = $drush_response;

    $this->assertSame($expected, $driver->{$method}(...$args));
  }

  /**
   * Data provider for testUnwrapsEnvelope().
   */
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
    $driver = $this->createDriver();
    $driver->drushExitCode = 1;

    $this->assertNull($driver->configGet('missing.object', 'name'));
    $this->assertSame([], $driver->configGetData('missing.object'));
    $this->assertFalse($driver->configExists('missing.object'));
  }

  /**
   * Tests that deleting a missing configuration object issues no delete.
   */
  public function testConfigDeleteOfMissingObjectIsNoOp(): void {
    $driver = $this->createDriver();
    $driver->drushExitCode = 1;

    $driver->configDelete('missing.object');

    $this->assertSame(['config:get'], array_column($driver->invocations, 'command'));
  }

  /**
   * Tests that a module lookup matches the machine name exactly.
   *
   * The 'pm:list' filter matches any substring of a name, so a listing that
   * only holds a longer neighbour must not report the module as present.
   */
  public function testModuleLookupMatchesTheExactName(): void {
    $driver = $this->createDriver();
    $driver->drushResponse = '{"node_storage_body_field":{"status":"Enabled"}}';

    $this->assertFalse($driver->moduleIsEnabled('node'));
    $this->assertFalse($driver->moduleIsPresent('node'));

    $driver->drushResponse = '{"node":{"status":"Enabled"},"search_node":{"status":"Enabled"}}';

    $this->assertTrue($driver->moduleIsEnabled('node'));
    $this->assertTrue($driver->moduleIsPresent('node'));
  }

  /**
   * Tests that a failed write puts the configuration object back.
   *
   * The delete and the write are separate commands, so a write that fails
   * after the delete would otherwise leave the object missing instead of
   * unchanged.
   */
  public function testConfigSetDataRestoresTheObjectWhenTheWriteFails(): void {
    $driver = $this->createDriver();
    $driver->drushResponse = '{"name":"Original"}';
    // Fail the first 'config:set' and let the restoring one through.
    $driver->drushFailures['config:set'] = 1;

    try {
      $driver->configSetData('system.site', ['name' => 'Replacement']);
      $this->fail('Expected the failed write to be rethrown.');
    }
    catch (\RuntimeException $e) {
      $this->assertStringContainsString('config:set', $e->getMessage());
    }

    $sets = array_values(array_filter($driver->invocations, static fn(array $invocation): bool => $invocation['command'] === 'config:set'));

    $this->assertCount(2, $sets, 'The failed write is followed by a restoring write.');
    $this->assertSame('{"name":"Original"}', $sets[1]['arguments'][2], 'The restore writes back the data read before the delete.');
  }

  /**
   * Tests that a whole-object write drops the keys the new data omits.
   */
  public function testConfigSetDataReplacesRatherThanMerges(): void {
    $driver = $this->createDriver();

    $driver->configSetData('system.site', ['name' => 'Example']);

    $commands = array_column($driver->invocations, 'command');

    $this->assertContains('config:delete', $commands, 'The object must be deleted so omitted keys do not survive.');
    $this->assertSame('config:set', end($commands));
  }

  /**
   * Data provider: method -> args -> first-expected-drush-command.
   */
  public static function dataProviderInvokesDrush(): \Iterator {
    $user = new EntityStub('user', NULL, ['name' => 'alice', 'pass' => 'pw', 'mail' => 'alice@ex.co']);

    yield 'userCreate' => ['userCreate', [$user], 'user-create', "User ID   :   9\n"];
    yield 'userDelete' => ['userDelete', [$user], 'user-cancel'];
    yield 'userAddRole' => ['userAddRole', [$user, 'admin'], 'user-add-role'];
    yield 'watchdogFetch' => ['watchdogFetch', [10], 'watchdog-show'];
    yield 'watchdogFetch filtered' => ['watchdogFetch', [10, 'php', 'error'], 'watchdog-show'];
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
   * Creates a driver with a stubbed 'drush()' that records every invocation.
   */
  protected function createDriver(): RecordingDrushDriver {
    return new RecordingDrushDriver('alias');
  }

}
