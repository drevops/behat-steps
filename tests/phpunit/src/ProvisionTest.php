<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests the transforms the fixture site is provisioned through.
 *
 * The provisioning sequence itself is exercised by the CI matrix, which
 * builds a real site on every leg. What is covered here is the logic that
 * shapes the build: the Composer merge and the core version widening.
 */
#[CoversFunction('provision_append_settings')]
#[CoversFunction('provision_constraint')]
#[CoversFunction('provision_env')]
#[CoversFunction('provision_merge_composer')]
#[CoversFunction('provision_read_json')]
#[CoversFunction('provision_rebase_psr4')]
#[CoversFunction('provision_section')]
#[CoversFunction('provision_widen_constraint')]
#[CoversFunction('provision_widen_core_version_requirement')]
#[CoversFunction('provision_widen_info_text')]
#[CoversFunction('provision_with_env')]
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
    putenv('PROVISION_TEST_VARIABLE=12');

    $this->assertSame('12', provision_env('PROVISION_TEST_VARIABLE', '11'));

    putenv('PROVISION_TEST_VARIABLE');
  }

  /**
   * Assert that a command without variables is left alone.
   */
  public function testWithEnvLeavesTheCommandAlone(): void {
    $this->assertSame('composer update', provision_with_env('composer update', []));
  }

  /**
   * Assert that variables are prefixed and quoted.
   */
  public function testWithEnvPrefixesTheAssignments(): void {
    $expected = "/usr/bin/env COMPOSER_MEMORY_LIMIT='-1' PHP_OPTIONS='-d sendmail_path=/bin/true' composer update";
    $env = ['COMPOSER_MEMORY_LIMIT' => '-1', 'PHP_OPTIONS' => '-d sendmail_path=/bin/true'];

    $this->assertSame($expected, provision_with_env('composer update', $env));
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
   * Assert that a package's constraint is read.
   */
  public function testConstraintReadsThePackage(): void {
    $file = $this->writeFixture('composer.json', '{"require": {"mglaman/composer-drupal-lenient": "^1.0"}}');

    $this->assertSame('^1.0', provision_constraint($file, 'mglaman/composer-drupal-lenient'));
  }

  /**
   * Assert that a package the file does not declare is reported.
   */
  public function testConstraintReportsAnUndeclaredPackage(): void {
    $file = $this->writeFixture('composer.json', '{"require": {}}');

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('declares no constraint for mglaman/composer-drupal-lenient');

    provision_constraint($file, 'mglaman/composer-drupal-lenient');
  }

  /**
   * Assert that the merge shapes the fixture's Composer configuration.
   */
  public function testMergeComposerShapesTheFixture(): void {
    $merged = provision_merge_composer(static::package(), static::fixture());

    $expected_require_dev = [
      // The package's own runtime requirements, less the PHP constraint.
      'drupal/core-utility' => '^11',
      'behat/behat' => '^3.33 || ^4',
      // A "require-dev" entry backing a "suggest" entry.
      'dmore/behat-chrome-extension' => '^1.4',
      // The packages that run the test suites.
      'drevops/behat-screenshot' => '^2',
      'dvdoug/behat-code-coverage' => '^5.5',
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
   * Assert that a package the fixture pins keeps the fixture's constraint.
   */
  public function testMergeComposerLetsTheFixtureConstraintWin(): void {
    $merged = provision_merge_composer(static::package(), static::fixture());

    $this->assertSame('^11.2', $merged['require']['drupal/core-recommended']);
    $this->assertArrayNotHasKey('drupal/core-recommended', $merged['require-dev']);
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
   * Assert that the package's test namespaces are rebased and Drupal's added.
   */
  public function testMergeComposerRegistersTheTestNamespaces(): void {
    $merged = provision_merge_composer(static::package(), static::fixture());

    $expected = [
      'DrevOps\\BehatSteps\\' => '../src/',
      'DrevOps\\BehatSteps\\Tests\\' => '../tests/phpunit/src/',
      'Drupal\\BuildTests\\' => 'web/core/tests/Drupal/BuildTests/',
      'Drupal\\FunctionalJavascriptTests\\' => 'web/core/tests/Drupal/FunctionalJavascriptTests/',
      'Drupal\\FunctionalTests\\' => 'web/core/tests/Drupal/FunctionalTests/',
      'Drupal\\KernelTests\\' => 'web/core/tests/Drupal/KernelTests/',
      'Drupal\\TestSite\\' => 'web/core/tests/Drupal/TestSite/',
      'Drupal\\Tests\\' => 'web/core/tests/Drupal/Tests/',
      'Drupal\\TestTools\\' => 'web/core/tests/Drupal/TestTools/',
    ];

    $this->assertSame($expected, $merged['autoload-dev']['psr-4']);
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
   * Assert that a core version requirement is widened, or left alone.
   *
   * @param string $text
   *   The info file contents.
   * @param string $expected
   *   The contents the widening produces.
   */
  #[DataProvider('dataProviderWidenInfoText')]
  public function testWidenInfoText(string $text, string $expected): void {
    $this->assertSame($expected, provision_widen_info_text($text));
  }

  /**
   * Fixture rows for the core version widening.
   */
  public static function dataProviderWidenInfoText(): array {
    return [
      'constraint excluding Drupal 12' => [
        "name: Example\ncore_version_requirement: ^10 || ^11\n",
        "name: Example\ncore_version_requirement: '^10 || ^11 || ^12'\n",
      ],
      'quoted constraint' => [
        "core_version_requirement: '^11'\n",
        "core_version_requirement: '^11 || ^12'\n",
      ],
      'double quoted constraint' => [
        "core_version_requirement: \"^11\"\n",
        "core_version_requirement: '^11 || ^12'\n",
      ],
      'constraint already accepting Drupal 12' => [
        "core_version_requirement: ^11 || ^12\n",
        "core_version_requirement: ^11 || ^12\n",
      ],
      'empty constraint' => [
        "core_version_requirement:\n",
        "core_version_requirement:\n",
      ],
      'trailing comment' => [
        "core_version_requirement: ^11 # Set by the fixture.\n",
        "core_version_requirement: '^11 || ^12' # Set by the fixture.\n",
      ],
      'key that is not at the start of a line' => [
        "# core_version_requirement: ^11\n",
        "# core_version_requirement: ^11\n",
      ],
      'no core version requirement' => [
        "name: Example\ntype: module\n",
        "name: Example\ntype: module\n",
      ],
    ];
  }

  /**
   * Assert that every info file under a directory is widened.
   */
  public function testWidenCoreVersionRequirementWalksTheDirectory(): void {
    $widened = $this->writeFixture('contrib/token/token.info.yml', "core_version_requirement: ^11\n");
    $already = $this->writeFixture('contrib/paragraphs/paragraphs.info.yml', "core_version_requirement: ^11 || ^12\n");
    $other = $this->writeFixture('contrib/token/token.services.yml', "core_version_requirement: ^11\n");

    $this->assertSame(1, provision_widen_core_version_requirement(static::$tmp . '/contrib'));
    $this->assertSame("core_version_requirement: '^11 || ^12'\n", file_get_contents($widened));
    $this->assertSame("core_version_requirement: ^11 || ^12\n", file_get_contents($already));
    $this->assertSame("core_version_requirement: ^11\n", file_get_contents($other));
  }

  /**
   * Assert that a build without contrib modules widens nothing.
   */
  public function testWidenCoreVersionRequirementAcceptsTheMissingDirectory(): void {
    $this->assertSame(0, provision_widen_core_version_requirement(static::$tmp . '/absent'));
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
   * The package's Composer configuration, reduced to what the merge reads.
   */
  protected static function package(): array {
    return [
      'name' => 'drevops/behat-steps',
      'require' => [
        'php' => '>=8.3',
        'drupal/core-utility' => '^11',
        'behat/behat' => '^3.33 || ^4',
        'drupal/core-recommended' => '^11',
      ],
      'require-dev' => [
        'dmore/behat-chrome-extension' => '^1.4',
        'phpstan/phpstan' => '^2',
        'drevops/behat-screenshot' => '^2',
        'dvdoug/behat-code-coverage' => '^5.5',
      ],
      'suggest' => [
        'dmore/behat-chrome-extension' => 'Needed to run @javascript scenarios.',
      ],
      'autoload' => ['psr-4' => ['DrevOps\\BehatSteps\\' => 'src/']],
      'autoload-dev' => ['psr-4' => ['DrevOps\\BehatSteps\\Tests\\' => 'tests/phpunit/src/']],
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
