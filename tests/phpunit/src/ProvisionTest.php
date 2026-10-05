<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use DrevOps\BehatSteps\Tests\Fixtures\UnwritableStream;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests the fixture site provisioning script.
 *
 * The CI matrix builds a real site on every leg, so it exercises the
 * provisioning sequence itself. This test covers the logic that shapes the
 * build: the Composer merge, the paths it rebases, the patch map, and the 2
 * rewrites that a Drupal 12 build depends on.
 */
#[CoversFunction('provision_append_settings')]
#[CoversFunction('provision_behat_packages')]
#[CoversFunction('provision_decode_json')]
#[CoversFunction('provision_env')]
#[CoversFunction('provision_install_command')]
#[CoversFunction('provision_is_lenient')]
#[CoversFunction('provision_lenient_constraint')]
#[CoversFunction('provision_merge_composer')]
#[CoversFunction('provision_patches')]
#[CoversFunction('provision_read_json')]
#[CoversFunction('provision_rebase_path')]
#[CoversFunction('provision_rebase_psr4')]
#[CoversFunction('provision_section')]
#[CoversFunction('provision_widen_contrib')]
#[CoversFunction('provision_widen_core_requirement')]
#[CoversFunction('provision_with_env')]
#[CoversFunction('provision_write')]
#[CoversFunction('provision_write_auth')]
#[CoversFunction('provision_write_merged_composer')]
class ProvisionTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    require_once __DIR__ . '/../../../scripts/provision.php';
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

  public static function dataProviderEnvFallsBack(): array {
    return [
      'unset' => ['PROVISION_TEST_VARIABLE'],
      'empty' => ['PROVISION_TEST_VARIABLE='],
    ];
  }

  public function testEnvReadsTheValue(): void {
    putenv('PROVISION_TEST_VARIABLE=10');

    $this->assertSame('10', provision_env('PROVISION_TEST_VARIABLE', '11'));

    putenv('PROVISION_TEST_VARIABLE');
  }

  public function testWithEnvLeavesTheCommandAlone(): void {
    $this->assertSame('composer install', provision_with_env('composer install', []));
  }

  public function testWithEnvPrefixesTheAssignments(): void {
    $expected = "/usr/bin/env COMPOSER_MEMORY_LIMIT='-1' PHP_OPTIONS='-d sendmail_path=/bin/true' composer install";
    $env = ['COMPOSER_MEMORY_LIMIT' => '-1', 'PHP_OPTIONS' => '-d sendmail_path=/bin/true'];

    $this->assertSame($expected, provision_with_env('composer install', $env));
  }

  public function testSectionReadsAnObjectOnly(): void {
    $config = ['require' => ['drupal/core' => '^11'], 'name' => 'drevops/fixture'];

    $this->assertSame(['drupal/core' => '^11'], provision_section($config, 'require'));
    $this->assertSame([], provision_section($config, 'require-dev'));
    $this->assertSame([], provision_section($config, 'name'));
  }

  public function testReadJsonDecodesTheFile(): void {
    $file = $this->writeFixture('composer.json', '{"name": "drevops/fixture"}');

    $this->assertSame(['name' => 'drevops/fixture'], provision_read_json($file));
  }

  public function testReadJsonReportsTheMissingFile(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Unable to read');

    provision_read_json(static::$tmp . '/absent.json');
  }

  public function testReadJsonReportsContentsThatAreNotAnObject(): void {
    $file = $this->writeFixture('broken.json', 'not json');

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Unable to decode');

    provision_read_json($file);
  }

  public function testDecodeJsonDecodesTheContents(): void {
    $this->assertSame(['name' => 'drevops/fixture'], provision_decode_json('{"name": "drevops/fixture"}', 'composer.json'));
  }

  public function testDecodeJsonNamesTheFileItCannotDecode(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Unable to decode /app/composer.json');

    provision_decode_json('not json', '/app/composer.json');
  }

  public function testWriteReportsTheFailedWrite(): void {
    UnwritableStream::register();

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Unable to write');

    $this->withoutWarnings(static function (): void {
      provision_write(UnwritableStream::path('composer.json'), 'contents');
    });
  }

  public function testWriteAuthSkipsWithoutToken(): void {
    $file = static::$tmp . '/auth.json';

    provision_write_auth('', $file);

    $this->assertFileDoesNotExist($file);
  }

  public function testWriteAuthWritesTheTokenPrivately(): void {
    $file = static::$tmp . '/auth.json';

    provision_write_auth('gh-token', $file);

    $this->assertSame(['github-oauth' => ['github.com' => 'gh-token']], json_decode((string) file_get_contents($file), TRUE));
    $this->assertSame(0600, fileperms($file) & 0777);
  }

  public function testWriteAuthRestoresTheUmask(): void {
    UnwritableStream::register();
    $original_umask = umask();

    $this->withoutWarnings(static function (): void {
      try {
        provision_write_auth('gh-token', UnwritableStream::path('auth.json'));
      }
      catch (\RuntimeException) {
        // The restored umask is under test, not the report.
      }
    });

    $this->assertSame($original_umask, umask());
  }

  public function testPatchesAreKeyedByTheirDirectory(): void {
    $this->writeFixture('patches/mglaman/phpstan-drupal/custom-drupal-root.patch', 'diff');
    $this->writeFixture('patches/drupal/webform/fix-the-thing.patch', 'diff');

    $expected = [
      'drupal/webform' => ['fix the thing' => 'patches/drupal/webform/fix-the-thing.patch'],
      'mglaman/phpstan-drupal' => ['custom drupal root' => 'patches/mglaman/phpstan-drupal/custom-drupal-root.patch'],
    ];

    $this->assertSame($expected, provision_patches(static::$tmp . '/patches', static::$tmp));
  }

  public function testPatchesReadsAnEmptyTreeAsNone(): void {
    $this->assertSame([], provision_patches(static::$tmp . '/absent', static::$tmp));
  }

  public function testPatchesSkipsFilesThatAreNotPatches(): void {
    $this->writeFixture('patches/mglaman/phpstan-drupal/README.md', 'not a patch');

    $this->assertSame([], provision_patches(static::$tmp . '/patches', static::$tmp));
  }

  /**
   * Assert which packages a Behat major cannot be installed alongside.
   *
   * @param string $behat
   *   The Behat major the build resolves to.
   * @param string $drupal_version
   *   The Drupal major the build installs.
   * @param array<int, string> $expected
   *   The packages to remove.
   */
  #[DataProvider('dataProviderBehatPackagesListsTheRemovals')]
  public function testBehatPackagesListsTheRemovals(string $behat, string $drupal_version, array $expected): void {
    $this->assertSame($expected, provision_behat_packages($behat, $drupal_version));
  }

  public static function dataProviderBehatPackagesListsTheRemovals(): array {
    return [
      'behat 3 removes nothing' => ['3', '11', []],
      'behat 4 drops the chrome extension and the coverage driver' => ['4', '11', ['dmore/behat-chrome-extension', 'dvdoug/behat-code-coverage']],
      'drupal 12 brings a phpunit the coverage driver accepts' => ['4', '12', ['dmore/behat-chrome-extension']],
    ];
  }

  /**
   * Assert which Drupal majors need their contrib set relaxed.
   *
   * @param string $drupal_version
   *   The Drupal major the build installs.
   * @param bool $expected
   *   Whether the major needs the lenient plugin.
   */
  #[DataProvider('dataProviderIsLenientFromTwelve')]
  public function testIsLenientFromTwelve(string $drupal_version, bool $expected): void {
    $this->assertSame($expected, provision_is_lenient($drupal_version));
  }

  public static function dataProviderIsLenientFromTwelve(): array {
    return [
      'ten' => ['10', FALSE],
      'eleven' => ['11', FALSE],
      'twelve' => ['12', TRUE],
      'thirteen' => ['13', TRUE],
    ];
  }

  public function testLenientConstraintReadsTheFixturePin(): void {
    $file = $this->writeFixture('d12/composer.json', '{"require": {"mglaman/composer-drupal-lenient": "^2.0"}}');

    $this->assertSame('^2.0', provision_lenient_constraint($file));
  }

  /**
   * Assert that a fixture that does not pin the plugin is reported.
   *
   * @param string $contents
   *   The fixture's composer.json.
   */
  #[DataProvider('dataProviderLenientConstraintReportsTheMissingPin')]
  public function testLenientConstraintReportsTheMissingPin(string $contents): void {
    $file = $this->writeFixture('d12/composer.json', $contents);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('is not required by');

    provision_lenient_constraint($file);
  }

  public static function dataProviderLenientConstraintReportsTheMissingPin(): array {
    return [
      'no require section' => ['{"name": "drevops/fixture"}'],
      'not required' => ['{"require": {"drupal/core-recommended": "^12"}}'],
      'required at no version' => ['{"require": {"mglaman/composer-drupal-lenient": ""}}'],
      'required at a value that is not a constraint' => ['{"require": {"mglaman/composer-drupal-lenient": []}}'],
    ];
  }

  /**
   * Assert that the install narrows the Behat constraint to the build's major.
   *
   * @param string $deps
   *   The dependency resolution the build asks for.
   * @param string $behat
   *   The Behat major the build resolves to.
   * @param string $expected
   *   The command.
   */
  #[DataProvider('dataProviderInstallCommandNarrowsBehat')]
  public function testInstallCommandNarrowsBehat(string $deps, string $behat, string $expected): void {
    $this->assertSame($expected, provision_install_command($deps, $behat));
  }

  public static function dataProviderInstallCommandNarrowsBehat(): array {
    return [
      'normal' => ['normal', '3', "composer update --with='behat/behat:^3'"],
      'lowest' => ['lowest', '4', "composer update --prefer-lowest --prefer-stable --with='behat/behat:^4'"],
    ];
  }

  /**
   * Assert how a core version requirement admits the running major.
   *
   * @param string $text
   *   The contents of an info file.
   * @param string $expected
   *   The contents after the rewrite.
   */
  #[DataProvider('dataProviderWidenCoreRequirementAdmitsTheMajor')]
  public function testWidenCoreRequirementAdmitsTheMajor(string $text, string $expected): void {
    $this->assertSame($expected, provision_widen_core_requirement($text, '12'));
  }

  public static function dataProviderWidenCoreRequirementAdmitsTheMajor(): array {
    return [
      'bare constraint' => [
        "name: Token\ncore_version_requirement: ^10 || ^11\n",
        "name: Token\ncore_version_requirement: '^10 || ^11 || ^12'\n",
      ],
      'single-quoted constraint' => [
        "core_version_requirement: '^11'\n",
        "core_version_requirement: '^11 || ^12'\n",
      ],
      'double-quoted constraint' => [
        "core_version_requirement: \"^11\"\n",
        "core_version_requirement: '^11 || ^12'\n",
      ],
      'trailing comment is kept' => [
        "core_version_requirement: ^11 # Pinned by the fixture.\n",
        "core_version_requirement: '^11 || ^12' # Pinned by the fixture.\n",
      ],
      'major already admitted' => [
        "core_version_requirement: ^11 || ^12\n",
        "core_version_requirement: ^11 || ^12\n",
      ],
      'empty constraint' => [
        "core_version_requirement:\n",
        "core_version_requirement:\n",
      ],
      'key absent' => [
        "name: Token\ntype: module\n",
        "name: Token\ntype: module\n",
      ],
      'every occurrence' => [
        "core_version_requirement: ^11\nname: Token\ncore_version_requirement: ^10\n",
        "core_version_requirement: '^11 || ^12'\nname: Token\ncore_version_requirement: '^10 || ^12'\n",
      ],
    ];
  }

  public function testWidenContribRewritesTheWholeTree(): void {
    $token = $this->writeFixture('contrib/token/token.info.yml', "core_version_requirement: ^11\n");
    $nested = $this->writeFixture('contrib/token/modules/token_extra/token_extra.info.yml', "core_version_requirement: ^11\n");
    $webform = $this->writeFixture('contrib/webform/webform.info.yml', "core_version_requirement: ^11 || ^12\n");
    $readme = $this->writeFixture('contrib/token/README.md', 'core_version_requirement: ^11');

    $this->assertSame(2, provision_widen_contrib(static::$tmp . '/contrib', '12'));

    $this->assertSame("core_version_requirement: '^11 || ^12'\n", file_get_contents($token));
    $this->assertSame("core_version_requirement: '^11 || ^12'\n", file_get_contents($nested));
    $this->assertSame("core_version_requirement: ^11 || ^12\n", file_get_contents($webform));
    $this->assertSame('core_version_requirement: ^11', file_get_contents($readme));
  }

  public function testWidenContribReadsAnAbsentTreeAsNone(): void {
    $this->assertSame(0, provision_widen_contrib(static::$tmp . '/absent', '12'));
  }

  public function testMergeComposerShapesTheFixture(): void {
    $merged = provision_merge_composer(static::package(), static::fixture());

    $expected_require_dev = [
      // The package's own runtime requirements, less the PHP constraint.
      'drupal/core-utility' => '^11',
      'behat/behat' => '^3.33 || ^4',
      // A "require-dev" entry backing a "suggest" entry.
      'dmore/behat-chrome-extension' => '^1.4',
      // The packages that run the test suites.
      'alexskrypnyk/phpunit-helpers' => '^0.7',
      'drevops/behat-screenshot' => '^2',
      'dvdoug/behat-code-coverage' => '^5.3',
    ];

    $this->assertSame($expected_require_dev, $merged['require-dev']);
  }

  public function testMergeComposerDropsThePhpConstraint(): void {
    $merged = provision_merge_composer(static::package(), static::fixture());

    $this->assertArrayNotHasKey('php', $merged['require-dev']);
  }

  public function testMergeComposerDropsAnUnsuggestedDevPackage(): void {
    $merged = provision_merge_composer(static::package(), static::fixture());

    $this->assertArrayNotHasKey('phpstan/phpstan', $merged['require-dev']);
  }

  public function testMergeComposerKeepsTheFixtureRequire(): void {
    $merged = provision_merge_composer(static::package(), static::fixture());

    $this->assertSame(['drupal/core-recommended' => '^11.2'], $merged['require']);
  }

  /**
   * Assert that a package the fixture pins is left out of "require-dev".
   *
   * Named in both sections, the package resolves to the lower of the 2
   * constraints under "--prefer-lowest", which can fall outside the range the
   * fixture pins.
   */
  public function testMergeComposerDropsDevPackageTheFixturePins(): void {
    $merged = provision_merge_composer(static::package(), static::fixture());

    $this->assertArrayHasKey('drupal/core-recommended', $merged['require']);
    $this->assertArrayNotHasKey('drupal/core-recommended', $merged['require-dev']);
  }

  public function testMergeComposerRebasesTheAutoloadPaths(): void {
    $merged = provision_merge_composer(static::package(), static::fixture());

    $this->assertSame(['DrevOps\\BehatSteps\\' => '../src/'], $merged['autoload']['psr-4']);
    $this->assertSame(['scripts/composer/'], $merged['autoload']['classmap']);
  }

  /**
   * Assert that the package's own test namespaces are rebased too.
   *
   * The backend test suites run from the build and resolve the package, its
   * tests and their fixtures through these entries.
   */
  public function testMergeComposerRebasesThePackageTestNamespaces(): void {
    $merged = provision_merge_composer(static::package(), static::fixture());

    $expected = [
      'DrevOps\\BehatSteps\\' => '../src/',
      'ConsumerProject\\Backend\\' => '../tests/phpunit/fixtures/backend/ConsumerProject/Backend/',
      'DrevOps\\BehatSteps\\Tests\\' => '../tests/phpunit/src/',
    ];

    $this->assertSame($expected, array_slice($merged['autoload-dev']['psr-4'], 0, 3, TRUE));
  }

  public function testMergeComposerRegistersTheDrupalTestNamespaces(): void {
    $merged = provision_merge_composer(static::package(), static::fixture());

    $expected = [
      'Drupal\\BuildTests\\' => 'web/core/tests/Drupal/BuildTests/',
      'Drupal\\FunctionalJavascriptTests\\' => 'web/core/tests/Drupal/FunctionalJavascriptTests/',
      'Drupal\\FunctionalTests\\' => 'web/core/tests/Drupal/FunctionalTests/',
      'Drupal\\KernelTests\\' => 'web/core/tests/Drupal/KernelTests/',
      'Drupal\\TestSite\\' => 'web/core/tests/Drupal/TestSite/',
      'Drupal\\Tests\\' => 'web/core/tests/Drupal/Tests/',
      'Drupal\\TestTools\\' => 'web/core/tests/Drupal/TestTools/',
    ];

    $this->assertSame($expected, array_slice($merged['autoload-dev']['psr-4'], 3, NULL, TRUE));
  }

  public function testMergeComposerKeepsTheFixtureProperties(): void {
    $merged = provision_merge_composer(static::package(), static::fixture());

    $this->assertSame('drevops/fixture', $merged['name']);
    $this->assertSame(['allow-plugins' => ['composer/installers' => TRUE]], $merged['config']);
  }

  public function testRebasePsr4LeavesTheClassmapAlone(): void {
    $autoload = ['classmap' => ['scripts/composer/']];

    $this->assertSame($autoload, provision_rebase_psr4($autoload));
  }

  public function testRebasePsr4RebasesEveryDirectoryOfList(): void {
    $autoload = ['psr-4' => ['DrevOps\\BehatSteps\\' => ['src/', 'lib/']]];
    $expected = ['psr-4' => ['DrevOps\\BehatSteps\\' => ['../src/', '../lib/']]];

    $this->assertSame($expected, provision_rebase_psr4($autoload));
  }

  public function testRebasePsr4ReadsNonPathValueAsEmpty(): void {
    $autoload = ['psr-4' => ['DrevOps\\BehatSteps\\' => 11]];

    $this->assertSame(['psr-4' => ['DrevOps\\BehatSteps\\' => '../']], provision_rebase_psr4($autoload));
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

  public function testWriteMergedComposerWritesTheResult(): void {
    $package_file = $this->writeFixture('package/composer.json', (string) json_encode(static::package()));
    $fixture_file = $this->writeFixture('build/composer.json', (string) json_encode(static::fixture()));

    provision_write_merged_composer($package_file, $fixture_file);

    $written = (string) file_get_contents($fixture_file);

    $this->assertSame(provision_merge_composer(static::package(), static::fixture()), json_decode($written, TRUE));
    $this->assertStringContainsString('"../src/"', $written);
    $this->assertStringNotContainsString('\/', $written);
  }

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

  public function testAppendSettingsReportsTheMissingFile(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Unable to open');

    provision_append_settings(static::$tmp . '/absent.php');
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
   * The package's Composer configuration, reduced to what the merge reads.
   */
  protected static function package(): array {
    return [
      'name' => 'drevops/behat-steps',
      'require' => [
        'php' => '>=8.2',
        'drupal/core-utility' => '^11',
        'behat/behat' => '^3.33 || ^4',
        'drupal/core-recommended' => '^11',
      ],
      'require-dev' => [
        'dmore/behat-chrome-extension' => '^1.4',
        'phpstan/phpstan' => '^2',
        'alexskrypnyk/phpunit-helpers' => '^0.7',
        'drevops/behat-screenshot' => '^2',
        'dvdoug/behat-code-coverage' => '^5.3',
      ],
      'suggest' => [
        'dmore/behat-chrome-extension' => 'Needed to run @javascript scenarios.',
      ],
      'autoload' => ['psr-4' => ['DrevOps\\BehatSteps\\' => 'src/']],
      'autoload-dev' => [
        'psr-4' => [
          'ConsumerProject\\Backend\\' => 'tests/phpunit/fixtures/backend/ConsumerProject/Backend/',
          'DrevOps\\BehatSteps\\Tests\\' => 'tests/phpunit/src/',
        ],
      ],
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
