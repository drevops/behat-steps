<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\ServiceContainer;

use Behat\Behat\Context\ServiceContainer\ContextExtension;
use Behat\Testwork\ServiceContainer\ExtensionManager;
use DrevOps\BehatSteps\Behat\Generator\ClassGenerator;
use DrevOps\BehatSteps\Behat\Mink\ServiceContainer\MinkExtension;
use DrevOps\BehatSteps\Behat\ServiceContainer\BehatStepsExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests the config schema and the services the extension puts in the container.
 */
#[CoversClass(BehatStepsExtension::class)]
class BehatStepsExtensionTest extends TestCase {

  /**
   * Directory holding the binaries the resolver probes for.
   */
  protected static string $fixtureDir;

  /**
   * Working directory to restore after a test that changed it.
   */
  protected string $originalCwd;

  public static function setUpBeforeClass(): void {
    self::$fixtureDir = dirname(__DIR__, 6) . '/.artifacts/tmp/extension-binary-' . getmypid();

    mkdir(self::$fixtureDir . '/project/vendor/bin', 0777, TRUE);
    touch(self::$fixtureDir . '/project/vendor/bin/drush');
    mkdir(self::$fixtureDir . '/project/web', 0777, TRUE);
  }

  public static function tearDownAfterClass(): void {
    $iterator = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator(self::$fixtureDir, \FilesystemIterator::SKIP_DOTS),
      \RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $file) {
      $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }

    rmdir(self::$fixtureDir);
  }

  protected function setUp(): void {
    $this->originalCwd = (string) getcwd();
  }

  protected function tearDown(): void {
    chdir($this->originalCwd);
  }

  public function testConfigKeyNamesTheExtension(): void {
    $this->assertSame('behat_steps', (new BehatStepsExtension())->getConfigKey());
  }

  public function testInitializeTouchesNoOtherExtension(): void {
    $manager = new ExtensionManager([]);

    (new BehatStepsExtension())->initialize($manager);

    $this->assertSame([], $manager->getExtensions());
  }

  public function testAnAjaxTimeoutFromTheMinkTreeOverridesTheDefault(): void {
    $container = $this->load([]);
    $container->setParameter(MinkExtension::DEPRECATED_AJAX_TIMEOUT_PARAMETER, 12);

    (new BehatStepsExtension())->process($container);

    $parameters = $container->getParameter('behat_steps.parameters');
    $this->assertIsArray($parameters);
    $this->assertSame(12, $parameters['ajax_timeout']);
  }

  public function testTheDefaultAjaxTimeoutSurvivesWithoutTheMinkTree(): void {
    $container = $this->load([]);

    (new BehatStepsExtension())->process($container);

    $parameters = $container->getParameter('behat_steps.parameters');
    $this->assertIsArray($parameters);
    $this->assertSame(5, $parameters['ajax_timeout']);
  }

  public function testBlackboxDriverIsAlwaysRegistered(): void {
    $container = $this->load([]);

    $this->assertTrue($container->hasDefinition('behat_steps.driver.blackbox'));
    $this->assertFalse($container->hasDefinition('behat_steps.driver.drupal'));
    $this->assertFalse($container->hasDefinition('behat_steps.driver.drush'));
  }

  public function testServicesFileIsLoaded(): void {
    $container = $this->load([]);

    $this->assertTrue($container->hasDefinition('behat_steps.driver_manager'));
    $this->assertTrue($container->hasDefinition('behat_steps.authentication_manager'));
    $this->assertTrue($container->hasDefinition('behat_steps.user_manager'));
    $this->assertTrue($container->hasDefinition('behat_steps.context.initializer'));
    $this->assertTrue($container->hasDefinition('behat_steps.context.attribute_reader'));
    $this->assertTrue($container->hasDefinition('behat_steps.listener.driver'));
    $this->assertTrue($container->hasDefinition('behat_steps.region_selector'));
  }

  public function testDrupalDriverIsRegisteredWithItsRoot(): void {
    $container = $this->load(['drupal' => ['drupal_root' => 'web']]);

    $this->assertTrue($container->hasDefinition('behat_steps.driver.drupal'));
    $this->assertTrue($container->hasDefinition('behat_steps.driver.core'));
    $this->assertSame('web', $container->getParameter('behat_steps.driver.drupal.drupal_root'));
  }

  public function testDrushDriverIsRegisteredWithItsRoot(): void {
    $container = $this->load(['drush' => ['root' => 'web']]);

    $this->assertTrue($container->hasDefinition('behat_steps.driver.drush'));
    $this->assertSame('web', $container->getParameter('behat_steps.driver.drush.root'));
    $this->assertFalse($container->getParameter('behat_steps.driver.drush.alias'));
  }

  public function testDrushDriverAcceptsAliasInsteadOfRoot(): void {
    $container = $this->load(['drush' => ['alias' => '@self']]);

    $this->assertSame('@self', $container->getParameter('behat_steps.driver.drush.alias'));
    $this->assertFalse($container->getParameter('behat_steps.driver.drush.root'));
  }

  public function testDrupalDriverRequiresItsRoot(): void {
    $this->expectException(InvalidConfigurationException::class);
    $this->expectExceptionMessage('The child config "drupal_root" under "behat_steps.drupal" must be configured');

    $this->load(['drupal' => []]);
  }

  public function testDrushDriverRequiresAliasOrRoot(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Drush `alias` or `root` path is required for the Drush driver.');

    $this->load(['drush' => []]);
  }

  public function testDrushGlobalOptionsReachTheDriver(): void {
    $container = $this->load(['drush' => ['root' => 'web', 'global_options' => '--yes']]);

    $this->assertSame([['setArguments', ['--yes']]], $container->getDefinition('behat_steps.driver.drush')->getMethodCalls());
  }

  public function testDrushGlobalOptionsAreOptional(): void {
    $container = $this->load(['drush' => ['root' => 'web']]);

    $this->assertSame([], $container->getDefinition('behat_steps.driver.drush')->getMethodCalls());
  }

  /**
   * Tests that the configured region map reaches the container.
   *
   * @param array<string, mixed> $config
   *   The extension configuration, before schema normalisation.
   * @param array<string, string> $expected
   *   The region map expected on the container parameter.
   */
  #[DataProvider('dataProviderRegionsReachTheContainer')]
  public function testRegionsReachTheContainer(array $config, array $expected): void {
    $container = $this->load($config);

    $this->assertSame($expected, $container->getParameter('behat_steps.regions'));

    // The same map is surfaced through 'behat_steps.parameters', so a context
    // using ParametersTrait resolves the value the 'region' selector uses.
    $parameters = $container->getParameter('behat_steps.parameters');
    $this->assertIsArray($parameters);
    $this->assertSame($expected, $parameters['regions']);
  }

  public static function dataProviderRegionsReachTheContainer(): \Iterator {
    yield 'configured map is exposed' => [
      ['regions' => ['Header' => '#header', 'Content' => '#main']],
      ['Header' => '#header', 'Content' => '#main'],
    ];

    yield 'no regions yields an empty map' => [
      [],
      [],
    ];
  }

  /**
   * Tests that grouped mappings flatten into one lookup map.
   *
   * @param array<string, mixed> $config
   *   The extension configuration, before schema normalisation.
   * @param array<string, string> $expected
   *   The expected flattened mapping map.
   */
  #[DataProvider('dataProviderMappingsFlatten')]
  public function testMappingsFlatten(array $config, array $expected): void {
    $parameters = $this->load($config)->getParameter('behat_steps.parameters');

    $this->assertIsArray($parameters);
    $this->assertSame($expected, $parameters['mappings']);
  }

  public static function dataProviderMappingsFlatten(): \Iterator {
    yield 'single group flattens to its entries' => [
      ['mappings' => ['paths' => ['User Registration' => '/user/register', 'User Login' => '/user/login']]],
      ['User Registration' => '/user/register', 'User Login' => '/user/login'],
    ];

    yield 'multiple groups merge into one map' => [
      ['mappings' => ['paths' => ['Home' => '/'], 'text' => ['Greeting' => 'Hello']]],
      ['Home' => '/', 'Greeting' => 'Hello'],
    ];

    yield 'no mappings yields an empty map' => [
      [],
      [],
    ];
  }

  public function testDuplicateMappingKeyAcrossGroupsThrows(): void {
    $this->expectException(InvalidConfigurationException::class);
    $this->expectExceptionMessage('Duplicate mapping key "Home" found in groups "paths" and "aliases" under "behat_steps: mappings:".');

    $this->load(['mappings' => ['paths' => ['Home' => '/'], 'aliases' => ['Home' => '/front']]]);
  }

  /**
   * Tests the values the schema falls back to.
   *
   * @param string $name
   *   The configuration key to read.
   * @param mixed $expected
   *   The value the schema is expected to default to.
   */
  #[DataProvider('dataProviderSchemaDefaults')]
  public function testSchemaDefaults(string $name, mixed $expected): void {
    $parameters = $this->load([])->getParameter('behat_steps.parameters');

    $this->assertIsArray($parameters);
    $this->assertSame($expected, $parameters[$name]);
  }

  public static function dataProviderSchemaDefaults(): \Iterator {
    yield 'login_field' => ['login_field', 'name'];
    yield 'login_wait' => ['login_wait', 0];
    yield 'ajax_timeout' => ['ajax_timeout', 5];
    yield 'text' => [
      'text',
      [
        'login_url' => '/user',
        'logout_url' => '/user/logout',
        'logout_confirm_url' => '/user/logout/confirm',
        'log_in' => 'Log in',
        'log_out' => 'Log out',
        'password_field' => 'Password',
        'username_field' => 'Username',
      ],
    ];
    yield 'selectors' => [
      'selectors',
      [
        'login_form_selector' => 'form#user-login,form#user-login-form',
        'logged_in_selector' => 'body.logged-in,body.user-logged-in',
      ],
    ];
  }

  public function testMessageSelectorsAreConfigurable(): void {
    $parameters = $this->load(['selectors' => ['messages' => ['error' => '.messages--error']]])->getParameter('behat_steps.parameters');

    $this->assertIsArray($parameters);
    $this->assertSame(['error' => '.messages--error'], $parameters['selectors']['messages']);
  }

  public function testProcessSwapsInTheContextClassGenerator(): void {
    $extension = new BehatStepsExtension();
    $container = $this->load([], $extension);

    $extension->process($container);

    $this->assertSame(ClassGenerator::class, $container->getDefinition(ContextExtension::CLASS_GENERATOR_TAG . '.simple')->getClass());
  }

  public function testProcessRegistersTheTaggedDrivers(): void {
    $extension = new BehatStepsExtension();
    $container = $this->load(['drupal' => ['drupal_root' => 'web']], $extension);

    $extension->process($container);

    $calls = $container->getDefinition('behat_steps.driver_manager')->getMethodCalls();
    $names = array_map(static fn(array $call): string => $call[0], $calls);

    $this->assertSame(['registerDriver', 'registerDriver'], $names);
  }

  /**
   * Tests the driver lists a suite may declare.
   *
   * @param array<array-key, mixed> $drivers
   *   The 'drivers' setting the suite declares.
   */
  #[DataProvider('dataProviderProcessAcceptsValidSuiteDriverList')]
  public function testProcessAcceptsValidSuiteDriverList(array $drivers): void {
    $extension = new BehatStepsExtension();
    $container = $this->load(['drupal' => ['drupal_root' => 'web']], $extension);
    $container->setParameter('suite.configurations', ['default' => ['type' => NULL, 'settings' => ['drivers' => $drivers]]]);

    $extension->process($container);

    $this->assertTrue($container->hasDefinition('behat_steps.driver_manager'));
  }

  public static function dataProviderProcessAcceptsValidSuiteDriverList(): \Iterator {
    yield 'bare entries' => [['drupal', 'blackbox']];
    yield 'aliased entries' => [['api' => 'drupal']];
    yield 'bare and aliased mixed' => [['blackbox', 'api' => 'drupal']];
    yield 'a name carrying a hyphen, an underscore and a digit' => [['api-2_b' => 'drupal']];
    yield 'a name matched without regard to case' => [['Drupal']];
  }

  /**
   * Tests the driver lists a suite may not declare.
   *
   * @param array<array-key, mixed> $drivers
   *   The 'drivers' setting the suite declares.
   * @param string $message
   *   Part of the message the build is expected to fail with.
   */
  #[DataProvider('dataProviderProcessRejectsInvalidSuiteDriverList')]
  public function testProcessRejectsInvalidSuiteDriverList(array $drivers, string $message): void {
    $extension = new BehatStepsExtension();
    $container = $this->load(['drupal' => ['drupal_root' => 'web']], $extension);
    $container->setParameter('suite.configurations', ['default' => ['type' => NULL, 'settings' => ['drivers' => $drivers]]]);

    $this->expectException(InvalidConfigurationException::class);
    $this->expectExceptionMessage($message);

    $extension->process($container);
  }

  public static function dataProviderProcessRejectsInvalidSuiteDriverList(): \Iterator {
    yield 'an unregistered driver' => [['ghost'], 'The "default" suite lists the driver "ghost" under "drivers:", which is not registered. Registered drivers: blackbox, drupal.'];
    yield 'an unregistered driver behind an alias' => [['api' => 'ghost'], 'which is not registered'];
    yield 'a tag name carrying a space' => [['my driver' => 'drupal'], 'so that "@driver:my driver" is a valid tag'];
    yield 'a tag name carrying a colon' => [['my:driver' => 'drupal'], 'so that "@driver:my:driver" is a valid tag'];
    yield 'an entry that is not a name' => [[['drupal']], 'Write each entry as a driver name, or as "tag: driver name".'];
    yield 'an empty entry' => [[''], 'Write each entry as a driver name, or as "tag: driver name".'];
  }

  /**
   * Tests the suite configurations that the build leaves alone.
   *
   * @param array<string, mixed>|string|null $suites
   *   The 'suite.configurations' parameter, or NULL to leave it unset.
   */
  #[DataProvider('dataProviderProcessSkipsSuitesWithNothingToValidate')]
  public function testProcessSkipsSuitesWithNothingToValidate(array|string|null $suites): void {
    $extension = new BehatStepsExtension();
    $container = $this->load(['drupal' => ['drupal_root' => 'web']], $extension);

    if ($suites !== NULL) {
      $container->setParameter('suite.configurations', $suites);
    }

    $extension->process($container);

    $this->assertTrue($container->hasDefinition('behat_steps.driver_manager'));
  }

  public static function dataProviderProcessSkipsSuitesWithNothingToValidate(): \Iterator {
    yield 'no suite configuration at all' => [NULL];
    yield 'a parameter that is not a map' => ['default'];
    yield 'a suite that is not a map' => [['default' => 'yes']];
    yield 'a suite with no settings' => [['default' => ['type' => NULL]]];
    yield 'a suite declaring no driver list' => [['default' => ['type' => NULL, 'settings' => ['paths' => []]]]];
    yield 'a driver list that is not a list' => [['default' => ['type' => NULL, 'settings' => ['drivers' => 'drupal']]]];
  }

  public function testAbsoluteBinaryPathIsReturnedAsIs(): void {
    $this->assertSame('/usr/local/bin/drush', BehatStepsExtension::resolveBinaryPath('/usr/local/bin/drush'));
  }

  public function testBareBinaryCommandIsReturnedAsIs(): void {
    $this->assertSame('drush', BehatStepsExtension::resolveBinaryPath('drush'));
  }

  public function testBinaryPathResolvesFromWorkingDirectory(): void {
    chdir(self::$fixtureDir . '/project');

    $this->assertSame(self::$fixtureDir . '/project/vendor/bin/drush', BehatStepsExtension::resolveBinaryPath('vendor/bin/drush'));
  }

  public function testBinaryPathResolvesFromParentDirectory(): void {
    chdir(self::$fixtureDir . '/project/web');

    $this->assertSame(self::$fixtureDir . '/project/vendor/bin/drush', BehatStepsExtension::resolveBinaryPath('vendor/bin/drush'));
  }

  public function testUnresolvableBinaryPathIsReturnedAsIs(): void {
    chdir(self::$fixtureDir);

    $this->assertSame('some/nonexistent/binary', BehatStepsExtension::resolveBinaryPath('some/nonexistent/binary'));
  }

  /**
   * Runs a raw configuration array through the schema and into a container.
   *
   * @param array<string, mixed> $config
   *   The extension configuration, before schema normalisation.
   * @param \DrevOps\BehatSteps\Behat\ServiceContainer\BehatStepsExtension|null $extension
   *   The extension to load with, when the test needs it afterwards.
   */
  protected function load(array $config, ?BehatStepsExtension $extension = NULL): ContainerBuilder {
    $extension ??= new BehatStepsExtension();

    $builder = new ArrayNodeDefinition(BehatStepsExtension::CONFIG_KEY);
    $extension->configure($builder);
    $tree = $builder->getNode(TRUE);

    $container = new ContainerBuilder();
    $extension->load($container, $tree->finalize($tree->normalize($config)));

    return $container;
  }

}
