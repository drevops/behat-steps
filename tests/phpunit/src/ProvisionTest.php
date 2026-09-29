<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use DrevOps\BehatSteps\Tests\Fixtures\UnwritableStream;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the fixture site provisioning script.
 *
 * The provisioning sequence itself is exercised by the CI matrix, which
 * builds a real site on every leg. What is covered here is the entry point
 * and the logic that shapes the build: the Composer merge and the paths it
 * rebases.
 *
 * SCRIPT_RUN_SKIP defines the script's functions without running it, so
 * main() is called in process rather than as a subprocess. A global main()
 * can be declared once per process and docs.php declares one too, so each
 * test runs in its own process, which also gives it a pristine verbose()
 * buffer.
 */
#[RunTestsInSeparateProcesses]
#[CoversFunction('main')]
#[CoversFunction('print_help')]
#[CoversFunction('provision_append_settings')]
#[CoversFunction('provision_env')]
#[CoversFunction('provision_merge_composer')]
#[CoversFunction('provision_read_json')]
#[CoversFunction('provision_rebase_path')]
#[CoversFunction('provision_rebase_psr4')]
#[CoversFunction('provision_section')]
#[CoversFunction('provision_with_env')]
#[CoversFunction('provision_write_merged_composer')]
#[CoversFunction('verbose')]
class ProvisionTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    putenv('SCRIPT_RUN_SKIP=1');
    putenv('SCRIPT_QUIET=1');

    require_once __DIR__ . '/../../../scripts/provision.php';
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    putenv('SCRIPT_RUN_SKIP');
    putenv('SCRIPT_QUIET');

    parent::tearDown();
  }

  /**
   * Assert that the help text names the variables that shape the build.
   */
  public function testMainPrintsHelp(): void {
    $buffer = $this->runMain('--help');
    $text = implode('', $buffer);

    $this->assertStringContainsString('Fixture site provisioning.', $text);
    $this->assertStringContainsString('DRUPAL_VERSION', $text);
    $this->assertStringContainsString('DEPS', $text);
    $this->assertStringContainsString('provision.php', $text);
  }

  /**
   * Assert that an argument is rejected rather than silently ignored.
   */
  public function testMainRejectsAnyArgument(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('This script takes no arguments');

    $this->runMain('11');
  }

  /**
   * Assert that messages accumulate in the buffer in order.
   *
   * The buffer is static and the tests share a process, so only the tail it
   * just received is asserted on.
   */
  public function testVerboseBuffersEveryMessage(): void {
    verbose('first%s', '.');
    $buffer = verbose('second.');

    $this->assertSame(['first.', 'second.'], array_slice($buffer, -2));
  }

  /**
   * Assert that a variable carrying no value falls back to the default.
   *
   * @param string $assignment
   *   The putenv() assignment that leaves the variable without a value.
   */
  #[DataProvider('dataProviderEnvFallsBack')]
  public function testEnvFallsBack(string $assignment): void {
    putenv($assignment);

    $this->assertSame('11', provision_env('PROVISION_TEST_VARIABLE', '11'));

    putenv('PROVISION_TEST_VARIABLE');
  }

  /**
   * Fixture rows for the environment fallback.
   */
  public static function dataProviderEnvFallsBack(): array {
    return [
      'unset' => ['PROVISION_TEST_VARIABLE'],
      'empty' => ['PROVISION_TEST_VARIABLE='],
    ];
  }

  /**
   * Assert that a set variable wins over the default.
   */
  public function testEnvReadsTheValue(): void {
    putenv('PROVISION_TEST_VARIABLE=10');

    $this->assertSame('10', provision_env('PROVISION_TEST_VARIABLE', '11'));

    putenv('PROVISION_TEST_VARIABLE');
  }

  /**
   * Assert that a command without variables is left alone.
   */
  public function testWithEnvLeavesTheCommandAlone(): void {
    $this->assertSame('composer install', provision_with_env('composer install', []));
  }

  /**
   * Assert that variables are prefixed and quoted.
   */
  public function testWithEnvPrefixesTheAssignments(): void {
    $expected = "/usr/bin/env COMPOSER_MEMORY_LIMIT='-1' PHP_OPTIONS='-d sendmail_path=/bin/true' composer install";
    $env = ['COMPOSER_MEMORY_LIMIT' => '-1', 'PHP_OPTIONS' => '-d sendmail_path=/bin/true'];

    $this->assertSame($expected, provision_with_env('composer install', $env));
  }

  /**
   * Assert that a section is read, and that a missing one reads as empty.
   */
  public function testSectionReadsAnObjectOnly(): void {
    $config = ['require' => ['drupal/core' => '^11'], 'name' => 'drevops/fixture'];

    $this->assertSame(['drupal/core' => '^11'], provision_section($config, 'require'));
    $this->assertSame([], provision_section($config, 'require-dev'));
    $this->assertSame([], provision_section($config, 'name'));
  }

  /**
   * Assert that a JSON file is read and decoded.
   */
  public function testReadJsonDecodesTheFile(): void {
    $file = $this->writeFixture('composer.json', '{"name": "drevops/fixture"}');

    $this->assertSame(['name' => 'drevops/fixture'], provision_read_json($file));
  }

  /**
   * Assert that a missing file is reported.
   */
  public function testReadJsonReportsTheMissingFile(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Unable to read');

    provision_read_json(static::$tmp . '/absent.json');
  }

  /**
   * Assert that contents that are not an object are reported.
   */
  public function testReadJsonReportsContentsThatAreNotAnObject(): void {
    $file = $this->writeFixture('broken.json', 'not json');

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Unable to decode');

    provision_read_json($file);
  }

  /**
   * Assert that the merge shapes the fixture's Composer configuration.
   */
  public function testMergeComposerShapesTheFixture(): void {
    $merged = provision_merge_composer(static::package(), static::fixture());

    $expected_require_dev = [
      // The package's own runtime requirements, less the PHP constraint.
      'drupal/core-utility' => '^11',
      'behat/behat' => '^3.33',
      'drupal/core-recommended' => '^11',
      // A "require-dev" entry backing a "suggest" entry.
      'dmore/behat-chrome-extension' => '^1.4',
      // The packages that run the Behat suite.
      'drevops/behat-screenshot' => '^2',
      'dvdoug/behat-code-coverage' => '^5.3',
    ];

    $this->assertSame($expected_require_dev, $merged['require-dev']);
  }

  /**
   * Assert that the PHP constraint does not reach the fixture.
   */
  public function testMergeComposerDropsThePhpConstraint(): void {
    $merged = provision_merge_composer(static::package(), static::fixture());

    $this->assertArrayNotHasKey('php', $merged['require-dev']);
  }

  /**
   * Assert that a "require-dev" entry without a "suggest" entry is dropped.
   */
  public function testMergeComposerDropsAnUnsuggestedDevPackage(): void {
    $merged = provision_merge_composer(static::package(), static::fixture());

    $this->assertArrayNotHasKey('phpstan/phpstan', $merged['require-dev']);
  }

  /**
   * Assert that the fixture's own "require" survives the merge.
   */
  public function testMergeComposerKeepsTheFixtureRequire(): void {
    $merged = provision_merge_composer(static::package(), static::fixture());

    $this->assertSame(['drupal/core-recommended' => '^11.2'], $merged['require']);
  }

  /**
   * Assert that every autoload path is rebased on the package root.
   */
  public function testMergeComposerRebasesTheAutoloadPaths(): void {
    $merged = provision_merge_composer(static::package(), static::fixture());

    $this->assertSame(['DrevOps\\BehatSteps\\' => '../src/'], $merged['autoload']['psr-4']);
    $this->assertSame(['scripts/composer/'], $merged['autoload']['classmap']);
  }

  /**
   * Assert that the library namespace is registered for the build.
   */
  public function testMergeComposerRegistersTheLibraryNamespace(): void {
    $merged = provision_merge_composer(static::package(), static::fixture());

    $this->assertSame(['psr-4' => ['DrevOps\\BehatSteps\\' => '../src/']], $merged['autoload-dev']);
  }

  /**
   * Assert that the fixture's own properties survive the merge.
   */
  public function testMergeComposerKeepsTheFixtureProperties(): void {
    $merged = provision_merge_composer(static::package(), static::fixture());

    $this->assertSame('drevops/fixture', $merged['name']);
    $this->assertSame(['allow-plugins' => ['composer/installers' => TRUE]], $merged['config']);
  }

  /**
   * Assert that a section without PSR-4 entries is returned unchanged.
   */
  public function testRebasePsr4LeavesTheClassmapAlone(): void {
    $autoload = ['classmap' => ['scripts/composer/']];

    $this->assertSame($autoload, provision_rebase_psr4($autoload));
  }

  /**
   * Assert that a namespace mapped to a list keeps its shape.
   */
  public function testRebasePsr4RebasesEveryDirectoryOfList(): void {
    $autoload = ['psr-4' => ['DrevOps\\BehatSteps\\' => ['src/', 'lib/']]];
    $expected = ['psr-4' => ['DrevOps\\BehatSteps\\' => ['../src/', '../lib/']]];

    $this->assertSame($expected, provision_rebase_psr4($autoload));
  }

  /**
   * Assert that a PSR-4 path the fixture declares is not rebased.
   *
   * The fixture's own paths are already relative to the build directory, so
   * prefixing them would push them outside it.
   */
  public function testMergeComposerLeavesTheFixturePsr4Alone(): void {
    $fixture = static::fixture();
    $fixture['autoload'] = ['psr-4' => ['Fixture\\Site\\' => 'web/modules/custom/']];

    $merged = provision_merge_composer(static::package(), $fixture);

    $expected = [
      'DrevOps\\BehatSteps\\' => '../src/',
      'Fixture\\Site\\' => 'web/modules/custom/',
    ];

    $this->assertSame($expected, $merged['autoload']['psr-4']);
  }

  /**
   * Assert that the merged configuration is written as pretty JSON.
   */
  public function testWriteMergedComposerWritesTheResult(): void {
    $package_file = $this->writeFixture('package/composer.json', (string) json_encode(static::package()));
    $fixture_file = $this->writeFixture('build/composer.json', (string) json_encode(static::fixture()));

    provision_write_merged_composer($package_file, $fixture_file);

    $written = (string) file_get_contents($fixture_file);

    $this->assertSame(provision_merge_composer(static::package(), static::fixture()), json_decode($written, TRUE));
    $this->assertStringContainsString('"../src/"', $written);
    $this->assertStringNotContainsString('\/', $written);
  }

  /**
   * Assert that the config overrides are appended and the file closed again.
   */
  public function testAppendSettingsAppendsTheOverrides(): void {
    $file = $this->writeFixture('settings.php', "<?php\n\n\$databases = [];\n");
    chmod($file, 0444);

    provision_append_settings($file);

    $contents = (string) file_get_contents($file);

    $this->assertStringStartsWith("<?php\n\n\$databases = [];\n", $contents);
    $this->assertStringContainsString("\$config['system.site']['name'] = 'Overridden Site Name';", $contents);
    $this->assertStringContainsString("\$config['system.site']['slogan'] = 'Overridden Slogan';", $contents);
    $this->assertSame(0444, fileperms($file) & 0777);
  }

  /**
   * Assert that a settings file that is not there is reported.
   */
  public function testAppendSettingsReportsTheMissingFile(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Unable to open');

    provision_append_settings(static::$tmp . '/absent.php');
  }

  /**
   * Assert that a settings file that cannot be appended to is reported.
   */
  public function testAppendSettingsReportsTheFailedWrite(): void {
    UnwritableStream::register("<?php\n");

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Unable to append the fixture config overrides');

    $this->withoutWarnings(static function (): void {
      provision_append_settings(UnwritableStream::path('settings.php'));
    });
  }

  /**
   * Assert that a merged configuration that cannot be written is reported.
   */
  public function testWriteMergedComposerReportsTheFailedWrite(): void {
    UnwritableStream::register((string) json_encode(static::fixture()));
    $package_file = $this->writeFixture('package/composer.json', (string) json_encode(static::package()));

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Unable to write');

    $this->withoutWarnings(static function () use ($package_file): void {
      provision_write_merged_composer($package_file, UnwritableStream::path('composer.json'));
    });
  }

  /**
   * Run a callback with PHP warnings swallowed.
   *
   * A failed write raises a warning before the script reports it, and the
   * suite is configured to fail on one.
   *
   * @param callable $callback
   *   The callback to run.
   */
  protected function withoutWarnings(callable $callback): void {
    set_error_handler(static fn(): bool => TRUE);

    try {
      $callback();
    }
    finally {
      restore_error_handler();
      UnwritableStream::unregister();
    }
  }

  /**
   * Call the script's main() with the given arguments.
   *
   * @param string|array<string> $args
   *   Arguments passed after the script name.
   *
   * @return array<string>
   *   The full verbose() message buffer.
   */
  protected function runMain(string|array $args = []): array {
    $args = is_array($args) ? $args : [$args];
    array_unshift($args, 'provision.php');

    main($args, count($args));

    return verbose('');
  }

  /**
   * Write a file below the per-test temporary directory.
   *
   * @param string $path
   *   Path relative to the temporary directory. Missing parent directories
   *   are created.
   * @param string $contents
   *   The file contents.
   *
   * @return string
   *   The absolute path written.
   */
  protected function writeFixture(string $path, string $contents): string {
    $full_path = static::$tmp . DIRECTORY_SEPARATOR . $path;
    $directory = dirname($full_path);

    if (!is_dir($directory)) {
      mkdir($directory, 0777, TRUE);
    }

    file_put_contents($full_path, $contents);

    return $full_path;
  }

  /**
   * The package's Composer configuration, reduced to what the merge reads.
   */
  protected static function package(): array {
    return [
      'name' => 'drevops/behat-steps',
      'require' => [
        'php' => '>=8.2',
        'drupal/core-utility' => '^11',
        'behat/behat' => '^3.33',
        'drupal/core-recommended' => '^11',
      ],
      'require-dev' => [
        'dmore/behat-chrome-extension' => '^1.4',
        'phpstan/phpstan' => '^2',
        'drevops/behat-screenshot' => '^2',
        'dvdoug/behat-code-coverage' => '^5.3',
      ],
      'suggest' => [
        'dmore/behat-chrome-extension' => 'Needed to run @javascript scenarios.',
      ],
      'autoload' => ['psr-4' => ['DrevOps\\BehatSteps\\' => 'src/']],
    ];
  }

  /**
   * The fixture site's Composer configuration.
   */
  protected static function fixture(): array {
    return [
      'name' => 'drevops/fixture',
      'require' => ['drupal/core-recommended' => '^11.2'],
      'autoload' => ['classmap' => ['scripts/composer/']],
      'config' => ['allow-plugins' => ['composer/installers' => TRUE]],
    ];
  }

}
