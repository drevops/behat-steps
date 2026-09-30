<?php

declare(strict_types=1);

namespace DrevOps\BehatSteps\Tests\Unit\Behat\ServiceContainer;

use Behat\Behat\Context\ServiceContainer\ContextExtension;
use Behat\Mink\Driver\BrowserKitDriver;
use Behat\MinkExtension\ServiceContainer\MinkExtension;
use Behat\Testwork\ServiceContainer\ExtensionManager;
use DrevOps\BehatSteps\Behat\Generator\ClassGenerator;
use DrevOps\BehatSteps\Behat\Mink\ServiceContainer\Driver\BrowserKitFactory;
use DrevOps\BehatSteps\Behat\ServiceContainer\BehatStepsExtension;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\ForeignMinkExtension;
use DrevOps\BehatSteps\Tests\Unit\Behat\Fixtures\TestableBrowserKitFactory;
use Drupal\Tests\DrupalTestBrowser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

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

  /**
   * Tests that the first-party factory displaces Mink's own.
   *
   * @param array<int, class-string<\Behat\Testwork\ServiceContainer\Extension>> $locators
   *   The extensions to activate, in activation order.
   */
  #[DataProvider('dataProviderInitializeRegistersTheFirstPartyBrowserKitFactory')]
  public function testInitializeRegistersTheFirstPartyBrowserKitFactory(array $locators): void {
    $manager = new ExtensionManager([]);

    foreach ($locators as $locator) {
      $manager->activateExtension($locator);
    }

    $mink = $manager->getExtension('mink');
    $this->assertInstanceOf(MinkExtension::class, $mink);
    $before = $this->minkDriverFactories($mink);

    $manager->initializeExtensions();

    $after = $this->minkDriverFactories($mink);
    $this->assertNotInstanceOf(BrowserKitFactory::class, $before['browserkit_http']);
    $this->assertInstanceOf(BrowserKitFactory::class, $after['browserkit_http']);
    $this->assertSame(array_keys($before), array_keys($after));
  }

  public static function dataProviderInitializeRegistersTheFirstPartyBrowserKitFactory(): \Iterator {
    yield 'Mink activated first' => [[MinkExtension::class, BehatStepsExtension::class]];
    yield 'Mink activated last' => [[BehatStepsExtension::class, MinkExtension::class]];
  }

  public function testInitializeBuildsTheBrowserKitSessionOnDrupalTestBrowser(): void {
    $mink = $this->initializeMink();
    $container = new ContainerBuilder();

    $mink->load($container, $this->processMinkConfig($mink, ['base_url' => 'http://example.com', 'sessions' => ['default' => ['browserkit_http' => NULL]]]));

    $session = $container->getDefinition('mink')->getMethodCalls()[0][1][1];
    $this->assertInstanceOf(Definition::class, $session);
    $driver = $session->getArgument(0);
    $this->assertInstanceOf(Definition::class, $driver);
    $client = $driver->getArgument(0);
    $this->assertInstanceOf(Definition::class, $client);

    $this->assertSame(BrowserKitDriver::class, $driver->getClass());
    $this->assertSame(DrupalTestBrowser::class, $client->getClass());
  }

  public function testInitializeSkipsSuiteWithoutMink(): void {
    $manager = new ExtensionManager([]);

    (new BehatStepsExtension())->initialize($manager);

    $this->assertSame([], $manager->getExtensions());
  }

  public function testInitializeLeavesForeignExtensionUnderMinkKey(): void {
    $foreign = new ForeignMinkExtension();

    (new BehatStepsExtension())->initialize(new ExtensionManager([$foreign]));

    $this->assertSame([], $foreign->driverFactories);
  }

  public function testInitializeKeepsMinkFactoryWithoutDrupal(): void {
    $mink = new MinkExtension();
    $extension = new class extends BehatStepsExtension {

      /**
       * {@inheritdoc}
       */
      protected function createBrowserKitFactory(): BrowserKitFactory {
        return new TestableBrowserKitFactory();
      }

    };

    $extension->initialize(new ExtensionManager([$mink]));

    $this->assertNotInstanceOf(BrowserKitFactory::class, $this->minkDriverFactories($mink)['browserkit_http']);
  }

  public function testInitializeAddsNoAjaxTimeoutToMinkConfiguration(): void {
    $mink = $this->initializeMink();

    $this->expectException(InvalidConfigurationException::class);
    $this->expectExceptionMessage('Unrecognized option "ajax_timeout" under "mink"');

    $this->processMinkConfig($mink, ['ajax_timeout' => 5, 'sessions' => ['default' => ['browserkit_http' => NULL]]]);
  }

  public function testBlackboxDriverIsAlwaysRegistered(): void {
    $container = $this->load([]);

    $this->assertTrue($container->hasDefinition('behat_steps.driver.blackbox'));
    $this->assertFalse($container->hasDefinition('behat_steps.driver.drupal'));
    $this->assertFalse($container->hasDefinition('behat_steps.driver.drush'));
  }

  public function testServicesFileIsLoaded(): void {
    $container = $this->load([]);

    $this->assertTrue($container->hasDefinition('behat_steps.driver_registry'));
    $this->assertTrue($container->hasDefinition('behat_steps.authenticator'));
    $this->assertTrue($container->hasDefinition('behat_steps.user_registry'));
    $this->assertTrue($container->hasDefinition('behat_steps.context.initializer'));
    $this->assertTrue($container->hasDefinition('behat_steps.context.attribute_reader'));
    $this->assertTrue($container->hasDefinition('behat_steps.listener.driver'));
    $this->assertTrue($container->hasDefinition('behat_steps.listener.skip_tag'));
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
   * Tests that the steps section reaches the parameters untouched.
   *
   * A group there may name a trait only one of the registered contexts
   * composes, so the extension validates nothing about its contents.
   *
   * @param array<string, mixed> $config
   *   The extension configuration, before schema normalisation.
   * @param array<string, mixed> $expected
   *   The expected steps section.
   */
  #[DataProvider('dataProviderStepsSectionIsPassedThrough')]
  public function testStepsSectionIsPassedThrough(array $config, array $expected): void {
    $parameters = $this->load($config)->getParameter('behat_steps.parameters');

    $this->assertIsArray($parameters);
    $this->assertSame($expected, $parameters['steps']);
  }

  public static function dataProviderStepsSectionIsPassedThrough(): \Iterator {
    yield 'one group' => [
      ['steps' => ['javascript' => ['enabled' => FALSE]]],
      ['javascript' => ['enabled' => FALSE]],
    ];

    yield 'nested values are kept as written' => [
      ['steps' => ['mapping' => ['groups' => ['paths' => ['Home' => '/']]]]],
      ['mapping' => ['groups' => ['paths' => ['Home' => '/']]]],
    ];

    yield 'a group no context composes is kept' => [
      ['steps' => ['nonexistent' => ['enabled' => FALSE]]],
      ['nonexistent' => ['enabled' => FALSE]],
    ];

    yield 'the AJAX timeout in the wait group' => [
      ['steps' => ['wait' => ['ajax_timeout' => 10]]],
      ['wait' => ['ajax_timeout' => 10]],
    ];

    yield 'no steps section yields an empty map' => [
      [],
      [],
    ];
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
    yield 'steps' => ['steps', []];
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

  public function testMessageSelectorsAtTheirFormerPathAreRejected(): void {
    $this->expectException(InvalidConfigurationException::class);
    $this->expectExceptionMessage('The "selectors: messages:" setting under "behat_steps" moved to "steps: message: selectors:". Move each severity selector across.');

    $this->load(['selectors' => ['messages' => ['error' => '.messages--error']]]);
  }

  public function testSelectorTheTreeDoesNotDeclareIsKept(): void {
    $parameters = $this->load(['selectors' => ['acme_banner' => '.acme-banner']])->getParameter('behat_steps.parameters');

    $this->assertIsArray($parameters);
    $this->assertSame('.acme-banner', $parameters['selectors']['acme_banner']);
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

    $calls = $container->getDefinition('behat_steps.driver_registry')->getMethodCalls();
    $names = array_map(static fn(array $call): string => $call[0], $calls);

    $this->assertSame(['registerDriver', 'registerDriver'], $names);
  }

  public function testTheConfiguredDriverListReachesTheContainer(): void {
    $container = $this->load(['drivers' => ['blackbox', 'api' => 'drupal'], 'drupal' => ['drupal_root' => 'web']]);

    $this->assertSame(['blackbox', 'api' => 'drupal'], $container->getParameter(BehatStepsExtension::DRIVERS_PARAMETER));
  }

  public function testAnOmittedDriverListReachesTheContainerAsEmpty(): void {
    $this->assertSame([], $this->load([])->getParameter(BehatStepsExtension::DRIVERS_PARAMETER));
  }

  /**
   * Tests the driver lists the configuration may declare.
   *
   * @param array<array-key, mixed> $drivers
   *   The 'drivers' list the configuration declares.
   */
  #[DataProvider('dataProviderProcessAcceptsValidDriverList')]
  public function testProcessAcceptsValidDriverList(array $drivers): void {
    $extension = new BehatStepsExtension();
    $container = $this->load(['drivers' => $drivers, 'drupal' => ['drupal_root' => 'web']], $extension);

    $extension->process($container);

    $this->assertTrue($container->hasDefinition('behat_steps.driver_registry'));
  }

  public static function dataProviderProcessAcceptsValidDriverList(): \Iterator {
    yield 'bare entries' => [['drupal', 'blackbox']];
    yield 'aliased entries' => [['api' => 'drupal']];
    yield 'bare and aliased mixed' => [['blackbox', 'api' => 'drupal']];
    yield 'a name carrying a hyphen, an underscore and a digit' => [['api-2_b' => 'drupal']];
    yield 'a name matched without regard to case' => [['Drupal']];
    yield 'an omitted list' => [[]];
  }

  /**
   * Tests the driver lists the configuration may not declare.
   *
   * @param array<array-key, mixed> $drivers
   *   The 'drivers' list the configuration declares.
   * @param string $message
   *   Part of the message the build is expected to fail with.
   */
  #[DataProvider('dataProviderProcessRejectsInvalidDriverList')]
  public function testProcessRejectsInvalidDriverList(array $drivers, string $message): void {
    $extension = new BehatStepsExtension();
    $container = $this->load(['drupal' => ['drupal_root' => 'web']], $extension);
    $container->setParameter(BehatStepsExtension::DRIVERS_PARAMETER, $drivers);

    $this->expectException(InvalidConfigurationException::class);
    $this->expectExceptionMessage($message);

    $extension->process($container);
  }

  public static function dataProviderProcessRejectsInvalidDriverList(): \Iterator {
    yield 'an unregistered driver' => [['ghost'], 'The "drivers" list under "behat_steps" names the driver "ghost", which is not registered. Registered drivers: blackbox, drupal.'];
    yield 'an unregistered driver behind an alias' => [['api' => 'ghost'], 'which is not registered'];
    yield 'a tag name carrying a space' => [['my driver' => 'drupal'], 'so that "@driver:my driver" is a valid tag'];
    yield 'a tag name carrying a colon' => [['my:driver' => 'drupal'], 'so that "@driver:my:driver" is a valid tag'];
    yield 'a tag name carrying a trailing newline' => [["api\n" => 'drupal'], 'A driver name may hold only letters, digits'];
    yield 'an entry that is not a name' => [[['drupal']], 'holds an entry that is not a driver name'];
    yield 'an empty entry' => [[''], 'holds an entry that is not a driver name'];
    yield 'the same name twice' => [['drupal', 'drupal'], 'names "drupal" twice'];
    yield 'two names differing only by case' => [['drupal', 'Drupal'], 'names "drupal" twice'];
    yield 'two aliases differing only by case' => [['api' => 'drupal', 'API' => 'blackbox'], 'names "api" twice'];
  }

  public function testTheSameDriverMayCarryTwoDistinctNames(): void {
    $extension = new BehatStepsExtension();
    $container = $this->load(['drivers' => ['api' => 'drupal', 'web' => 'drupal'], 'drupal' => ['drupal_root' => 'web']], $extension);

    $extension->process($container);

    $this->assertTrue($container->hasDefinition('behat_steps.driver_registry'));
  }

  public function testProcessSkipsValidationWithoutTheDriversParameter(): void {
    $extension = new BehatStepsExtension();
    $container = $this->load(['drupal' => ['drupal_root' => 'web']], $extension);
    $container->getParameterBag()->remove(BehatStepsExtension::DRIVERS_PARAMETER);

    $extension->process($container);

    $this->assertTrue($container->hasDefinition('behat_steps.driver_registry'));
  }

  public function testProcessSkipsValidationWhenTheParameterIsNotList(): void {
    $extension = new BehatStepsExtension();
    $container = $this->load(['drupal' => ['drupal_root' => 'web']], $extension);
    $container->setParameter(BehatStepsExtension::DRIVERS_PARAMETER, 'drupal');

    $extension->process($container);

    $this->assertTrue($container->hasDefinition('behat_steps.driver_registry'));
  }

  public function testTheSchemaRefusesDriverListThatIsNotList(): void {
    $this->expectException(InvalidConfigurationException::class);
    $this->expectExceptionMessage('behat_steps.drivers');

    $this->load(['drivers' => 'drupal']);
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

  /**
   * Returns Mink's extension after this extension has initialized against it.
   */
  protected function initializeMink(): MinkExtension {
    $mink = new MinkExtension();

    (new BehatStepsExtension())->initialize(new ExtensionManager([$mink]));

    return $mink;
  }

  /**
   * Runs a raw Mink configuration array through Mink's schema.
   *
   * @param \Behat\MinkExtension\ServiceContainer\MinkExtension $mink
   *   The Mink extension whose schema to apply.
   * @param array<string, mixed> $config
   *   The Mink configuration, before schema normalisation.
   *
   * @return array<string, mixed>
   *   The processed configuration.
   */
  protected function processMinkConfig(MinkExtension $mink, array $config): array {
    $builder = new ArrayNodeDefinition('mink');
    $mink->configure($builder);
    $tree = $builder->getNode(TRUE);

    $processed = $tree->finalize($tree->normalize($config));
    $this->assertIsArray($processed);

    return $processed;
  }

  /**
   * Returns the driver factories Mink's extension holds, keyed by driver name.
   *
   * @param \Behat\MinkExtension\ServiceContainer\MinkExtension $mink
   *   The Mink extension to read.
   *
   * @return array<string, \Behat\MinkExtension\ServiceContainer\Driver\DriverFactory>
   *   The registered factories.
   */
  protected function minkDriverFactories(MinkExtension $mink): array {
    $factories = (new \ReflectionProperty(MinkExtension::class, 'driverFactories'))->getValue($mink);
    $this->assertIsArray($factories);

    return $factories;
  }

}
